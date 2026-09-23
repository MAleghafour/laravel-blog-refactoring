<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->max(12)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'توکن معتبر نمی باشد!',
            'email.required' => 'ایمیل معتبر خود را وارد نمایید!',
            'email.email' => 'ایمیل معتبر خود را وارد نمایید!',
            'password.required' => 'کلمه عبور خود را وارد نمایید!',
            'password.min' => 'کلمه عبور باید حداقل 8 کارکتر باشد!',
            'password.max' => 'کلمه عبور باید حداکثر 12 کارکتر باشد!',
            'password.confirmed' => 'کلمه عبور با تکرار آن برابر نیست!',
            'password.mixed' => 'کلمه عبور باید شامل اعداد کارکتر کوچک و بزرگ و کارکترهای ويژه (!@#$%^&*) باشد!',
        ];
    }
}
