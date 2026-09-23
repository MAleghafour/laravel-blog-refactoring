<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'ایمیل معتبر خود را وارد نمایید!',
            'email.email' => 'ایمیل معتبر وارد نمایید!',
            'password.required' => 'کلمه عبور خود را وارد نمایید!',
        ];
    }
}
