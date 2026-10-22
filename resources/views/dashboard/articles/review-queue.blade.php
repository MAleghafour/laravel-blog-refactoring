
@extends('layouts.dashboard.master')

@section('content')
    <main>
        <section class="py-4">
            <div class="container">

                <div class="d-sm-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-2 mb-sm-0">
                        صف بررسی مقالات
                        <span class="badge bg-warning text-dark">
                            {{ $articles->total() }}
                        </span>
                    </h1>

                    <a href="{{ route('article.index') }}"
                       class="btn btn-sm btn-outline-secondary">
                        بازگشت به مقالات
                    </a>
                </div>

                <div class="card border rounded-3">
                    <div class="card-body p-3">
                        <div class="table-responsive">
                            <table class="table align-middle table-hover mb-0">
                                <thead class="table-dark">
                                <tr>
                                    <th>عنوان مقاله</th>
                                    <th>نویسنده</th>
                                    <th>دسته‌بندی</th>
                                    <th>تاریخ ارسال برای بررسی</th>
                                    <th>عملیات</th>
                                </tr>
                                </thead>

                                <tbody>
                                @forelse($articles as $article)
                                    <tr>
                                        <td>
                                            {{ $article->title ?: 'بدون عنوان' }}
                                        </td>

                                        <td>
                                            {{ $article->author?->name ?? 'نامشخص' }}
                                        </td>

                                        <td>
                                            {{ $article->category?->name ?? 'بدون دسته‌بندی' }}
                                        </td>

                                        <td>
                                            {{ $article->submitted_for_review_at?->format('Y/m/d H:i') ?? '—' }}
                                        </td>

                                        <td>
                                            <a href="{{ route('article.show', $article) }}"
                                               class="btn btn-sm btn-outline-primary">
                                                مشاهده و بررسی
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            مقاله‌ای در انتظار بررسی وجود ندارد.
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if($articles->hasPages())
                            <div class="mt-4">
                                {{ $articles->links('pagination::bootstrap-5') }}
                            </div>
                        @endif

                    </div>
                </div>

            </div>
        </section>
    </main>
@endsection
