<?php

namespace Tests\Feature\Article;

use App\Models\User;
use App\Models\dashboard\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleShowTest extends TestCase
{
    use RefreshDatabase;

    private function createArticle(User $author, string $slug): Article
    {
        return Article::create([
            'author_id' => $author->id,
            'title' => $slug,
            'slug' => $slug,
            'status' => 'draft',
        ]);
    }

    public function test_admin_can_view_another_authors_article(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author, 'admin-view-article');

        $this->actingAs($admin)
            ->get(route('article.show', $article))
            ->assertOk()
            ->assertViewIs('dashboard.articles.show')
            ->assertSee('admin-view-article');
    }

    public function test_author_can_view_own_article(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author, 'own-article');

        $this->actingAs($author)
            ->get(route('article.show', $article))
            ->assertOk()
            ->assertSee('own-article');
    }

    public function test_author_cannot_view_another_authors_article(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $other = User::factory()->create(['role' => 'author']);

        $article = $this->createArticle($other, 'other-article');

        $this->actingAs($author)
            ->get(route('article.show', $article))
            ->assertForbidden();
    }

    public function test_subscriber_cannot_view_article(): void
    {
        $subscriber = User::factory()->create(['role' => 'subscriber']);
        $author = User::factory()->create(['role' => 'author']);

        $article = $this->createArticle($author, 'subscriber-view');

        $this->actingAs($subscriber)
            ->get(route('article.show', $article))
            ->assertForbidden();
    }

    public function test_article_details_display_event_history_newest_first(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);

        $article = $this->createArticle($author, 'article-with-history');

        $older = $article->events()->create([
            'actor_id' => $author->id,
            'event_type' => 'created',
            'explanation' => 'اولین رویداد',
        ]);

        $newer = $article->events()->create([
            'actor_id' => $admin->id,
            'event_type' => 'approved',
            'explanation' => 'آخرین رویداد',
        ]);

        $older->created_at = '2026-01-01 10:00:00';
        $older->save();

        $newer->created_at = '2026-02-01 10:00:00';
        $newer->save();

        $this->actingAs($admin)
            ->get(route('article.show', $article))
            ->assertOk()
            ->assertSee('article-with-history')
            ->assertSee('اولین رویداد')
            ->assertSee('آخرین رویداد')
            ->assertSeeInOrder([
                'آخرین رویداد',
                'اولین رویداد',
            ])
            ->assertViewHas('events', function ($events) use ($older, $newer) {
                return $events->total() === 2
                    && $events->perPage() === 10
                    && $events->getCollection()
                        ->pluck('id')
                        ->all() === [$newer->id, $older->id];
            });
    }

    public function test_article_event_history_is_paginated_by_ten(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $article = $this->createArticle($admin, 'paginated-history');

        for ($i = 1; $i <= 12; $i++) {
            $article->events()->create([
                'actor_id' => $admin->id,
                'event_type' => 'test_event',
                'explanation' => "رویداد شماره {$i}",
            ]);
        }

        $this->actingAs($admin)
            ->get(route('article.show', $article))
            ->assertOk()
            ->assertViewHas('events', function ($events) {
                return $events->total() === 12
                    && $events->perPage() === 10
                    && $events->currentPage() === 1
                    && $events->count() === 10;
            });

        $this->actingAs($admin)
            ->get(route('article.show', [
                'article' => $article,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertViewHas('events', function ($events) {
                return $events->total() === 12
                    && $events->currentPage() === 2
                    && $events->count() === 2;
            });
    }

    public function test_admin_can_see_approval_form_for_pending_article(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);

        $article = $this->createArticle($author, 'pending-approval-ui');

        $article->update([
            'status' => 'pending',
            'submitted_for_review_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('article.show', $article))
            ->assertOk()
            ->assertSee('تأیید و انتشار مقاله')
            ->assertSee('name="lock_version"', false)
            ->assertSee(
                route('article.approve', $article),
                false
            );
    }

    public function test_author_cannot_see_approval_form(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $article = $this->createArticle($author, 'author-approval-ui');

        $article->update([
            'status' => 'pending',
            'submitted_for_review_at' => now(),
        ]);

        $this->actingAs($author)
            ->get(route('article.show', $article))
            ->assertOk()
            ->assertDontSee('تأیید و انتشار مقاله')
            ->assertDontSee(
                route('article.approve', $article),
                false
            );
    }

    public function test_admin_can_see_return_for_correction_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);

        $article = $this->createArticle($author, 'pending-return-ui');

        $article->update([
            'status' => 'pending',
            'submitted_for_review_at' => now(),
        ]);

        $article->refresh();

        $this->actingAs($admin)
            ->get(route('article.show', $article))
            ->assertOk()
            ->assertSee('بازگرداندن برای اصلاح')
            ->assertSee('name="explanation"', false)
            ->assertSee('name="lock_version"', false)
            ->assertSee(
                route('article.return', $article),
                false
            );
    }

    public function test_author_cannot_see_return_for_correction_form(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $article = $this->createArticle($author, 'author-return-ui');

        $article->update([
            'status' => 'pending',
            'submitted_for_review_at' => now(),
        ]);

        $this->actingAs($author)
            ->get(route('article.show', $article))
            ->assertOk()
            ->assertDontSee('بازگرداندن برای اصلاح')
            ->assertDontSee(
                route('article.return', $article),
                false
            );
    }

    public function test_author_can_see_submit_form_for_own_draft(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $article = Article::create([
            'author_id' => $author->id,
            'slug' => 'submit-form-visible',
            'status' => 'draft',
        ]);

        $this->actingAs($author)
            ->get(route('article.show', $article))
            ->assertOk()
            ->assertSee(route('article.submit', $article), false)
            ->assertSee('name="lock_version"', false)
            ->assertSee('ارسال برای بازبینی');
    }

    public function test_submit_form_is_hidden_when_article_is_not_draft(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $article = Article::create([
            'author_id' => $author->id,
            'slug' => 'submit-form-hidden',
            'status' => 'pending',
        ]);

        $this->actingAs($author)
            ->get(route('article.show', $article))
            ->assertOk()
            ->assertDontSee(route('article.submit', $article), false);
    }

    public function test_author_can_see_withdraw_form_for_own_pending_article(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $article = Article::create([
            'author_id' => $author->id,
            'slug' => 'withdraw-form-visible',
            'status' => 'pending',
        ]);

        $this->actingAs($author)
            ->get(route('article.show', $article))
            ->assertOk()
            ->assertSee(
                route('article.withdraw', $article),
                false
            )
            ->assertSee('انصراف از بازبینی')
            ->assertSee('name="lock_version"', false);
    }

    public function test_withdraw_form_is_hidden_for_draft_article(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $article = Article::create([
            'author_id' => $author->id,
            'slug' => 'withdraw-form-hidden',
            'status' => 'draft',
        ]);

        $this->actingAs($author)
            ->get(route('article.show', $article))
            ->assertOk()
            ->assertDontSee(
                route('article.withdraw', $article),
                false
            );
    }
}
