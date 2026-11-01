<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\dashboard\category\DestroyCategoryRequest;
use App\Http\Requests\dashboard\category\StoreCategoryRequest;
use App\Http\Requests\dashboard\category\UpdateCategoryRequest;
use App\Models\dashboard\Article;
use App\Models\dashboard\Category;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::withCount('articles')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15);

        $categoryCount = Category::count();

        return view('dashboard.category', compact('categories', 'categoryCount'));
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $data = $request->safe()->only([
            'name',
            'slug',
            'description',
            'icon',
        ]);

        try {
            // Database constraints close validation race windows.
            Category::create($data);
        } catch (QueryException $exception) {
            $message = $exception->getMessage();
            $isExpectedConstraintFailure = str_contains(
                $message,
                'UNIQUE constraint failed: categories.name'
            ) || str_contains(
                $message,
                'UNIQUE constraint failed: categories.slug'
            ) || str_contains(
                $message,
                'category slug conflicts with category_slug_histories.slug'
            );

            if ($isExpectedConstraintFailure) {
                return redirect()
                    ->route('category.index')
                    ->withInput()
                    ->with(
                        'error',
                        'نام یا نامک انتخاب‌شده قبلاً استفاده شده است.'
                    );
            }

            throw $exception;
        }

        return redirect()
            ->route('category.index')
            ->with('success', 'دسته‌بندی با موفقیت ایجاد شد.');
    }

    public function edit(int $id): RedirectResponse
    {
        $category = Category::find($id);

        if ($category === null) {
            return redirect()
                ->route('category.index')
                ->with('error', 'دسته بندی مورد نظر پیدا نشد!');
        }

        return redirect()
            ->route('category.index')
            ->withInput([
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'icon' => $category->icon,
                'lock_version' => $category->lock_version,
            ])
            ->with('category_form_edit_id', $category->id);
    }

    public function update(
        UpdateCategoryRequest $request,
        $id
    ): RedirectResponse {
        $category = Category::findOrfail($id);
        $validated = $request->validated();
        $expectedVersion = (int) $validated['lock_version'];
        $data = $request->safe()->only([
            'name',
            'slug',
            'description',
            'icon',
        ]);

        $changes = [];

        foreach ($data as $field => $value) {
            if ($category->getAttribute($field) !== $value) {
                $changes[$field] = $value;
            }
        }

        try {
            $result = DB::transaction(function () use (
                $category,
                $expectedVersion,
                $changes
            ) {
                if ($changes === []) {
                    return Category::query()
                        ->whereKey($category->id)
                        ->where('lock_version', $expectedVersion)
                        ->exists()
                            ? 'unchanged'
                            : 'stale';
                }

                $slugChanged = array_key_exists('slug', $changes);
                $oldSlug = $category->slug;
                $updateData = [
                    ...$changes,
                    'lock_version' => DB::raw('lock_version + 1'),
                    'updated_at' => now(),
                ];

                // Compare and swap prevents overwriting a concurrent update.
                $updated = Category::query()
                    ->whereKey($category->id)
                    ->where('lock_version', $expectedVersion)
                    ->update($updateData);

                if ($updated !== 1) {
                    return 'stale';
                }

                if ($slugChanged) {
                    $category->slugHistories()->create([
                        'slug' => $oldSlug,
                    ]);
                }

                return 'updated';
            });
        } catch (QueryException $exception) {
            $message = $exception->getMessage();
            $isExpectedConstraintFailure = str_contains(
                $message,
                'UNIQUE constraint failed: categories.name'
            ) || str_contains(
                $message,
                'UNIQUE constraint failed: categories.slug'
            ) || str_contains(
                $message,
                'UNIQUE constraint failed: category_slug_histories.slug'
            ) || str_contains(
                $message,
                'category slug conflicts with category_slug_histories.slug'
            ) || str_contains(
                $message,
                'category slug history conflicts with categories.slug'
            );

            if ($isExpectedConstraintFailure) {
                return redirect()
                    ->route('category.index')
                    ->withInput()
                    ->with('category_form_edit_id', $category->id)
                    ->with(
                        'error',
                        'نام یا نامک انتخاب‌شده قبلاً استفاده شده است.'
                    );
            }

            throw $exception;
        }

        if ($result === 'stale') {
            return redirect()
                ->route('category.index')
                ->withInput()
                ->with('category_form_edit_id', $category->id)
                ->with(
                    'error',
                    'دسته‌بندی پس از باز شدن فرم تغییر کرده است. لطفاً صفحه را دوباره بارگذاری کنید.'
                );
        }

        $successMessage = $result === 'updated'
            ? 'دسته‌بندی با موفقیت به‌روزرسانی شد.'
            : 'تغییری برای ذخیره وجود نداشت.';

        return redirect()
            ->route('category.index')
            ->with('success', $successMessage);

    }

    public function destroy(
        DestroyCategoryRequest $request,
        int $id
    ): RedirectResponse {
        $category = Category::find($id);

        if ($category === null) {
            return redirect()
                ->route('category.index')
                ->with(
                    'error',
                    'دسته‌بندی مورد نظر یافت نشد یا قبلاً حذف شده است.'
                );
        }

        $expectedVersion = (int) $request->validated()['lock_version'];

        try {
            $result = DB::transaction(function () use (
                $category,
                $expectedVersion
            ) {
                // Soft-deleted articles must also block category deletion.
                $hasArticles = Article::withTrashed()
                    ->where('category_id', $category->id)
                    ->exists();

                if ($hasArticles) {
                    return 'has_articles';
                }

                // Delete first so the current slug can pass history triggers.
                $deleted = Category::query()
                    ->whereKey($category->id)
                    ->where('lock_version', $expectedVersion)
                    ->delete();

                if ($deleted !== 1) {
                    return Category::query()
                        ->whereKey($category->id)
                        ->exists()
                            ? 'stale'
                            : 'missing';
                }

                $category->slugHistories()->create([
                    'slug' => $category->slug,
                ]);

                return 'deleted';
            });
        } catch (QueryException $exception) {
            $message = $exception->getMessage();

            if (str_contains($message, 'FOREIGN KEY constraint failed')) {
                return redirect()
                    ->route('category.index')
                    ->withInput()
                    ->with(
                        'error',
                        'این دسته‌بندی دارای مقاله است و قابل حذف نیست.'
                    );
            }

            $isExpectedSlugFailure = str_contains(
                $message,
                'UNIQUE constraint failed: category_slug_histories.slug'
            ) || str_contains(
                $message,
                'category slug history conflicts with categories.slug'
            );

            if ($isExpectedSlugFailure) {
                return redirect()
                    ->route('category.index')
                    ->withInput()
                    ->with(
                        'error',
                        'حذف دسته‌بندی به دلیل تداخل نامک انجام نشد.'
                    );
            }

            throw $exception;
        }

        if ($result === 'has_articles') {
            return redirect()
                ->route('category.index')
                ->with(
                    'error',
                    'این دسته‌بندی دارای مقاله است و قابل حذف نیست.'
                );
        }

        if ($result === 'stale') {
            return redirect()
                ->route('category.index')
                ->with(
                    'error',
                    'دسته‌بندی پس از نمایش صفحه تغییر کرده است. لطفاً صفحه را دوباره بارگذاری کنید.'
                );
        }

        if ($result === 'missing') {
            return redirect()
                ->route('category.index')
                ->with(
                    'error',
                    'دسته‌بندی مورد نظر یافت نشد یا قبلاً حذف شده است.'
                );
        }

        return redirect()
            ->route('category.index')
            ->with(
                'success',
                'دسته‌بندی با موفقیت حذف شد و نامک آن محفوظ ماند.'
            );
    }
}
