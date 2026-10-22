@extends('layouts.dashboard.master')

@section('content')
    <main>
        <section class="py-4">
            <div class="container">

                <div class="d-sm-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-2 mb-sm-0">
                        مقالات فعال
                        <span class="badge bg-primary bg-opacity-10 text-primary">
                        {{ $articles->total() }}
                    </span>
                    </h1>

                    @can('create', \App\Models\dashboard\Article::class)
                        <a href="{{ route('article.create') }}"
                           class="btn btn-sm btn-primary">
                            ثبت مقاله جدید
                        </a>
                    @endcan
                </div>

                <ul class="nav nav-tabs mb-4">
                    <li class="nav-item">
                        <a class="nav-link active"
                           aria-current="page"
                           href="{{ route('article.index') }}">
                            مقالات فعال
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link"
                           href="{{ route('article.trash') }}">
                            سطل زباله
                        </a>
                    </li>
                </ul>

                <div class="card border rounded-3">
                    <div class="card-body p-3">

                        <div class="table-responsive">
                            <table class="table align-middle table-hover mb-0">
                                <thead class="table-dark">
                                <tr>
                                    <th>عنوان مقاله</th>
                                    <th>نویسنده</th>
                                    <th>دسته‌بندی</th>
                                    <th>وضعیت</th>
                                    <th>تاریخ ایجاد</th>
                                    <th>عملیات</th>
                                </tr>
                                </thead>

                                <tbody>
                                @forelse($articles as $article)
                                    <tr>
                                        <td>
                                            <a href="{{ route('article.show', $article) }}">
                                                {{ $article->title ?: 'بدون عنوان' }}
                                            </a>
                                        </td>

                                        <td>
                                            {{ $article->author?->name ?? 'نامشخص' }}
                                        </td>

                                        <td>
                                            {{ $article->category?->name ?? 'بدون دسته‌بندی' }}
                                        </td>

                                        <td>
                                            @switch($article->status)
                                                @case('draft')
                                                    <span class="badge bg-secondary">
                                                        پیش‌نویس
                                                    </span>
                                                    @break

                                                @case('pending')
                                                    <span class="badge bg-warning text-dark">
                                                        در انتظار بررسی
                                                    </span>
                                                    @break

                                                @case('published')
                                                    <span class="badge bg-success">
                                                        منتشرشده
                                                    </span>
                                                    @break

                                                @default
                                                    <span class="badge bg-light text-dark">
                                                        نامشخص
                                                    </span>
                                            @endswitch
                                        </td>

                                        <td>
                                            {{ $article->created_at?->format('Y/m/d') ?? '—' }}
                                        </td>

                                        <td>
                                            <div class="d-flex gap-2">
                                                @can('view', $article)
                                                    <a href="{{ route('article.show', $article) }}"
                                                       class="btn btn-sm btn-outline-primary">
                                                        مشاهده
                                                    </a>
                                                @endcan

                                                @can('update', $article)
                                                    <a href="{{ route('article.edit', $article) }}"
                                                       class="btn btn-sm btn-outline-secondary">
                                                        ویرایش
                                                    </a>
                                                @endcan

                                                    @can('delete', $article)
                                                        <form action="{{ route('article.destroy', $article) }}"
                                                              method="POST"
                                                              class="d-inline"
                                                              onsubmit="return confirm('آیا از انتقال این مقاله به سطل زباله مطمئن هستید؟');">

                                                            @csrf
                                                            @method('DELETE')

                                                            <button type="submit"
                                                                    class="btn btn-sm btn-outline-danger">
                                                                حذف
                                                            </button>
                                                        </form>
                                                    @endcan

                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            هنوز مقاله‌ای ثبت نشده است.
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
