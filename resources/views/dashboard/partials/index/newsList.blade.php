
<div class="col-12">
    <div class="card border bg-transparent rounded-3">
        <div class="card-header bg-transparent border-bottom p-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">آخرین مقالات</h5>

                <a href="{{ route('article.create') }}"
                   class="btn btn-sm btn-primary">
                    مقاله جدید
                </a>
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle table-hover">
                    <thead class="table-dark">
                    <tr>
                        <th scope="col">عنوان</th>
                        <th scope="col">نویسنده</th>
                        <th scope="col">تاریخ ایجاد</th>
                        <th scope="col">دسته‌بندی</th>
                        <th scope="col">وضعیت</th>
                        <th scope="col">عملیات</th>
                    </tr>
                    </thead>

                    <tbody>
                    @forelse ($latestArticles as $article)
                        <tr>
                            <td>
                                <a href="{{ route('article.show', $article) }}">
                                    {{ $article->title ?: 'بدون عنوان' }}
                                </a>
                            </td>

                            <td>{{ $article->author?->name ?? 'نامشخص' }}</td>

                            <td>
                                {{ $article->created_at?->format('Y/m/d') }}
                            </td>

                            <td>
                                {{ $article->category?->name ?? 'بدون دسته‌بندی' }}
                            </td>

                            <td>
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
                                @endswitch
                            </td>

                            <td>
                                <a href="{{ route('article.show', $article) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    مشاهده
                                </a>

                                @can('update', $article)
                                    <a href="{{ route('article.edit', $article) }}"
                                       class="btn btn-sm btn-outline-secondary">
                                        ویرایش
                                    </a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                هنوز مقاله‌ای ثبت نشده است.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="text-center mt-3">
                <a href="{{ route('article.index') }}"
                   class="btn btn-outline-primary">
                    مشاهده همه مقالات
                </a>
            </div>
        </div>
    </div>
</div>
