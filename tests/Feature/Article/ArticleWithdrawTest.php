<?php

namespace Tests\Feature\Article;

use App\Models\User;
use App\Models\dashboard\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ArticleWithdrawTest extends TestCase
{
    use RefreshDatabase;

    private function createPendingArticle(User $author): Article
    {
        return Article::create([
            'author_id' => $author->id,
            'slug' => 'withdraw-' . uniqid(),
            'status' => 'pending',
            'submitted_for_review_at' => now(),
        ]);
    }

    public function test_author_can_withdraw_own_pending_article(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createPendingArticle($author);
        $submittedAt = $article->submitted_for_review_at;

        $this->actingAs($author)
            ->post(route('article.withdraw', $article), [
                'lock_version' => $article->lock_version,
            ])
            ->assertRedirect(route('article.show', $article))
            ->assertSessionHas('success');

        $article->refresh();

        $this->assertSame('draft', $article->status);
        $this->assertSame(1, $article->lock_version);
        $this->assertTrue(
            $submittedAt->equalTo($article->submitted_for_review_at)
        );

        $this->assertDatabaseHas('article_events', [
            'article_id' => $article->id,
            'actor_id' => $author->id,
            'event_type' => 'withdrawn',
        ]);
    }

    public function test_author_cannot_withdraw_another_authors_article(): void
    {
        $owner = User::factory()->create(['role' => 'author']);
        $other = User::factory()->create(['role' => 'author']);
        $article = $this->createPendingArticle($owner);

        $this->actingAs($other)
            ->post(route('article.withdraw', $article), [
                'lock_version' => $article->lock_version,
            ])
            ->assertForbidden();

        $this->assertSame('pending', $article->fresh()->status);
    }

    public function test_admin_cannot_withdraw_article(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createPendingArticle($author);

        $this->actingAs($admin)
            ->post(route('article.withdraw', $article), [
                'lock_version' => $article->lock_version,
            ])
            ->assertForbidden();
    }

    public function test_subscriber_cannot_withdraw_article(): void
    {
        $subscriber = User::factory()->create(['role' => 'subscriber']);
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createPendingArticle($author);

        $this->actingAs($subscriber)
            ->post(route('article.withdraw', $article), [
                'lock_version' => $article->lock_version,
            ])
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createPendingArticle($author);

        $this->post(route('article.withdraw', $article), [
            'lock_version' => $article->lock_version,
        ])
            ->assertRedirect(route('login'));
    }

    public function test_draft_article_cannot_be_withdrawn(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createPendingArticle($author);
        $article->update(['status' => 'draft']);

        $this->actingAs($author)
            ->post(route('article.withdraw', $article), [
                'lock_version' => $article->lock_version,
            ])
            ->assertForbidden();
    }

    public function test_missing_lock_version_is_rejected(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createPendingArticle($author);

        $this->actingAs($author)
            ->post(route('article.withdraw', $article))
            ->assertRedirect(route('article.show', $article))
            ->assertSessionHasErrors('lock_version');
    }

    public function test_stale_lock_version_prevents_withdrawal(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createPendingArticle($author);

        $this->actingAs($author)
            ->post(route('article.withdraw', $article), [
                'lock_version' => $article->lock_version + 1,
            ])
            ->assertSessionHas('error');

        $this->assertSame('pending', $article->fresh()->status);
        $this->assertDatabaseCount('article_events', 0);
    }

    public function test_withdrawal_rolls_back_when_event_creation_fails(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createPendingArticle($author);

        DB::statement("
            CREATE TRIGGER fail_withdraw_event
            BEFORE INSERT ON article_events
            BEGIN
                SELECT RAISE(ABORT, 'Simulated event failure');
            END
        ");

        $this->actingAs($author)
            ->post(route('article.withdraw', $article), [
                'lock_version' => $article->lock_version,
            ])
            ->assertRedirect(route('article.show', $article))
            ->assertSessionHas('error');

        $article->refresh();

        $this->assertSame('pending', $article->status);
        $this->assertSame(0, $article->lock_version);
        $this->assertDatabaseCount('article_events', 0);
    }
}
