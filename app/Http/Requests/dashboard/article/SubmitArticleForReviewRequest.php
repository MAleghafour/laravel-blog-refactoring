<?php

namespace App\Http\Requests\dashboard\article;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Validation\ValidationException;

class SubmitArticleForReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $article = $this->route('article');

        return $article !== null
            && $this->user()?->can('submitForReview', $article) === true;
    }

    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * Validate the persisted article content before submission.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $article = $this->route('article');

                if ($article === null) {
                    return;
                }

                if (! is_string($article->title) ||
                    trim($article->title) === '') {
                    $validator->errors()->add(
                        'title',
                        'عنوان مقاله برای بازبینی الزامی است.'
                    );
                }

                if (
                    $article->category_id === null ||
                    ! \Illuminate\Support\Facades\DB::table('categories')
                        ->where('id', $article->category_id)
                        ->exists()
                ) {
                    $validator->errors()->add(
                        'category_id',
                        'دسته‌بندی مقاله معتبر نیست.'
                    );
                }

                $text = html_entity_decode(
                    strip_tags($article->body ?? ''),
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
            },
        ];
    }

    /**
     * Return validation errors to the article details page.
     */
    protected function failedValidation(ValidatorContract $validator): void
    {
        throw (new ValidationException($validator))
            ->redirectTo(
                route('article.show', $this->route('article'))
            );
    }
}
