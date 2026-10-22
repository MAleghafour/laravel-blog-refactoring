@extends('layouts.dashboard.master')

    @section('content')
    <main>
        <section class="py-4">
            <div class="container">

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">جزئیات مقاله</h1>

                    <a href="{{ route('article.index') }}"
                       class="btn btn-sm btn-outline-secondary">
                        بازگشت به مقالات
                    </a>
                </div>

                <div class="card border rounded-3 mb-4">
                    <div class="card-body p-3">

                        <h2 class="h4">
                            {{ $article->title ?: 'بدون عنوان' }}
                        </h2>

                        <dl class="row mb-0">
                            <dt class="col-md-3">نویسنده</dt>
                            <dd class="col-md-9">
                                {{ $article->author?->name ?? 'نامشخص' }}
                            </dd>

                            <dt class="col-md-3">دسته‌بندی</dt>
                            <dd class="col-md-9">
                                {{ $article->category?->name ?? 'بدون دسته‌بندی' }}
                            </dd>

                            <dt class="col-md-3">وضعیت</dt>
                            <dd class="col-md-9">
                                @switch($article->status)
                                    @case('draft')
                                        <span class="badge bg-secondary">پیش‌نویس</span>
                                        @break
                                    @case('pending')
                                        <span class="badge bg-warning text-dark">در انتظار بررسی</span>
                                        @break
                                    @case('published')
                                        <span class="badge bg-success">منتشرشده</span>
                                        @break
                                    @default
                                        <span class="badge bg-light text-dark">نامشخص</span>
                                @endswitch
                            </dd>

                            <dt class="col-md-3">Slug</dt>
                            <dd class="col-md-9">{{ $article->slug }}</dd>

                            <dt class="col-md-3">توضیح کوتاه</dt>
                            <dd class="col-md-9">{{ $article->brief ?: '—' }}</dd>

                            <dt class="col-md-3">تگ‌ها</dt>
                            <dd class="col-md-9">{{ $article->tags ?: '—' }}</dd>

                            <dt class="col-md-3">تاریخ ایجاد</dt>
                            <dd class="col-md-9">
                                {{ $article->created_at?->format('Y/m/d H:i') ?? '—' }}
                            </dd>

                            <dt class="col-md-3">آخرین ویرایش</dt>
                            <dd class="col-md-9">
                                {{ $article->updated_at?->format('Y/m/d H:i') ?? '—' }}
                            </dd>

                            <dt class="col-md-3">اولین انتشار</dt>
                            <dd class="col-md-9">
                                {{ $article->first_published_at?->format('Y/m/d H:i') ?? '—' }}
                            </dd>

                            <dt class="col-md-3">آخرین ارسال برای بررسی</dt>
                            <dd class="col-md-9">
                                {{ $article->submitted_for_review_at?->format('Y/m/d H:i') ?? '—' }}
                            </dd>
                        </dl>

                        @can('submitForReview', $article)
                            <div class="border rounded p-3 mt-4">
                                <h3 class="h5">ارسال مقاله برای بازبینی</h3>

                                <p class="text-muted">
                                    پس از ارسال، مقاله در انتظار بررسی مدیر قرار می‌گیرد.
                                    برای ارسال، عنوان، دسته‌بندی و حداقل ۵۰ کاراکتر
                                    متن قابل‌مشاهده الزامی است.
                                </p>

                                @if ($errors->hasAny(['title', 'category_id', 'body']))
                                    <div class="alert alert-danger" role="alert">
                                        <ul class="mb-0">
                                            @foreach (['title', 'category_id', 'body'] as $field)
                                                @foreach ($errors->get($field) as $message)
                                                    <li>{{ $message }}</li>
                                                @endforeach
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <form action="{{ route('article.submit', $article) }}"
                                      method="POST">
                                    @csrf

                                    <input
                                        type="hidden"
                                        name="lock_version"
                                        value="{{ $article->lock_version }}"
                                    >

                                    @error('lock_version')
                                    <div class="alert alert-danger" role="alert">
                                        {{ $message }}
                                    </div>
                                    @enderror

                                    <button type="submit" class="btn btn-primary">
                                        ارسال برای بازبینی
                                    </button>
                                </form>
                            </div>
                        @endcan

                        @can('withdraw', $article)
                            <div class="border rounded p-3 mt-4">
                                <h3 class="h5">انصراف از بازبینی</h3>

                                <p class="text-muted">
                                    با انصراف از بازبینی، مقاله از صف بررسی خارج
                                    و به وضعیت پیش‌نویس بازگردانده می‌شود.
                                </p>

                                <form
                                    action="{{ route('article.withdraw', $article) }}"
                                    method="POST"
                                >
                                    @csrf

                                    <input
                                        type="hidden"
                                        name="lock_version"
                                        value="{{ $article->lock_version }}"
                                    >

                                    @error('lock_version')
                                    <div class="alert alert-danger" role="alert">
                                        {{ $message }}
                                    </div>
                                    @enderror

                                    <button type="submit" class="btn btn-outline-warning">
                                        انصراف از بازبینی
                                    </button>
                                </form>
                            </div>
                        @endcan

                        @can('approve', $article)
                                <div class="border rounded p-3 mt-4">
                                    <h3 class="h5">بررسی مقاله</h3>

                                    <p class="text-muted">
                                        با تأیید مقاله، وضعیت آن به منتشرشده تغییر می‌کند.
                                    </p>

                                    <form action="{{ route('article.approve', $article) }}"
                                          method="POST">
                                        @csrf

                                        <input type="hidden"
                                               name="lock_version"
                                               value="{{ $article->lock_version }}">

                                        <div class="mb-3">
                                            <label for="approval_explanation"
                                                   class="form-label">
                                                توضیحات تأیید (اختیاری)
                                            </label>

                                            <textarea id="approval_explanation"
                                                      name="explanation"
                                                      class="form-control"
                                                      rows="3">{{ old('explanation') }}</textarea>

                                            @error('explanation')
                                            <div class="text-danger small mt-1">
                                                {{ $message }}
                                            </div>
                                            @enderror
                                        </div>

                                        @error('lock_version')
                                        <div class="alert alert-danger" role="alert">
                                            {{ $message }}
                                        </div>
                                        @enderror

                                        <button type="submit"
                                                class="btn btn-success">
                                            تأیید و انتشار مقاله
                                        </button>
                                    </form>
                                </div>
                            @endcan

                            @can('returnForCorrection', $article)
                                <div class="border rounded p-3 mt-3">
                                    <h3 class="h5">بازگرداندن مقاله برای اصلاح</h3>

                                    <p class="text-muted">
                                        مقاله به وضعیت پیش‌نویس برمی‌گردد و نویسنده
                                        می‌تواند اصلاحات لازم را انجام دهد.
                                    </p>

                                    <form action="{{ route('article.return', $article) }}"
                                          method="POST">
                                        @csrf

                                        <input type="hidden"
                                               name="lock_version"
                                               value="{{ $article->lock_version }}">

                                        <div class="mb-3">
                                            <label for="return_explanation"
                                                   class="form-label">
                                                دلیل بازگرداندن مقاله (الزامی)
                                            </label>

                                            <textarea id="return_explanation"
                                                      name="explanation"
                                                      class="form-control @error('explanation') is-invalid @enderror"
                                                      rows="3"
                                                      required
                                                      maxlength="2000">{{ old('explanation') }}</textarea>

                                            @error('explanation')
                                            <div class="invalid-feedback">
                                                {{ $message }}
                                            </div>
                                            @enderror
                                        </div>

                                        @error('lock_version')
                                        <div class="alert alert-danger" role="alert">
                                            {{ $message }}
                                        </div>
                                        @enderror

                                        <button type="submit"
                                                class="btn btn-warning">
                                            بازگرداندن برای اصلاح
                                        </button>
                                    </form>
                                </div>
                            @endcan

                            <hr>

                            <h3 class="h5">متن مقاله</h3>

                            <div class="article-body border rounded p-3">
                                {{ $article->body ?: 'متنی ثبت نشده است.' }}
                            </div>

                        </div>
                    </div>

                    <div class="card border rounded-3">
                        <div class="card-body p-3">
                            <h3 class="h5 mb-3">تاریخچه رویدادها</h3>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-dark">
                                    <tr>
                                        <th>رویداد</th>
                                        <th>انجام‌دهنده</th>
                                        <th>توضیحات</th>
                                        <th>تاریخ</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($events as $event)
                                        <tr>
                                            <td>{{ $event->event_type }}</td>
                                            <td>{{ $event->actor?->name ?? 'نامشخص' }}</td>
                                            <td>{{ $event->explanation ?: '—' }}</td>
                                            <td>
                                                {{ $event->created_at?->format('Y/m/d H:i') ?? '—' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center py-4">
                                                رویدادی ثبت نشده است.
                                            </td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if($events->hasPages())
                                <div class="mt-3">
                                    {{ $events->links('pagination::bootstrap-5') }}
                                </div>
                            @endif

                        </div>
                    </div>

                </div>
            </section>
        </main>
    @endsection
