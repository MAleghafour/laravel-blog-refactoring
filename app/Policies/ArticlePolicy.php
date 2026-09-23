<?php

namespace App\Policies;

use App\Models\User;
use App\Models\dashboard\Article;

class ArticlePolicy
{
    /**
     * Determine whether the user can update the article.
     */
    public function update(User $user, Article $article): bool
    {
        return $user->role === 'admin'
            || ($user->role === 'author' && $article->author_id === $user->id);
    }

    /**
     * Determine whether the user can delete the article.
     */
    public function delete(User $user, Article $article): bool
    {
        return $user->role === 'admin'
            || ($user->role === 'author' && $article->author_id === $user->id);
    }
}
