<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'min:5', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
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
            // 'g-recaptcha-response' => [
            //     'required',
            //     new GoogleCaptchav3('submitRegister', .5),
            // ],
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'نام و نام خانوادگی خود را وارد نمایید!',
            'name.max' => 'نام و نام خانوادگی باید حدااکثر 225 کارکتر باشد!',
            'name.min' => 'نام و نام خانوادگی باید حدااقل 5 کارکتر باشد!',
            'email.required' => 'ایمیل معتبر خود را وارد نمایید!',
            'email.max' => 'ایمیل مورد نظر حداکثر باید ۲۵۵ کارکتر باشد!',
            'email.email' => 'ایمیل معتبر وارد نمایید!',
            'email.unique' => 'قبلا کاربری با این ایمیل ثبت نام کرده است!',
            'password.required' => 'کلمه عبور خود را وارد نمایید!',
            'password.min' => 'کلمه عبور باید حداقل 8 کارکتر باشد!',
            'password.max' => 'کلمه عبور باید حداکثر 12 کارکتر باشد!',
            'password.confirmed' => 'کلمه عبور با تکرار آن برابر نیست!',
            'password.mixed' => 'کلمه عبور باید شامل اعداد کارکتر کوچک و بزرگ و کارکترهای ويژه (!@#$%^&*) باشد!',
            'g-recaptcha-response.required' => 'اعتبار سنجی گوگل ریکپچا الزامی است!',
        ];
    }
}
