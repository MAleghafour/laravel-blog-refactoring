<?php

namespace App\Http\Requests\dashboard\article;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

//use Illuminate\Validation\Rules\In;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use Illuminate\Contracts\Validation\Validator as ValidationValidator;


class StoreUpdateArticle extends FormRequest
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
            'author_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'type' => 'required|string|in:فوری,حوادث,خبری,متنی,چندرسانه ای,سایر',
            //'type'=>['require','string',new In(['خبری','حوادث'])],
            'body' => 'required|string',
            'slug' => 'required|string|unique:articles,slug',
            'brief' => 'nullable|string',
            'thumbnail' => 'nullable|image|max:2048|mimes:jpeg,jpg,png,gif',
            'tags' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'status' => 'nullable'
        ];
    }

    public function messages(): array
    {
        return [
            //'title.required' => 'عنوان را وارد نمایید! ',
            //'title.min' => 'عنوان باید شامل حداقل ۱۰ کارکتر باشد!',
            'title.required' => 'برای این مقاله عنوان انتخاب نمایید.',
            'title.max' => 'مقاله تنها باید شامل 255 کارکتر باشد.',
            'type.required' => 'نوع خبر را انتخاب نمایید.',
            'type.in' => 'نوع خبر درست نیست.',
            'body.required' => 'متن مقاله را وارد کنید.',
            'slug.required' => 'نامک ( شناسه ) خبر را وارد نمایید.',
            'slug.unique' => 'این نامک قبلا ثبت شده است نامک دیگری انتخاب نمایید.',
            'brief.required' => 'توضیح مختصری در مورد این مقاله بنویسید.',
            'thumbnail.image' => 'یک فایل تصویری انتخاب نمایید.',
            'thumbnail.max' => 'حجم تصویر نباید بیشتر از 2 مگابایت باشد.',
            'thumbnail.mime' => 'فایل مورد نظر باید شامل فرمت های ( jpeg,jpg,png,gif ) باشد',
            'category_id.exists' => 'دسته بندی مورد نظر پیدا نشد'

        ];

    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'author_id' => auth()->id()
        ]);
        $this->status ? $this->merge(['status' => 'review']) : $this->merge(['status' => 'pending']);
       if(!empty($this->slug)){
           $this->merge(['slug'=>Str::slug($this->slug)]);
       }

    }
    public function validated($key = null, $default = null){
                 $validatedData = parent::validated();
                 unset($validatedData['thumbnail']);
                 return $validatedData;
    }

    /*   public function messages():array
       {
           return [
               'title.required' => ':attribute را وارد نمایید! ',
               //'title.min' => 'عنوان باید شامل حداقل ۱۰ کارکتر باشد!',
           ];

       }*/


    protected function withValidator(Validator $validator): void
    {
        /*$validator->after(function ($validator) {
            $pattern = '/(\+?98|098|0|0098)?(9\d{9})/';
            if($this->input('title')!== strtoupper($this->input('title'))){
                $validator->errors()->add('title','عنوان باید با کارکترهای بزرگ وارد شود!');
            }
            if (!preg_match($pattern, $this->input('title'))) {
                $validator->errors()->add('title', 'شماره موبایل صحیح نیست');
            }
        });*/

    }

    public function attributes(): array
    {
        return [
            'title' => 'عنوان',
        ];
    }

    /*protected function failedValidation(ValidationValidator $validator)
     {
         // Throw a HttpResponseException with validation errors
         throw new HttpResponseException(response()->json([
             'errors' => $validator->errors(),
         ], 422));
     }*/

    protected function failedAuthorization()
    {
        throw new HttpResponseException(response()->json(['error' => 'Unauthorized'], 403));
    }
}
