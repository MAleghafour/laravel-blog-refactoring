<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use App\Models\dashboard\Article;
use App\Models\dashboard\Category;
use App\Services\FileUploadService;
use App\Services\ArticleHtmlSanitizer;
use App\Jobs\DeleteArticleThumbnailsJob;
use App\Http\Requests\dashboard\article\StoreArticleRequest;
use App\Http\Requests\dashboard\article\SubmitArticleForReviewRequest;
use App\Http\Requests\dashboard\article\WithdrawArticleRequest;
use App\Http\Requests\dashboard\article\UpdateArticleRequest;
use App\Jobs\SendArticleEditedMailJob;
use Illuminate\Http\Request;
use Illuminate\HTTp\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Support\Arr;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;



class ArticleController extends Controller
{
    use AuthorizesRequests;
    protected FileUploadService $FileUploadService;
    public function __construct(FileUploadService $FileUploadService){
         $this->FileUploadService = $FileUploadService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $this->authorize('viewAny', Article::class);

        $query = Article::query()
            ->with(['author', 'category'])
            ->latest();

        if (auth()->user()->role === 'author') {
            $query->where('author_id', auth()->id());
        }

        $articles = $query->paginate(15);

        return view('dashboard.articles.index', compact('articles'));
    }

    public function trash(): View
    {
        $this->authorize('viewAny', Article::class);

        $query = Article::onlyTrashed()
            ->with(['author', 'category'])
            ->latest('deleted_at');

        if (auth()->user()->role === 'author') {
            $query->where('author_id', auth()->id());
        }

        $articles = $query->paginate(15);

        return view('dashboard.articles.trash', compact('articles'));
    }

