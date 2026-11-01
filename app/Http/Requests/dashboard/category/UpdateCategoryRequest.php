<?php

namespace App\Http\Requests\dashboard\category;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCategoryRequest extends FormRequest
{
    private const ALLOWED_INPUTS = [
        'name',
        'slug',
        'description',
        'icon',
        'lock_version',
        '_token',
        '_method',
    ];

    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        $categoryId = (int) $this->route('id');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->ignore($categoryId),
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'slug')->ignore($categoryId),
                Rule::unique('category_slug_histories', 'slug'),
            ],
            'description' => ['nullable', 'string'],
            'icon' => [
                'required',
                'string',
                Rule::in(array_keys(config('category.icons', []))),
            ],
            'lock_version' => ['required', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $extraFields = array_diff(
                    array_keys($this->all()),
                    self::ALLOWED_INPUTS
                );

                foreach ($extraFields as $field) {
                    $validator->errors()->add(
                        $field,
                        'ارسال فیلد «'.$field.'» مجاز نیست.'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'نام دسته‌بندی الزامی است.',
            'name.string' => 'نام دسته‌بندی باید متن باشد.',
            'name.max' => 'نام دسته‌بندی نباید بیشتر از ۲۵۵ کاراکتر باشد.',
            'name.unique' => 'این نام قبلاً ثبت شده است.',
            'slug.required' => 'نامک دسته‌بندی الزامی است.',
            'slug.string' => 'نامک دسته‌بندی باید متن باشد.',
            'slug.max' => 'نامک دسته‌بندی نباید بیشتر از ۲۵۵ کاراکتر باشد.',
            'slug.unique' => 'این نامک قبلاً استفاده شده است.',
            'description.string' => 'توضیحات دسته‌بندی باید متن باشد.',
            'icon.required' => 'انتخاب آیکون دسته‌بندی الزامی است.',
            'icon.string' => 'آیکون دسته‌بندی معتبر نیست.',
            'icon.in' => 'آیکون انتخاب‌شده معتبر نیست.',
            'lock_version.required' => 'نسخه دسته‌بندی مشخص نیست. صفحه را دوباره بارگذاری کنید.',
            'lock_version.integer' => 'نسخه دسته‌بندی معتبر نیست.',
            'lock_version.min' => 'نسخه دسته‌بندی معتبر نیست.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        $this->session()->flash(
            'category_form_edit_id',
            (int) $this->route('id')
        );

        return route('category.index');
    }
}
