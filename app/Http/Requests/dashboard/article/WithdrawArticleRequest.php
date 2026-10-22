<?php

namespace App\Http\Requests\dashboard\article;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;

class WithdrawArticleRequest extends FormRequest
{
    /**
     * Allow the author to withdraw their own pending article.
     */
    public function authorize(): bool
    {
        $article = $this->route('article');

        return $article !== null
            && $this->user()?->can('withdraw', $article) === true;
    }

    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * Return validation errors to the article details page.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw (new ValidationException($validator))
            ->redirectTo(
                route('article.show', $this->route('article'))
            );
    }
}
