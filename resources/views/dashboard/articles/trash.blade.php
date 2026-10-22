
@extends('layouts.dashboard.master')

@section('content')
    <main>
        <section class="py-4">
            <div class="container">

                <div class="d-sm-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-2 mb-sm-0">
                        سطل زباله مقالات
                        <span class="badge bg-secondary">
                        {{ $articles->total() }}
                    </span>
                    </h1>
                </div>

                <ul class="nav nav-tabs mb-4">
                    <li class="nav-item">
                        <a class="nav-link"
                           href="{{ route('article.index') }}">
                            مقالات فعال
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link active"
                           aria-current="page"
                           href="{{ route('article.trash') }}">
                            سطل زباله
                        </a>
                    </li>
                </ul>

                @error('confirmation')
                <div class="alert alert-danger" role="alert">
                    عبارت تأیید حذف دائمی معتبر نیست.
                    لطفاً عبارت «حذف دائمی» را دقیق وارد کنید.
                </div>
                @enderror

                <div class="card border rounded-3">
                    <div class="card-body p-3">

                        <div class="table-responsive">
                            <table class="table align-middle table-hover mb-0">
                                <thead class="table-dark">
                                <tr>
                                    <th>عنوان مقاله</th>
                                    <th>نویسنده</th>
                                    <th>دسته‌بندی</th>
                                    <th>وضعیت قبلی</th>
                                    <th>تاریخ حذف</th>
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
                                            {{ $article->deleted_at?->format('Y/m/d H:i') ?? '—' }}
                                        </td>

                                        <td>

                                            @can('restore', $article)
                                                <form action="{{ route('article.restore', $article) }}"
                                                      method="POST"
                                                      class="d-inline"
                                                      onsubmit="return confirm('آیا از بازیابی این مقاله مطمئن هستید؟');">

                                                    @csrf

                                                    <button type="submit"
                                                            class="btn btn-sm btn-outline-success">
                                                        بازیابی
                                                    </button>
                                                </form>
                                            @endcan

                                            @can('forceDelete', $article)
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-danger"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#forceDeleteModal-{{ $article->id }}">
                                                        حذف دائمی
                                                    </button>
                                                @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            سطل زباله خالی است.
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

                @foreach($articles as $article)
                    @can('forceDelete', $article)

                        <div class="modal fade"
                             id="forceDeleteModal-{{ $article->id }}"
                             tabindex="-1"
                             aria-labelledby="forceDeleteTitle-{{ $article->id }}"
                             aria-hidden="true">

                            <div class="modal-dialog">
                                <div class="modal-content">

                                    <div class="modal-header">
                                        <h5 class="modal-title"
                                            id="forceDeleteTitle-{{ $article->id }}">
                                            تأیید حذف دائمی مقاله
                                        </h5>
                                        <button type="button"
                                                class="btn-close"
                                                data-bs-dismiss="modal"
                                                aria-label="بستن">
                                        </button>
                                    </div>

                                    <form action="{{ route('article.force-delete', $article) }}"
                                          method="POST">
                                        @csrf
                                        @method('DELETE')

                                        <div class="modal-body">
                                            <div class="alert alert-danger">
                                                این عملیات غیرقابل بازگشت است.
                                                مقاله، تاریخچه رویدادها و تصاویر اختصاصی
                                                آن به‌صورت دائمی حذف خواهند شد.
                                            </div>

                                            <p>{{ $article->title ?: 'بدون عنوان' }}</p>

                                            <label for="confirmation-{{ $article->id }}"
                                                   class="form-label">
                                                برای تأیید، عبارت «حذف دائمی» را وارد کنید:
                                            </label>

                                            <input type="text"
                                                   id="confirmation-{{ $article->id }}"
                                                   name="confirmation"
                                                   class="form-control"
                                                   required
                                                   autocomplete="off">
                                        </div>

                                        <div class="modal-footer">
                                            <button type="button"
                                                    class="btn btn-secondary"
                                                    data-bs-dismiss="modal">
                                                انصراف
                                            </button>

                                            <button type="submit"
                                                    class="btn btn-danger">
                                                حذف دائمی مقاله
                                            </button>
                                        </div>
                                    </form>

                                </div>
                            </div>
                        </div>
                    @endcan
                @endforeach

            </div>
        </section>
    </main>
@endsection
