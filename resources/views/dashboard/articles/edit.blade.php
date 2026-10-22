@extends('layouts.dashboard.master')

@section('content')
<section class="py-4">
    <div class="container">
        <div class="row pb-4">
            <div class="col-12">
                <!-- Title -->
                <h1 class="mb-0 h3">ویرایش مقاله</h1>
            </div>
        </div>
        @if ($correctionFeedback)
            <div class="alert alert-warning mb-4" role="alert">
                <h6 class="alert-heading">مقاله برای اصلاح بازگردانده شده است</h6>
                <p class="mb-0">
                    {{ $correctionFeedback->explanation }}
                </p>
            </div>
        @endif
        <div class="row">
            <form
                class="row"
                action="{{ route('article.update', $article) }}"
                method="POST"
                enctype="multipart/form-data"
            >
                @csrf
                @method('PUT')

                <input
                    type="hidden"
                    name="lock_version"
                    value="{{ $article->lock_version }}"
                >
                <div class="col-sm-12 col-md-8">
                    <!-- Chart START -->
                    <div class="card border">
                        <!-- Card body -->
                        <div class="card-body">
                            <!-- Form START -->

                            <!-- Main form -->
                            <div class="row">
                                <div class="col-12">
                                    <!-- Post name -->
                                    <div class="mb-3">
                                        <label class="form-label">عنوان</label>
                                        <input
                                            id="con-name"
                                            name="title"
                                            type="text"
                                            class="form-control @error('title') is-invalid @enderror"
                                            placeholder="عنوان مقاله"
                                            value="{{ old('title', $article->title) }}"
                                            maxlength="255"
                                        >
                                        @error('title')
                                        <small class="mt-3 text-danger">{{$message}}</small>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Main toolbar -->
                                <div class="col-md-12">
                                    <!-- Subject -->
                                    <div class="mb-3">
                                        <label class="form-label">متن خبر</label>
                                        <textarea
                                            id="newsBody"
                                            class="form-control @error('body') is-invalid @enderror"
                                            name="body"
                                            style="min-height: 300px"
                                        >{{ old('body', $article->body) }}</textarea>                                       @error('body')
                                        <small class="mt-3 text-danger">{{$message}}</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-lg-12">
                                    <!-- slug -->
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <label class="form-label">نامک</label>
                                            <i class="fa fa-question-circle" data-bs-toggle="tooltip" data-bs-placement="top" title=" ( برای seo بهتر است عنوانی مناسب شامل کلمات کلیدی این خبر برای این مطلب انتخاب کرده و هر کلمه را با - از هم جدا نمایید )">
                                            </i>
                                        </div>
                                        <input
                                            id="article-slug"
                                            type="text"
                                            name="slug"
                                            class="form-control @error('slug') is-invalid @enderror"
                                            placeholder="نامک مقاله"
                                            value="{{ old('slug', $article->slug) }}"
                                            maxlength="255"
                                            required
                                        >
                                        @error('slug')
                                        <small class="mt-3 text-danger">{{$message}}</small>
                                        @enderror
                                    </div>
                                </div>
                                <!-- Short description -->
                                <div class="col-12">
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <label class="form-label">توضیح مختصر</label>
                                            <i class="fa fa-question-circle" data-bs-toggle="tooltip" data-bs-placement="top" title="توضیح مختصری در مورد این خبر شامل کلمات کلیدی و حداکثر شامل ۲۶۰ کارکتر بنویسید">
                                            </i>
                                        </div>
                                        <textarea
                                            id="article-brief"
                                            class="form-control @error('brief') is-invalid @enderror"
                                            name="brief"
                                            maxlength="260"
                                            style="min-height: 100px"
                                            placeholder="توضیح کوتاهی درباره مقاله بنویسید..."
                                        >{{ old('brief', $article->brief) }}</textarea>
                                        @error('brief')
                                        <small class="mt-3 text-danger">{{$message}}</small>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="col-sm-12 col-md-4">
                    <div class="card border">
                        <!-- Card body -->
                        <div class="card-body">
                            <div class="col-12">
                                <div class="mb-3">
                                    <!-- Image -->
                                    <div class="position-relative">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h6 class="my-2">آپلود تصویر شاخص</h6>
                                            <i class="fa fa-question-circle" data-bs-toggle="tooltip" data-bs-placement="top" title=" فرمت های مجاز: JPG، JPEG و PNG و ابعاد
                                                پیشنهادی ما 600px * 450px است. تصاویر بزرگتر به اندازه 4:3 برش داده می شود
                                                تا با تصاویر کوچک/پیش نمایش ما مطابقت داشته باشد.">
                                            </i>
                                        </div>
                                        <label class="w-100" style="cursor:pointer;">
                                            <div class="input-group flex-row-reverse">
                                                <input type="text" class="form-control upload-name"/>
                                                <span
                                                    class="btn btn-custom cursor-pointer upload-button">آپلود فایل</span>
                                            </div>
                                            <input
                                                id="fileInput"
                                                class="form-control stretched-link hidden-upload d-none"
                                                type="file"
                                                name="thumbnail"
                                                accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                                            >
                                            @error('thumbnail')
                                            <div class="mt-2 text-danger" role="alert">
                                                {{ $message }}
                                            </div>
                                            @enderror
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <!-- Image PreView -->
                                    @if ($article->thumbnail_medium)
                                        <div class="mb-3">
                                            <p class="small text-muted mb-2">تصویر شاخص فعلی</p>

                                            <img
                                                src="{{ asset('uploads/thumbnails/medium/' . $article->thumbnail_medium) }}"
                                                alt="تصویر شاخص فعلی مقاله"
                                                class="img-fluid rounded"
                                                style="max-height: 220px;"
                                            >
                                        </div>
                                    @endif

                                    <div class="mb-3">
                                        <img
                                            id="preview"
                                            src=""
                                            alt="پیش‌نمایش تصویر جدید"
                                            class="img-fluid rounded"
                                            style="max-height: 220px;"
                                            hidden
                                        >
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-12">
                                <!-- Tags -->
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <label class="form-label">برچسب</label>
                                        <i class="fa fa-question-circle" data-bs-toggle="tooltip" data-bs-placement="top" title="حداکثر 14 کلمه کلیدی، کلمات کلیدی باید با حروف کوچک و با کاما از هم جدا
                                                شوند. به عنوان مثال، جاوا اسکریپت، واکنش، بازاریابی">
                                        </i>
                                    </div>
                                    <textarea
                                        id="article-tags"
                                        class="form-control @error('tags') is-invalid @enderror"
                                        name="tags"
                                        rows="1"
                                        placeholder="ورزش، اقتصاد، ..."
                                    >{{ old('tags', $article->tags) }}</textarea>
                                    @error('tags')
                                    <small class="mt-3 text-danger">{{$message}}</small>
                                    @enderror
                                </div>
                            </div>


                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="article-category" class="form-label">
                                        دسته‌بندی
                                    </label>

                                    <select
                                        id="article-category"
                                        name="category_id"
                                        class="form-select @error('category_id') is-invalid @enderror"
                                    >
                                        <option
                                            value=""
                                            @selected(old('category_id', $article->category_id) === null || old('category_id', $article->category_id) === '')
                                        >
                                            بدون دسته‌بندی
                                        </option>

                                        @foreach ($categories as $category)
                                            <option
                                                value="{{ $category->id }}"
                                                @selected(old('category_id', $article->category_id) == $category->id)                                            >
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>

                                    @error('category_id')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                    @enderror

                                </div>
                            </div>

                            @if ($isAdmin)
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label for="article-status" class="form-label">
                                            وضعیت مقاله
                                        </label>

                                        <select
                                            id="article-status"
                                            name="status"
                                            class="form-select @error('status') is-invalid @enderror"
                                        >
                                            @foreach ([
                                                'draft' => 'پیش‌نویس',
                                                'pending' => 'در انتظار بازبینی',
                                                'published' => 'منتشرشده'
                                            ] as $value => $label)
                                                <option
                                                    value="{{ $value }}"
                                                    @selected(old('status', $article->status) === $value)
                                                >
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>

                                        @error('status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            @endif

                            @if ($isEditingAnotherAuthor)
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label for="article-explanation" class="form-label">
                                            دلیل ویرایش مقاله
                                        </label>

                                        <textarea
                                            id="article-explanation"
                                            name="explanation"
                                            class="form-control @error('explanation') is-invalid @enderror"
                                            rows="3"
                                            maxlength="2000"
                                            required
                                        >{{ old('explanation') }}</textarea>

                                        <div class="form-text">
                                            این توضیح در تاریخچه مقاله ثبت می‌شود.
                                        </div>

                                        @error('explanation')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            @endif

                            <!-- Create post button -->
                            <div class="col-md-12 text-start">
                                <button class="btn btn-success w-100" type="submit">
                                    ذخیره تغییرات
                                </button>
                            </div>

                        </div>
                    </div>
                </div>
            </form>
            {{--Form End--}}
        </div>
    </div>
</section>

@endsection
