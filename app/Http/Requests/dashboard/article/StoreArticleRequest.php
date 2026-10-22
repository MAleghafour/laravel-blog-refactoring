<?php

namespace App\Http\Requests\dashboard\article;

use App\Models\dashboard\Article;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreArticleRequest extends FormRequest
{
    /**
     * Allow authenticated Admin and Author users to create articles.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Article::class) ?? false;
    }

    /**
     * Normalize the slug before applying validation rules.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('slug')) {
            $this->merge([
                'slug' => Str::slug($this->input('slug')),
            ]);
        }
    }

    /**
     * Validate article fields for draft creation.
     *
     * Review and publication requirements are enforced
     * separately by their workflow actions.
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('articles', 'slug'),
            ],
            'body' => ['nullable', 'string'],
            'brief' => ['nullable', 'string', 'max:260'],
            'tags' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'thumbnail' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png',
                'max:2048',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.required' => 'نامک مقاله الزامی است.',
            'slug.unique' => 'این نامک قبلاً ثبت شده است.',
            'title.max' => 'عنوان مقاله نباید بیشتر از ۲۵۵ کاراکتر باشد.',
            'brief.max' => 'توضیح کوتاه نباید بیشتر از ۲۶۰ کاراکتر باشد.',
            'category_id.exists' => 'دسته‌بندی انتخاب‌شده معتبر نیست.',
            'thumbnail.image' => 'فایل انتخاب‌شده باید تصویر باشد.',
            'thumbnail.mimes' => 'فرمت تصویر باید JPG یا PNG باشد.',
            'thumbnail.max' => 'حجم تصویر نباید بیشتر از ۲ مگابایت باشد.',
        ];
    }
}
