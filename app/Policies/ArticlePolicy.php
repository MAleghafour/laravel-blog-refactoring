<?php

namespace App\Policies;

use App\Models\User;
use App\Models\dashboard\Article;
use Illuminate\Auth\Access\Response;

class ArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'author'], true);
    }

    public function view(User $user, Article $article): bool
    {
        return $this->canManage($user, $article);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'author'], true);
    }

    public function update(User $user, Article $article): bool
    {
        return $this->canManage($user, $article)
            && ! $article->trashed();
    }

    public function delete(User $user, Article $article): bool
    {
        return $this->canManage($user, $article)
            && ! $article->trashed();
    }

    public function restore(User $user, Article $article): bool
    {
        return $this->canManage($user, $article)
            && $article->trashed();
    }

    public function forceDelete(User $user, Article $article): bool
    {
        return $user->role === 'admin'
            && $article->trashed();
    }

    public function approve(User $user, Article $article): bool
    {
        return $user->role === 'admin'
            && ! $article->trashed()
            && $article->status === 'pending';
    }

    public function returnForCorrection(User $user, Article $article): bool
    {
        return $user->role === 'admin'
            && ! $article->trashed()
            && $article->status === 'pending';
    }

    public function submitForReview(User $user, Article $article): bool
    {
        return $user->role === 'author'
            && $article->author_id === $user->id
            && ! $article->trashed()
            && $article->status === 'draft';
    }

    public function withdraw(User $user, Article $article): bool
    {
        return $user->role === 'author'
            && $article->author_id === $user->id
            && ! $article->trashed()
            && $article->status === 'pending';
    }

    private function canManage(User $user, Article $article): bool
    {
        return $user->role === 'admin'
            || (
                $user->role === 'author'
                && $article->author_id === $user->id
            );
    }
}
