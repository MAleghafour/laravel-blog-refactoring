<?php

namespace App\Http\Requests\dashboard\article;

use App\Services\ArticleHtmlSanitizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateArticleRequest extends FormRequest
{
    /**
     * Authorize editing through the ArticlePolicy.
     */
    public function authorize(): bool
    {
        $article = $this->route('article');

        return $article !== null
            && $this->user()?->can('update', $article) === true;
    }

    /**
     * Normalize the slug before validation.
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
     * Validate article updates based on the resulting status.
     */
    public function rules(): array
    {
        $article = $this->route('article');
        $isAdmin = $this->user()?->role === 'admin';

        $targetStatus = $isAdmin
            ? $this->input('status', $article->status)
            : ($article->status === 'published'
                ? 'pending'
                : $article->status);

        $requiresCompleteContent = in_array(
            $targetStatus,
            ['pending', 'published'],
            true
        );

        return [
            'title' => [
                $requiresCompleteContent ? 'required' : 'nullable',
                'string',
                'max:255',
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('articles', 'slug')->ignore($article->id),
            ],
            'body' => [
                $requiresCompleteContent ? 'required' : 'nullable',
                'string',
            ],
            'brief' => ['nullable', 'string', 'max:260'],
            'tags' => ['nullable', 'string'],
            'category_id' => [
                $requiresCompleteContent ? 'required' : 'nullable',
                'exists:categories,id',
            ],
            'thumbnail' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png',
                'max:2048',
            ],
            'lock_version' => ['required', 'integer', 'min:0'],
            'status' => $isAdmin
                ? ['sometimes', 'required', Rule::in(['draft', 'pending', 'published'])]
                : ['prohibited'],
            'explanation' => $isAdmin
                ? ['nullable', 'string', 'max:2000']
                : ['prohibited'],
        ];
    }

    /**
     * Validate visible article content and Admin explanations.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $article = $this->route('article');
                $isAdmin = $this->user()?->role === 'admin';

                $targetStatus = $isAdmin
                    ? $this->input('status', $article->status)
                    : ($article->status === 'published'
                        ? 'pending'
                        : $article->status);

                if (in_array($targetStatus, ['pending', 'published'], true)) {
                    // Validate the visible content after applying the storage allowlist.
                    $sanitizedBody = app(ArticleHtmlSanitizer::class)
                        ->sanitize($this->input('body'));

                    $text = html_entity_decode(
                        strip_tags($sanitizedBody ?? ''),
                        ENT_QUOTES | ENT_HTML5,
                        'UTF-8'
                    );

                    $visibleText = preg_replace('/\s+/u', '', $text);

                    if ($visibleText === null ||
                        mb_strlen($visibleText) < 50) {
                        $validator->errors()->add(
                            'body',
                            'متن مقاله باید حداقل ۵۰ کاراکتر قابل‌مشاهده داشته باشد.'
                        );
                    }
                }

                if ($isAdmin &&
                    $article->author_id !== $this->user()->id &&
                    trim((string) $this->input('explanation', '')) === '') {
                    $validator->errors()->add(
                        'explanation',
                        'توضیح دلیل ویرایش مقاله نویسنده دیگر الزامی است.'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'عنوان مقاله الزامی است.',
            'slug.required' => 'نامک مقاله الزامی است.',
            'slug.unique' => 'این نامک قبلاً ثبت شده است.',
            'body.required' => 'متن مقاله الزامی است.',
            'category_id.required' => 'دسته‌بندی مقاله الزامی است.',
            'category_id.exists' => 'دسته‌بندی انتخاب‌شده معتبر نیست.',
            'brief.max' => 'توضیح کوتاه نباید بیشتر از ۲۶۰ کاراکتر باشد.',
            'thumbnail.image' => 'فایل انتخاب‌شده باید تصویر باشد.',
            'thumbnail.mimes' => 'فرمت تصویر باید JPG یا PNG باشد.',
            'thumbnail.max' => 'حجم تصویر نباید بیشتر از ۲ مگابایت باشد.',
            'lock_version.required' => 'نسخه مقاله مشخص نیست. صفحه را دوباره بارگذاری کنید.',
            'status.required' => 'انتخاب وضعیت مقاله الزامی است.',
            'status.in' => 'وضعیت انتخاب‌شده معتبر نیست.',
            'status.prohibited' => 'نویسنده اجازه تعیین مستقیم وضعیت مقاله را ندارد.',
        ];
    }
}
