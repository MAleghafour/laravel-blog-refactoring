<?php

namespace App\Http\Requests\dashboard\category;

use Illuminate\Foundation\Http\FormRequest;

class DestroyCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'lock_version.required' => 'نسخه دسته‌بندی مشخص نیست. صفحه را دوباره بارگذاری کنید.',
            'lock_version.integer' => 'نسخه دسته‌بندی معتبر نیست.',
            'lock_version.min' => 'نسخه دسته‌بندی معتبر نیست.',
        ];
    }
}
