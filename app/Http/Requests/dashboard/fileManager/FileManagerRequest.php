<?php

namespace App\Http\Requests\dashboard\fileManager;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;


class FileManagerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
            'files'=> 'required|array',
            'files.*' => 'file|max:2048|mimes:jpeg,jpg,png,gif,pdf,mp4,webm,avi,mov',
        ];
    }

    public function messages(): array
    {
        return [
            'files.required' => 'فایل مورد نظر خود را انتخاب نمایید',
            'files.*.max' => 'حجم تصویر نباید بیشتر از 2 مگابایت باشد.',
            'files.*.mimes' => 'فایل مورد نظر باید شامل فرمت های ( jpeg,jpg,png,gif,zip,rar,pdf,txt,mp4,webm,avi,mov ) باشد',
            //'files.*.file' => 'فایل مورد نظر باید شامل فرمت های ( jpeg,jpg,png,gif ) باشد',
            'files.array' => 'فایل معتبر نیست!',
        ];

    }


}
