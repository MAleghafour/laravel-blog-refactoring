<?php

namespace Tests\Feature\Article;

use App\Models\User;
use App\Models\dashboard\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleTrashTest extends TestCase
{
    use RefreshDatabase;

    private function createArticle(
        User $author,
        string $slug,
        bool $trashed = true
    ): Article {
        $article = Article::create([
            'author_id' => $author->id,
            'title' => $slug,
            'slug' => $slug,
            'status' => 'draft',
        ]);

        if ($trashed) {
            $article->delete();
        }

        return $article;
    }

    public function test_admin_can_view_all_trashed_articles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);

        $first = $this->createArticle($admin, 'admin-trash');
        $second = $this->createArticle($author, 'author-trash');

        $this->actingAs($admin)
            ->get(route('article.trash'))
            ->assertOk()
            ->assertViewHas('articles', function ($articles) use ($first, $second) {
                return $articles->total() === 2
                    && $articles->contains('id', $first->id)
                    && $articles->contains('id', $second->id);
            });
    }

    public function test_author_can_view_only_own_trashed_articles(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $other = User::factory()->create(['role' => 'author']);

        $own = $this->createArticle($author, 'own-trash');
        $otherArticle = $this->createArticle($other, 'other-trash');

        $this->actingAs($author)
            ->get(route('article.trash'))
            ->assertOk()
            ->assertViewHas('articles', function ($articles) use ($own, $otherArticle) {
                return $articles->total() === 1
                    && $articles->contains('id', $own->id)
                    && ! $articles->contains('id', $otherArticle->id);
            });
    }

    public function test_subscriber_cannot_access_trash(): void
    {
        $subscriber = User::factory()->create(['role' => 'subscriber']);

        $this->actingAs($subscriber)
            ->get(route('article.trash'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('article.trash'))
            ->assertRedirect(route('login'));
    }

    public function test_active_articles_are_excluded_from_trash(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $active = $this->createArticle($admin, 'active', false);
        $trashed = $this->createArticle($admin, 'trashed');

        $this->actingAs($admin)
            ->get(route('article.trash'))
            ->assertOk()
            ->assertViewHas('articles', function ($articles) use ($active, $trashed) {
                return $articles->total() === 1
                    && $articles->contains('id', $trashed->id)
                    && ! $articles->contains('id', $active->id);
            });
    }

    public function test_trash_is_paginated_by_fifteen(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        for ($i = 1; $i <= 17; $i++) {
            $this->createArticle($admin, "trash-{$i}");
        }

        $this->actingAs($admin)
            ->get(route('article.trash'))
            ->assertOk()
            ->assertViewHas('articles', function ($articles) {
                return $articles->total() === 17
                    && $articles->perPage() === 15
                    && $articles->count() === 15;
            });

        $this->actingAs($admin)
            ->get(route('article.trash', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('articles', function ($articles) {
                return $articles->currentPage() === 2
                    && $articles->count() === 2;
            });
    }

    public function test_trashed_articles_are_sorted_by_latest_deletion(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $older = $this->createArticle($admin, 'older-trash');
        $newer = $this->createArticle($admin, 'newer-trash');

        $older->deleted_at = '2026-01-01 10:00:00';
        $older->save();

        $newer->deleted_at = '2026-02-01 10:00:00';
        $newer->save();

        $this->actingAs($admin)
            ->get(route('article.trash'))
            ->assertOk()
            ->assertViewHas('articles', function ($articles) use ($older, $newer) {
                return $articles->getCollection()
                        ->pluck('id')
                        ->values()
                        ->all() === [$newer->id, $older->id];
            });
    }

    public function test_admin_can_see_permanent_delete_confirmation_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $article = $this->createArticle($admin, 'admin-delete-ui');

        $this->actingAs($admin)
            ->get(route('article.trash'))
            ->assertOk()
            ->assertSee('forceDeleteModal-' . $article->id)
            ->assertSee('name="confirmation"', false)
            ->assertSee(
                route('article.force-delete', $article),
                false
            )
            ->assertSee('حذف دائمی مقاله');
    }

    public function test_author_cannot_see_permanent_delete_form(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author, 'author-delete-ui');

        $this->actingAs($author)
            ->get(route('article.trash'))
            ->assertOk()
            ->assertDontSee('forceDeleteModal-' . $article->id)
            ->assertDontSee('name="confirmation"', false)
            ->assertDontSee(
                route('article.force-delete', $article),
                false
            );
    }
}
