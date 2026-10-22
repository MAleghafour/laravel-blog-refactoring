<?php

namespace Tests\Feature\Article;

use App\Models\User;
use App\Models\dashboard\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleIndexTest extends TestCase
{
    use RefreshDatabase;

    private function createArticle(
        User $author,
        string $slug,
        ?string $createdAt = null
    ): Article {
        $article = Article::create([
            'author_id' => $author->id,
            'title' => $slug,
            'slug' => $slug,
            'status' => 'draft',
        ]);

        if ($createdAt !== null) {
            $article->created_at = $createdAt;
            $article->save();
        }

        return $article;
    }

    public function test_admin_can_view_all_articles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);

        $adminArticle = $this->createArticle($admin, 'admin-article');
        $authorArticle = $this->createArticle($author, 'author-article');

        $this->actingAs($admin)
            ->get(route('article.index'))
            ->assertOk()
            ->assertViewHas('articles', function ($articles) use (
                $adminArticle,
                $authorArticle
            ) {
                return $articles->total() === 2
                    && $articles->contains('id', $adminArticle->id)
                    && $articles->contains('id', $authorArticle->id);
            });
    }

    public function test_author_can_view_only_own_articles(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $otherAuthor = User::factory()->create(['role' => 'author']);

        $ownArticle = $this->createArticle($author, 'own-article');
        $otherArticle = $this->createArticle($otherAuthor, 'other-article');

        $this->actingAs($author)
            ->get(route('article.index'))
            ->assertOk()
            ->assertViewHas('articles', function ($articles) use (
                $ownArticle,
                $otherArticle
            ) {
                return $articles->total() === 1
                    && $articles->contains('id', $ownArticle->id)
                    && ! $articles->contains('id', $otherArticle->id);
            });
    }

    public function test_subscriber_cannot_access_article_index(): void
    {
        $subscriber = User::factory()->create(['role' => 'subscriber']);

        $this->actingAs($subscriber)
            ->get(route('article.index'))
            ->assertForbidden();
    }

    public function test_guest_cannot_access_article_index(): void
    {
        $this->get(route('article.index'))
            ->assertRedirect(route('login'));
    }

    public function test_article_index_is_paginated_by_fifteen(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        for ($i = 1; $i <= 17; $i++) {
            $this->createArticle($admin, "article-{$i}");
        }

        $this->actingAs($admin)
            ->get(route('article.index'))
            ->assertOk()
            ->assertViewHas('articles', function ($articles) {
                return $articles->total() === 17
                    && $articles->perPage() === 15
                    && $articles->count() === 15;
            });

        $this->actingAs($admin)
            ->get(route('article.index', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('articles', function ($articles) {
                return $articles->currentPage() === 2
                    && $articles->count() === 2;
            });
    }

    public function test_articles_are_sorted_newest_first(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $older = $this->createArticle(
            $admin,
            'older-article',
            '2026-01-01 10:00:00'
        );

        $newer = $this->createArticle(
            $admin,
            'newer-article',
            '2026-02-01 10:00:00'
        );

        $this->actingAs($admin)
            ->get(route('article.index'))
            ->assertOk()
            ->assertViewHas('articles', function ($articles) use (
                $older,
                $newer
            ) {
                return $articles->getCollection()
                        ->pluck('id')
                        ->values()
                        ->all() === [$newer->id, $older->id];
            });
    }

    public function test_soft_deleted_articles_are_excluded_from_index(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $active = $this->createArticle($admin, 'active-article');
        $trashed = $this->createArticle($admin, 'trashed-article');

        $trashed->delete();

        $this->actingAs($admin)
            ->get(route('article.index'))
            ->assertOk()
            ->assertViewHas('articles', function ($articles) use (
                $active,
                $trashed
            ) {
                return $articles->total() === 1
                    && $articles->contains('id', $active->id)
                    && ! $articles->contains('id', $trashed->id);
            });
    }
}