    public function reviewQueue(): View
    {
        $this->authorize('viewAny', Article::class);

        $articles = Article::query()
            ->with(['author', 'category'])
            ->where('status', 'pending')
            ->orderBy('submitted_for_review_at', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(15);

        return view('dashboard.articles.review-queue', compact('articles'));
    }

    public function restore(Article $article): RedirectResponse
    {
        $this->authorize('restore', $article);

        DB::transaction(function () use ($article) {
            $article->restore();

            $article->update([
                'status' => 'draft',
            ]);

            $article->events()->create([
                'actor_id' => auth()->id(),
                'event_type' => 'restored',
            ]);
        });

        return redirect()
            ->route('article.trash')
            ->with('success', 'مقاله با موفقیت بازیابی شد.');
    }

    public function destroy(Article $article): RedirectResponse
    {
        $this->authorize('delete', $article);

        DB::transaction(function () use ($article) {
            $article->delete();

            $article->events()->create([
                'actor_id' => auth()->id(),
                'event_type' => 'trashed',
            ]);
        });

        return redirect()
            ->route('article.index')
            ->with('success', 'مقاله با موفقیت به سطل زباله منتقل شد.');
    }

    public function forceDelete(Request $request, Article $article): RedirectResponse {

        $this->authorize('forceDelete', $article);

        $request->validate([
            'confirmation' => [
                'required',
                'string',
                'in:حذف دائمی',
            ],
        ]);

        try {
            DB::transaction(function () use ($article) {

                $thumbnails = $article->only([
                    'thumbnail_small',
                    'thumbnail_medium',
                    'thumbnail_large',
                ]);

                $article->forceDelete();

                if (collect($thumbnails)->filter()->isNotEmpty()) {
                    DeleteArticleThumbnailsJob::dispatch($thumbnails);
                }
            });

        } catch (\Throwable $exception) {

            report($exception);

            return back()->with(
                'error',
                'حذف دائمی مقاله انجام نشد. لطفاً دوباره تلاش کنید.'
            );
        }

        return redirect()
            ->route('article.trash')
            ->with('success', 'مقاله برای همیشه حذف شد.');
    }

    /**
     * Approve a pending article if its version has not changed.
     *
     * Publishes the article and records the approval event
     * within the same database transaction.
     */
    public function approve(Request $request, Article $article): RedirectResponse
    {
        $this->authorize('approve', $article);

        $validated = $request->validate([
            'lock_version' => ['required', 'integer', 'min:0'],
            'explanation' => ['nullable', 'string', 'max:2000'],
        ]);

        $expectedVersion = (int) $validated['lock_version'];

       try{
           // Reject stale review requests with an atomic version check.
            $result = DB::transaction(function () use (
                $article,
                $expectedVersion,
                $validated
            ) {
                $updated = Article::query()
                    ->whereKey($article->id)
                    ->where('status', 'pending')
                    ->where('lock_version', $expectedVersion)
                    ->update([
                        'status' => 'published',
                        'first_published_at' => DB::raw(
                            'COALESCE(first_published_at, CURRENT_TIMESTAMP)'
                        ),
                        'lock_version' => DB::raw('lock_version + 1'),
                        'updated_at' => now(),
                    ]);

                if ($updated !== 1) {
                    return false;
                }

                $article->events()->create([
                    'actor_id' => auth()->id(),
                    'event_type' => 'approved',
                    'explanation' => $validated['explanation'] ?? null,
                ]);

                return true;
            });
       } catch (\Throwable $exception) {
               report($exception);

               return redirect()
                   ->route('article.show', $article)
                   ->with(
                       'error',
                       'تأیید مقاله به دلیل خطای سیستمی انجام نشد. لطفاً دوباره تلاش کنید.'
                   );
       }

        if (! $result) {
            return redirect()
                ->route('article.show', $article)
                ->with(
                    'error',
                    'مقاله پس از باز شدن صفحه تغییر کرده است. لطفاً صفحه را دوباره بارگذاری و بررسی کنید.'
                );
        }

        return redirect()
            ->route('article.show', $article)
            ->with('success', 'مقاله با موفقیت تأیید و منتشر شد.');
    }

    public function returnForCorrection(Request $request, Article $article): RedirectResponse {
        $this->authorize('returnForCorrection', $article);

        $validated = $request->validate([
            'lock_version' => ['required', 'integer', 'min:0'],
            'explanation' => ['required', 'string', 'max:2000'],
        ]);

        $expectedVersion = (int) $validated['lock_version'];

        try {
            $result = DB::transaction(function () use (
                $article,
                $expectedVersion,
                $validated
            ) {
                $updated = Article::query()
                    ->whereKey($article->id)
                    ->where('status', 'pending')
                    ->where('lock_version', $expectedVersion)
                    ->update([
                        'status' => 'draft',
                        'lock_version' => DB::raw('lock_version + 1'),
                        'updated_at' => now(),
                    ]);

                if ($updated !== 1) {
                    return false;
                }

                $article->events()->create([
                    'actor_id' => auth()->id(),
                    'event_type' => 'returned_for_correction',
                    'explanation' => $validated['explanation'],
                ]);

                return true;
            });
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('article.show', $article)
                ->with(
                    'error',
                    'بازگرداندن مقاله برای اصلاح انجام نشد. لطفاً دوباره تلاش کنید.'
                );
        }

        if (! $result) {
            return redirect()
                ->route('article.show', $article)
                ->with(
                    'error',
                    'مقاله پس از باز شدن صفحه تغییر کرده است. لطفاً صفحه را دوباره بارگذاری و بررسی کنید.'
                );
        }

        return redirect()
            ->route('article.show', $article)
            ->with(
                'success',
                'مقاله برای اصلاح به نویسنده بازگردانده شد.'
            );
    }

    public function show(Article $article): View
    {
        $this->authorize('view', $article);

        $article->load(['author', 'category']);

        $events = $article->events()
            ->with('actor')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10);

        return view('dashboard.articles.show', compact('article', 'events'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $categories = Category::all();
        return view('dashboard.articles.create', compact('categories'));
    }

    /**
     * Create an article draft with sanitized content.
     */
    public function store(StoreArticleRequest $request, ArticleHtmlSanitizer $sanitizer
    ): RedirectResponse {
        $this->authorize('create', Article::class);

        $data = $request->validated();

        // Enforce server-controlled article fields.
        unset($data['thumbnail']);

        $data['author_id'] = auth()->id();
        $data['status'] = 'draft';
        $data['body'] = $sanitizer->sanitize($data['body'] ?? null);

        $thumbnails = [];

        try {
            if ($request->hasFile('thumbnail')) {
                $thumbnails = $this->FileUploadService->fileUpload(
                    $request->file('thumbnail'),
                    false,
                    true
                );

                $data = array_merge($data, $thumbnails);
            }

            Article::create($data);

            return redirect()
                ->route('article.index')
                ->with('success', 'پیش‌نویس مقاله با موفقیت ثبت شد.');

        } catch (\Throwable $exception) {
            report($exception);

            // Remove uploaded files if article creation fails.
            if (! empty($thumbnails)) {
                try {
                    $this->FileUploadService->deleteExclusiveThumbnails(
                        $thumbnails
                    );
                } catch (\Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            return redirect()
                ->route('article.create')
                ->withInput()
                ->with('error', 'خطایی در ثبت پیش‌نویس مقاله رخ داد.');
        }
    }

    /**
     * Submit an author's draft for editorial review.
     */
    public function submitForReview(SubmitArticleForReviewRequest $request, Article $article
    ): RedirectResponse {
        $validated = $request->validated();
        $expectedVersion = (int) $validated['lock_version'];

        try {
            $result = DB::transaction(function () use (
                $article,
                $expectedVersion
            ) {
                // Reject stale requests with an atomic version check.
                $updated = Article::query()
                    ->whereKey($article->id)
                    ->where('author_id', auth()->id())
                    ->where('status', 'draft')
                    ->where('lock_version', $expectedVersion)
                    ->update([
                        'status' => 'pending',
                        'submitted_for_review_at' => now(),
                        'lock_version' => DB::raw('lock_version + 1'),
                        'updated_at' => now(),
                    ]);

                if ($updated !== 1) {
                    return false;
                }

                $article->events()->create([
                    'actor_id' => auth()->id(),
                    'event_type' => 'submitted_for_review',
                    'explanation' => null,
                ]);

                return true;
            });
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('article.show', $article)
                ->with(
                    'error',
                    'ارسال مقاله به دلیل خطای سیستمی انجام نشد.'
                );
        }

        if (! $result) {
            return redirect()
                ->route('article.show', $article)
                ->with(
                    'error',
                    'مقاله تغییر کرده است. لطفاً صفحه را دوباره بارگذاری کنید.'
                );
        }

        return redirect()
            ->route('article.show', $article)
            ->with('success', 'مقاله با موفقیت برای بازبینی ارسال شد.');
    }

    /**
     * Withdraw an author's pending article from review.
     */
    public function withdraw(WithdrawArticleRequest $request, Article $article
    ): RedirectResponse {
        $expectedVersion = (int) $request->validated()['lock_version'];

        try {
            $result = DB::transaction(function () use (
                $article,
                $expectedVersion
            ) {
                // Prevent stale requests and unauthorized state changes.
                $updated = Article::query()
                    ->whereKey($article->id)
                    ->where('author_id', auth()->id())
                    ->where('status', 'pending')
                    ->where('lock_version', $expectedVersion)
                    ->update([
                        'status' => 'draft',
                        'lock_version' => DB::raw('lock_version + 1'),
                        'updated_at' => now(),
                    ]);

                if ($updated !== 1) {
                    return false;
                }

                $article->events()->create([
                    'actor_id' => auth()->id(),
                    'event_type' => 'withdrawn',
                    'explanation' => null,
                ]);

                return true;
            });
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('article.show', $article)
                ->with(
                    'error',
                    'انصراف از بازبینی به دلیل خطای سیستمی انجام نشد.'
                );
        }

        if (! $result) {
            return redirect()
                ->route('article.show', $article)
                ->with(
                    'error',
                    'مقاله تغییر کرده است. لطفاً صفحه را دوباره بارگذاری کنید.'
                );
        }

        return redirect()
            ->route('article.show', $article)
            ->with('success', 'مقاله از صف بازبینی خارج و به پیش‌نویس برگردانده شد.');
    }

    /**
     * Show the article editing form.
     */
    public function edit(Article $article): View
    {
        $this->authorize('update', $article);

        $categories = Category::query()
            ->orderBy('name')
            ->get();

        $isAdmin = auth()->user()->role === 'admin';
        $isEditingAnotherAuthor = $isAdmin
            && (int) $article->author_id !== (int) auth()->id();

        $correctionFeedback = null;

        if (! $isAdmin && $article->status === 'draft') {
            $correctionFeedback = $article->events()
                ->where('event_type', 'returned_for_correction')
                ->orderByDesc('id')
                ->first();

            if ($correctionFeedback) {
                $hasResubmitted = $article->events()
                    ->where('event_type', 'submitted_for_review')
                    ->where('id', '>', $correctionFeedback->id)
                    ->exists();

                if ($hasResubmitted) {
                    $correctionFeedback = null;
                }
            }
        }

        return view('dashboard.articles.edit', compact(
            'article',
            'categories',
            'isAdmin',
            'isEditingAnotherAuthor',
            'correctionFeedback'
        ));
    }


    /**
     * Update article content with optimistic concurrency control.
     */
    public function update(
        UpdateArticleRequest $request,
        Article $article,
        ArticleHtmlSanitizer $sanitizer
    ): RedirectResponse {
        $validated = $request->validated();
        $expectedVersion = (int) $validated['lock_version'];

        $isAdmin = auth()->user()->role === 'admin';
        $currentStatus = $article->status;

        $targetStatus = $isAdmin
            ? ($validated['status'] ?? $currentStatus)
            : ($currentStatus === 'published' ? 'pending' : $currentStatus);

        $data = Arr::only($validated, [
            'title',
            'slug',
            'body',
            'brief',
            'tags',
            'category_id',
        ]);

        if (array_key_exists('body', $data)) {
            $data['body'] = $sanitizer->sanitize($data['body']);
        }

        $data['status'] = $targetStatus;
        $data['updated_at'] = now();
        $data['lock_version'] = DB::raw('lock_version + 1');

        if ($targetStatus === 'pending' && $currentStatus !== 'pending') {
            $data['submitted_for_review_at'] = now();
        }

        if ($targetStatus === 'published') {
            $data['first_published_at'] = DB::raw(
                'COALESCE(first_published_at, CURRENT_TIMESTAMP)'
            );
        }


        $oldThumbnails = $article->only([
            'thumbnail_small',
            'thumbnail_medium',
            'thumbnail_large',
        ]);

        $newThumbnails = [];
        $committed = false;

        try {
            if ($request->hasFile('thumbnail')) {
                $newThumbnails = $this->FileUploadService->fileUpload(
                    $request->file('thumbnail'),
                    false,
                    true
                );

                $data = array_merge($data, $newThumbnails);
            }

            $result = DB::transaction(function () use (
                $article,
                $expectedVersion,
                $data,
                $isAdmin,
                $validated
            ) {
                // Prevent stale, deleted, or unauthorized updates.
                $query = Article::query()
                    ->whereKey($article->id)
                    ->whereNull('deleted_at')
                    ->where('lock_version', $expectedVersion)
                    ->where('status', $article->status);

                if (! $isAdmin) {
                    $query->where('author_id', auth()->id());
                }

                $updated = $query->update($data);

                if ($updated !== 1) {
                    return false;
                }

                // Record editorial events in the same transaction.
                if ($isAdmin && $article->author_id !== auth()->id()) {
                    $article->events()->create([
                        'actor_id' => auth()->id(),
                        'event_type' => 'admin_edited',
                        'explanation' => $validated['explanation'],
                    ]);
                } elseif (! $isAdmin && $article->status === 'published') {
                    $article->events()->create([
                        'actor_id' => auth()->id(),
                        'event_type' => 'submitted_for_review',
                        'explanation' => null,
                    ]);
                } elseif ($isAdmin && $article->status !== $data['status']) {
                    $article->events()->create([
                        'actor_id' => auth()->id(),
                        'event_type' => 'status_changed',
                        'explanation' => $validated['explanation'] ?? null,
                    ]);
                }

                return true;
            });

            $committed = $result === true;
        } catch (\Throwable $exception) {
            report($exception);
        }

        if (! $committed) {
            // Uploaded files are outside the database transaction.
            if (! empty($newThumbnails)) {
                try {
                    $this->FileUploadService->deleteExclusiveThumbnails(
                        $newThumbnails
                    );
                } catch (\Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            if (isset($result) && $result === false) {
                return redirect()
                    ->route('article.edit', $article)
                    ->withInput()
                    ->with(
                        'error',
                        'مقاله پس از باز شدن فرم تغییر کرده است. لطفاً صفحه را دوباره بارگذاری کنید.'
                    );
            }

            return redirect()
                ->route('article.edit', $article)
                ->withInput()
                ->with('error', 'ویرایش مقاله به دلیل خطای سیستمی انجام نشد.');
        }

        // Delete old thumbnails only after the database commit.
        if (! empty($newThumbnails)) {
            try {
                $this->FileUploadService->deleteExclusiveThumbnails(
                    $oldThumbnails
                );
            } catch (\Throwable $cleanupException) {
                // A cleanup failure must not invalidate a committed update.
                report($cleanupException);
            }
        }

        // Queue author notification only after the update has committed.
        if ($isAdmin && (int) $article->author_id !== (int) auth()->id()) {
            try {
                $article->refresh();

                SendArticleEditedMailJob::dispatch(
                    $article->author->email,
                    $article->title ?? 'بدون عنوان',
                    $validated['explanation'],
                    $article->id
                );
            } catch (\Throwable $exception) {
                // Notification failure must not invalidate a committed edit.
                report($exception);
            }
        }

        return redirect()
            ->route('article.show', $article)
            ->with('success', 'تغییرات مقاله با موفقیت ذخیره شد.');
    }
}
