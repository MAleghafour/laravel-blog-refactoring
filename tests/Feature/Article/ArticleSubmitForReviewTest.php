<?php

namespace Tests\Feature\Article;

use App\Models\User;
use App\Models\dashboard\Article;
use App\Models\dashboard\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ArticleSubmitForReviewTest extends TestCase
{
    use RefreshDatabase;

    private function createReadyDraft(User $author): Article
    {
        $category = Category::create([
            'name' => 'Technology',
            'slug' => 'technology-' . uniqid(),
        ]);

        return Article::create([
            'author_id' => $author->id,
            'title' => 'Ready article',
            'slug' => 'ready-' . uniqid(),
            'body' => '<p>' . str_repeat('Laravel article content ', 4) . '</p>',
            'category_id' => $category->id,
            'status' => 'draft',
        ]);
    }

    public function test_author_can_submit_own_draft(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createReadyDraft($author);
        $this->assertSame(0, $article->lock_version);
        $this->actingAs($author)
            ->post(route('article.submit', $article), [
                'lock_version' => $article->lock_version,
            ])
            ->assertRedirect(route('article.show', $article))
            ->assertSessionHas('success');

        $article->refresh();

        $this->assertSame('pending', $article->status);
        $this->assertNotNull($article->submitted_for_review_at);
        $this->assertSame(1, $article->lock_version);

        $this->assertDatabaseHas('article_events', [
            'article_id' => $article->id,
            'actor_id' => $author->id,
            'event_type' => 'submitted_for_review',
        ]);
    }

    public function test_author_cannot_submit_another_authors_draft(): void
    {
        $owner = User::factory()->create(['role' => 'author']);
        $other = User::factory()->create(['role' => 'author']);
        $article = $this->createReadyDraft($owner);

        $this->actingAs($other)
            ->post(route('article.submit', $article), [
                'lock_version' => $article->lock_version,
            ])
            ->assertForbidden();

        $this->assertSame('draft', $article->fresh()->status);
    }

    public function test_admin_cannot_submit_draft(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createReadyDraft($author);

        $this->actingAs($admin)
            ->post(route('article.submit', $article), [
                'lock_version' => $article->lock_version,
            ])
            ->assertForbidden();
    }

    public function test_subscriber_cannot_submit_draft(): void
    {
        $subscriber = User::factory()->create(['role' => 'subscriber']);
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createReadyDraft($author);

        $this->actingAs($subscriber)
            ->post(route('article.submit', $article), [
                'lock_version' => $article->lock_version,
            ])
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createReadyDraft($author);

        $this->post(route('article.submit', $article), [
            'lock_version' => $article->lock_version,
        ])
            ->assertRedirect(route('login'));
    }

    public function test_article_without_title_cannot_be_submitted(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createReadyDraft($author);
        $article->update(['title' => null]);

        $this->actingAs($author)
            ->post(route('article.submit', $article), [
                'lock_version' => $article->lock_version,
            ])
            ->assertSessionHasErrors('title');

        $this->assertSame('draft', $article->fresh()->status);
    }

    public function test_article_without_category_cannot_be_submitted(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createReadyDraft($author);
        $article->update(['category_id' => null]);

        $this->actingAs($author)
            ->post(route('article.submit', $article), [
                'lock_version' => $article->lock_version,
            ])
            ->assertSessionHasErrors('category_id');

        $this->assertSame('draft', $article->fresh()->status);
    }

    public function test_article_with_short_visible_text_cannot_be_submitted(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createReadyDraft($author);
        $article->update([
            'body' => '<p><strong>Short text</strong></p>',
        ]);

        $this->actingAs($author)
            ->post(route('article.submit', $article), [
                'lock_version' => $article->lock_version,
            ])
            ->assertSessionHasErrors('body');

        $this->assertSame('draft', $article->fresh()->status);
    }

    public function test_missing_lock_version_is_rejected(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createReadyDraft($author);

        $this->actingAs($author)
            ->post(route('article.submit', $article))
            ->assertSessionHasErrors('lock_version');
    }

    public function test_stale_lock_version_prevents_submission(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createReadyDraft($author);

        $this->actingAs($author)
            ->post(route('article.submit', $article), [
                'lock_version' => $article->lock_version + 1,
            ])
            ->assertSessionHas('error');

        $this->assertSame('draft', $article->fresh()->status);
        $this->assertDatabaseCount('article_events', 0);
    }

    public function test_pending_article_cannot_be_submitted_again(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createReadyDraft($author);
        $article->update(['status' => 'pending']);

        $this->actingAs($author)
            ->post(route('article.submit', $article), [
                'lock_version' => $article->lock_version,
            ])
            ->assertForbidden();
    }

    public function test_submission_rolls_back_when_event_creation_fails(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createReadyDraft($author);

        DB::statement("
            CREATE TRIGGER fail_submit_event
            BEFORE INSERT ON article_events
            BEGIN
                SELECT RAISE(ABORT, 'Simulated event failure');
            END
        ");

        $this->actingAs($author)
            ->post(route('article.submit', $article), [
                'lock_version' => $article->lock_version,
            ])
            ->assertSessionHas('error');

        $article->refresh();

        $this->assertSame('draft', $article->status);
        $this->assertNull($article->submitted_for_review_at);
        $this->assertSame(0, $article->lock_version);
        $this->assertDatabaseCount('article_events', 0);
    }

    public function test_validation_failure_redirects_to_article_show(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createReadyDraft($author);

        $article->update(['title' => null]);

        $this->actingAs($author)
            ->post(route('article.submit', $article), [
                'lock_version' => $article->lock_version,
            ])
            ->assertRedirect(route('article.show', $article))
            ->assertSessionHasErrors('title');

        $this->assertSame('draft', $article->fresh()->status);
    }

    public function test_validation_errors_are_visible_on_article_show(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createReadyDraft($author);

        $article->update(['body' => '<p>Short</p>']);

        $this->actingAs($author)
            ->post(route('article.submit', $article), [
                'lock_version' => $article->lock_version,
            ])
            ->assertRedirect(route('article.show', $article))
            ->assertSessionHasErrors('body');

        $this->get(route('article.show', $article))
            ->assertOk()
            ->assertSee(
                'متن مقاله باید حداقل ۵۰ کاراکتر قابل‌مشاهده داشته باشد.'
            );
    }
}
