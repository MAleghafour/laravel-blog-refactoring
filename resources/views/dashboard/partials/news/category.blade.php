@php
    $categoryIcons = config('category.icons', []);
    $editCategoryId = session('category_form_edit_id');
    $isEditMode = $editCategoryId !== null;
    $formAction = $isEditMode
        ? route('category.update', $editCategoryId)
        : route('category.index');
    $defaultIcon = array_key_first($categoryIcons);
    $selectedIcon = old('icon', $defaultIcon);
@endphp

<section class="py-4">
        <div class="container">
            <h1 id="category-form-title" class="mb-4 h3">
                {{$isEditMode ? 'ویرایش دسته‌بندی' : 'افزودن دسته‌بندی'}}
            </h1>
            <div class="row pb-4 bg-light p-3 mb-4 rounded">
                <form id="category-form"
                    action="{{$formAction}}"
                    method="post"
                    data-mode="{{$isEditMode ? 'edit' : 'create'}}"
                    data-create-url="{{route('category.index')}}"
                    data-default-icon="{{$defaultIcon}}">
                    @csrf
                    <input id="category-form-method"
                        type="hidden"
                        name="_method"
                        value="PUT"
                        @disabled(!$isEditMode)>
                    <input id="category-lock-version"
                        type="hidden"
                        name="lock_version" value="{{old('lock_version', '')}}"
                        @disabled(!$isEditMode)>
                 <div class="row">
                     <div class="col-sm-12 col-md-4">
                         <label for="category-name" class="form-label">نام  </label>
                         <input id="category-name" name="name" type="text" class="form-control" placeholder="نام دست بندی ..." value="{{ old('name') }}">
                         @error('name')
                         <small class="category-form-error mt-2 d-inline-block text-danger">{{$message}}</small>
                         @enderror
                     </div>
                     <div class="col-sm-12 col-md-4">
                         <label for="category-slug" class="form-label">نامک</label>
                         <input id="category-slug" name="slug" type="text" class="form-control" placeholder="  نامک ..." value="{{ old('slug') }}" data-has-old-value="{{ session()->hasOldInput('slug') ? 'true' : 'false' }}">
                         @error('slug')
                         <small class="category-form-error mt-2 d-inline-block text-danger">{{$message}}</small>
                         @enderror
                     </div>
                     <div class="col-sm-12 col-md-4 ">
                         <label for="category-description" class="form-label">توضیحات</label>
                         <textarea id="category-description" class="form-control" name="description">{{ old('description') }}</textarea>
                         @error('description')
                         <small class="category-form-error mt-2 d-inline-block text-danger">{{$message}}</small>
                         @enderror
                     </div>
                     <div class="col-sm-12 col-md-4 mt-3">
                         <label for="category-icon" class="form-label">آیکون</label>
                         <select id="category-icon" class="form-control" name="icon">
                             @foreach($categoryIcons as $icon => $label)
                             <option value="{{$icon}}" {{$selectedIcon === $icon ? 'selected': ''}}>{{$icon}} {{$label}}</option>
                             @endforeach
                         </select>
                         @error('icon')
                         <small class="category-form-error mt-2 d-inline-block text-danger">{{$message}}</small>
                         @enderror
                     </div>
                     <div class="col-sm-12 col-md-4 d-flex align-items-center gap-2 mt-5">
                         <button id="category-form-submit" class="btn btn-success flex-grow-1 m-0" type="submit">
                             {{$isEditMode ? 'ذخیره تغییرات' : 'ثبت'}}
                         </button>
                         <button id="category-form-cancel"
                             class="btn btn-secondary m-0"
                             type="button"
                             @if(!$isEditMode) hidden @endif>
                             انصراف
                         </button>
                     </div>
                 </div>
                </form>
            </div>
            <div class="row pb-4">
                <div class="col-12">
                    <!-- Title -->
                    <div class="d-sm-flex justify-content-sm-between align-items-center">
                        <h1 class="mb-2 mb-sm-0 h3">دسته بندی ها <span class="badge bg-primary bg-opacity-10 text-primary">{{$categoryCount}}</span></h1>
                    </div>
                    @error('lock_version')
                    <div class="alert alert-danger mt-3" role="alert">
                        {{$message}}
                    </div>
                    @enderror
                </div>
            </div>
            <div class="row g-4">
                @forelse($categories as $category)
                <div class="col-md-6 col-xl-4">
                    <!-- Category item START -->
                    <div class="card border h-100">
                        <!-- Card header -->
                        <div class="card-header border-bottom p-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="icon-lg shadow bg-body rounded-circle">{{$category->icon}}</div>
                                <h4 class="mb-0 ms-3 flex-grow-1">{{$category->name}}</h4>
                                <div class="d-flex justify-content-between align-items-center">
                                    <button type="button"
                                        class="category-edit-trigger border-0 bg-transparent text-success mb-0 me-2"
                                        data-update-url="{{route('category.update',$category->id)}}"
                                        data-category-id="{{$category->id}}"
                                        data-category-name="{{$category->name}}"
                                        data-category-slug="{{$category->slug}}"
                                        data-category-description="{{$category->description}}"
                                        data-category-icon="{{$category->icon}}"
                                        data-lock-version="{{$category->lock_version}}"
                                        aria-controls="category-form"
                                        aria-label="ویرایش دسته‌بندی {{$category->name}}">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button"
                                            class="category-delete-trigger border-0 bg-transparent"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteCategoryModal"
                                            data-delete-url="{{route('category.destroy',$category->id)}}"
                                            data-category-id="{{$category->id}}"
                                            data-category-name="{{$category->name}}"
                                            data-lock-version="{{$category->lock_version}}"
                                            aria-label="حذف دسته‌بندی {{$category->name}}">
                                        <i class="fas fa-times-circle text-danger"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <!-- Card body START -->
                        <div class="card-body p-3">
                         <p>{{$category->description}}</p>
                            <!-- Followers and Post -->
                                <!-- Total post -->
                                <div class="d-flex justify-content-between">
                                    <h6 class="fw-light">کل اخبار</h6>
                                    <h5 class=" px-2 py-1 bg-success rounded text-white">{{$category->articles_count}}</h5>
                            </div>
                        </div>
                        <!-- Card body END -->

                        <!-- Card footer -->
                        <div class="card-footer border-top text-center p-3">
                            <a href="#" class="btn btn-primary-soft w-100 mb-0">مشاهده اخبار</a>
                        </div>
                    </div>
                    <!-- Category item END -->
                </div>
                @empty
                    <div class="col-12">
                        <div class="alert alert-info text-end mb-0" role="alert">
                            تا این لحظه دسته بندی ثبت نشده است!
                        </div>
                    </div>
                @endforelse
            </div>
            <div class="pagination d-flex justify-content-center align-items-center mt-4">
                {{$categories->links('pagination::bootstrap-5')}}
            </div>

            <div class="modal fade"
                id="deleteCategoryModal"
                tabindex="-1"
                aria-labelledby="deleteCategoryModalTitle"
                aria-hidden="true"
                dir="rtl">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content text-start">
                        <div class="modal-header text-start">
                            <h5 class="modal-title flex-grow-1 text-start" id="deleteCategoryModalTitle">
                                تأیید حذف دسته‌بندی
                            </h5>
                            <button type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                                aria-label="بستن">
                            </button>
                        </div>

                        <form id="delete-category-form" method="post">
                            @csrf
                            @method('DELETE')
                            <input id="delete-category-lock-version"
                                type="hidden"
                                name="lock_version" value="">

                            <div class="modal-body text-start">
                                <p class="mb-0">
                                    آیا از حذف دسته‌بندی
                                    «<strong id="delete-category-name"></strong>»
                                    مطمئن هستید؟
                                </p>
                            </div>

                            <div class="modal-footer justify-content-start">
                                <button type="button"
                                    class="btn btn-secondary"
                                    data-bs-dismiss="modal">
                                    انصراف
                                </button>
                                <button id="delete-category-submit"
                                    type="submit"
                                    class="btn btn-danger"
                                    disabled>
                                    حذف دسته‌بندی
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

