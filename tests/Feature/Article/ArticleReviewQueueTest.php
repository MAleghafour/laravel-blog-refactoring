<?php

namespace Tests\Feature\Article;

use App\Models\User;
use App\Models\dashboard\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleReviewQueueTest extends TestCase
{
    use RefreshDatabase;

    private function createArticle(
        User $author,
        string $slug,
        string $status = 'pending'
    ): Article {
        return Article::create([
            'author_id' => $author->id,
            'title' => $slug,
            'slug' => $slug,
            'status' => $status,
            'submitted_for_review_at' => $status === 'pending' ? now() : null,
        ]);
    }

    public function test_admin_can_access_review_queue(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('article.review-queue'))
            ->assertOk()
            ->assertViewIs('dashboard.articles.review-queue');
    }

    public function test_author_cannot_access_review_queue(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)
            ->get(route('article.review-queue'))
            ->assertForbidden();
    }

    public function test_subscriber_cannot_access_review_queue(): void
    {
        $subscriber = User::factory()->create(['role' => 'subscriber']);

        $this->actingAs($subscriber)
            ->get(route('article.review-queue'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('article.review-queue'))
            ->assertRedirect(route('login'));
    }

    public function test_review_queue_contains_only_active_pending_articles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);

        $pending = $this->createArticle($author, 'pending-article');
        $draft = $this->createArticle($author, 'draft-article', 'draft');
        $published = $this->createArticle($author, 'published-article', 'published');
        $trashed = $this->createArticle($author, 'trashed-pending');
        $trashed->delete();

        $this->actingAs($admin)
            ->get(route('article.review-queue'))
            ->assertOk()
            ->assertViewHas('articles', function ($articles) use (
                $pending,
                $draft,
                $published,
                $trashed
            ) {
                return $articles->total() === 1
                    && $articles->contains('id', $pending->id)
                    && ! $articles->contains('id', $draft->id)
                    && ! $articles->contains('id', $published->id)
                    && ! $articles->contains('id', $trashed->id);
            });
    }

    public function test_review_queue_is_sorted_by_oldest_submission_first(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);

        $newest = $this->createArticle($author, 'newest-submission');
        $oldest = $this->createArticle($author, 'oldest-submission');
        $middle = $this->createArticle($author, 'middle-submission');

        $newest->update([
            'submitted_for_review_at' => '2026-03-01 10:00:00',
        ]);

        $oldest->update([
            'submitted_for_review_at' => '2026-01-01 10:00:00',
        ]);

        $middle->update([
            'submitted_for_review_at' => '2026-02-01 10:00:00',
        ]);

        $this->actingAs($admin)
            ->get(route('article.review-queue'))
            ->assertOk()
            ->assertViewHas('articles', function ($articles) use (
                $oldest,
                $middle,
                $newest
            ) {
                return $articles->getCollection()
                        ->pluck('id')
                        ->all() === [
                        $oldest->id,
                        $middle->id,
                        $newest->id,
                    ];
            });
    }

    public function test_review_queue_is_paginated_by_fifteen(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);

        for ($i = 1; $i <= 17; $i++) {
            $article = $this->createArticle(
                $author,
                "pending-article-{$i}"
            );

            $article->update([
                'submitted_for_review_at' => now()->subDays(18 - $i),
            ]);
        }

        $this->actingAs($admin)
            ->get(route('article.review-queue'))
            ->assertOk()
            ->assertViewHas('articles', function ($articles) {
                return $articles->total() === 17
                    && $articles->perPage() === 15
                    && $articles->currentPage() === 1
                    && $articles->count() === 15;
            });

        $this->actingAs($admin)
            ->get(route('article.review-queue', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('articles', function ($articles) {
                return $articles->total() === 17
                    && $articles->currentPage() === 2
                    && $articles->count() === 2;
            });
    }
}