</section>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('category-form');

        if (!form) {
            return;
        }

        const nameInput = form.querySelector('#category-name');
        const slugInput = form.querySelector('#category-slug');
        const descriptionInput = form.querySelector('#category-description');
        const iconInput = form.querySelector('#category-icon');
        const methodInput = form.querySelector('#category-form-method');
        const lockVersionInput = form.querySelector('#category-lock-version');
        const title = document.getElementById('category-form-title');
        const submitButton = document.getElementById('category-form-submit');
        const cancelButton = document.getElementById('category-form-cancel');

        if (
            !nameInput
            || !slugInput
            || !descriptionInput
            || !iconInput
            || !methodInput
            || !lockVersionInput
            || !title
            || !submitButton
            || !cancelButton
        ) {
            return;
        }

        let slugWasEdited = slugInput.dataset.hasOldValue === 'true';

        if (form.dataset.mode === 'edit') {
            slugWasEdited = true;
        }

        const suggestSlug = (name) => {
            const normalized = name
                .normalize('NFKD')
                .replace(/[\u0300-\u036f]/g, '');

            // Avoid lossy transliteration for unsupported scripts.
            if (!/^[A-Za-z0-9 _-]*$/.test(normalized)) {
                return '';
            }

            return normalized
                .trim()
                .toLowerCase()
                .replace(/[\s_-]+/g, '-')
                .replace(/^-+|-+$/g, '');
        };

        const updateSuggestion = () => {
            if (form.dataset.mode !== 'create') {
                return;
            }

            if (!slugWasEdited) {
                slugInput.value = suggestSlug(nameInput.value);
            }
        };

        const hideValidationErrors = () => {
            form.querySelectorAll('.category-form-error').forEach((error) => {
                error.hidden = true;
            });
        };

        const setCreateMode = () => {
            form.action = form.dataset.createUrl ?? '';
            form.dataset.mode = 'create';
            form.dataset.categoryId = '';
            methodInput.disabled = true;
            lockVersionInput.disabled = true;
            lockVersionInput.value = '';
            nameInput.value = '';
            slugInput.value = '';
            descriptionInput.value = '';
            iconInput.value = form.dataset.defaultIcon ?? '';
            title.textContent = 'افزودن دسته‌بندی';
            submitButton.textContent = 'ثبت';
            cancelButton.hidden = true;
            slugWasEdited = false;
            hideValidationErrors();
            updateSuggestion();
            nameInput.focus();
        };

        const setEditMode = (trigger) => {
            const updateUrl = trigger.dataset.updateUrl ?? '';

            if (updateUrl === '') {
                return;
            }

            form.action = updateUrl;
            form.dataset.mode = 'edit';
            form.dataset.categoryId = trigger.dataset.categoryId ?? '';
            methodInput.disabled = false;
            lockVersionInput.disabled = false;
            lockVersionInput.value = trigger.dataset.lockVersion ?? '';
            nameInput.value = trigger.dataset.categoryName ?? '';
            slugInput.value = trigger.dataset.categorySlug ?? '';
            descriptionInput.value = trigger.dataset.categoryDescription ?? '';
            iconInput.value = trigger.dataset.categoryIcon ?? '';
            title.textContent = 'ویرایش دسته‌بندی';
            submitButton.textContent = 'ذخیره تغییرات';
            cancelButton.hidden = false;
            slugWasEdited = true;
            hideValidationErrors();
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            nameInput.focus();
        };

        document.querySelectorAll('.category-edit-trigger').forEach((trigger) => {
            trigger.addEventListener('click', () => setEditMode(trigger));
        });

        nameInput.addEventListener('input', updateSuggestion);
        slugInput.addEventListener('input', () => {
            slugWasEdited = true;
        });
        cancelButton.addEventListener('click', setCreateMode);

        updateSuggestion();
    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('deleteCategoryModal');
        const form = document.getElementById('delete-category-form');
        const categoryName = document.getElementById('delete-category-name');
        const lockVersion = document.getElementById('delete-category-lock-version');
        const submitButton = document.getElementById('delete-category-submit');

        if (!modal || !form || !categoryName || !lockVersion || !submitButton) {
            return;
        }

        let isSubmitting = false;

        modal.addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;

            if (!(trigger instanceof HTMLElement)) {
                return;
            }

            const deleteUrl = trigger.dataset.deleteUrl ?? '';

            categoryName.textContent = trigger.dataset.categoryName ?? '';
            lockVersion.value = trigger.dataset.lockVersion ?? '';
            form.dataset.categoryId = trigger.dataset.categoryId ?? '';
            isSubmitting = false;

            if (deleteUrl === '' || lockVersion.value === '') {
                form.removeAttribute('action');
                submitButton.disabled = true;
                return;
            }

            form.action = deleteUrl;
            submitButton.disabled = false;
        });

        form.addEventListener('submit', (event) => {
            if (isSubmitting || !form.hasAttribute('action')) {
                event.preventDefault();
                return;
            }

            isSubmitting = true;
            submitButton.disabled = true;
        });

        modal.addEventListener('hidden.bs.modal', () => {
            form.removeAttribute('action');
            form.dataset.categoryId = '';
            categoryName.textContent = '';
            lockVersion.value = '';
            submitButton.disabled = true;
            isSubmitting = false;
        });
    });
</script>
