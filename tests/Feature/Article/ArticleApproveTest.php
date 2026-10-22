<?php

namespace Tests\Feature\Article;

use App\Models\User;
use App\Models\dashboard\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class ArticleApproveTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_pending_article(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);

        $article = Article::create([
            'author_id' => $author->id,
            'title' => 'Pending Article',
            'slug' => 'pending-approve-test',
            'status' => 'pending',
            'submitted_for_review_at' => now(),
        ]);

        $article->refresh();

        $response = $this->actingAs($admin)
            ->post(route('article.approve', $article), [
                'lock_version' => $article->lock_version,
                'explanation' => 'مقاله بررسی و تأیید شد.',
            ]);

        $response
            ->assertRedirect(route('article.show', $article))
            ->assertSessionHas(
                'success',
                'مقاله با موفقیت تأیید و منتشر شد.'
            );

        $article->refresh();

        $this->assertSame('published', $article->status);
        $this->assertSame(1, $article->lock_version);
        $this->assertNotNull($article->first_published_at);

        $this->assertDatabaseHas('article_events', [
            'article_id' => $article->id,
            'actor_id' => $admin->id,
            'event_type' => 'approved',
            'explanation' => 'مقاله بررسی و تأیید شد.',
        ]);
    }


    public function test_admin_cannot_approve_article_with_stale_version(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);

        $article = Article::create([
            'author_id' => $author->id,
            'title' => 'Original Article',
            'slug' => 'stale-approve-test',
            'status' => 'pending',
            'submitted_for_review_at' => now(),
        ]);

        $article->refresh();

        // Version originally seen by Admin.
        $staleVersion = $article->lock_version;

        // Another request modifies the article.
        $article->update([
            'title' => 'Modified Article',
        ]);

        $this->assertSame(
            $staleVersion + 1,
            $article->fresh()->lock_version
        );

        $response = $this->actingAs($admin)
            ->post(route('article.approve', $article), [
                'lock_version' => $staleVersion,
            ]);

        $response
            ->assertRedirect(route('article.show', $article))
            ->assertSessionHas(
                'error',
                'مقاله پس از باز شدن صفحه تغییر کرده است. لطفاً صفحه را دوباره بارگذاری و بررسی کنید.'
            );

        $article->refresh();

        $this->assertSame('pending', $article->status);
        $this->assertSame('Modified Article', $article->title);
        $this->assertSame($staleVersion + 1, $article->lock_version);
        $this->assertNull($article->first_published_at);

        $this->assertDatabaseMissing('article_events', [
            'article_id' => $article->id,
            'event_type' => 'approved',
        ]);
    }


    public function test_reapproval_preserves_first_publication_date(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);

        $firstPublishedAt = '2026-01-15 10:30:00';

        $article = Article::create([
            'author_id' => $author->id,
            'title' => 'Previously Published Article',
            'slug' => 'reapproval-test',
            'status' => 'pending',
            'first_published_at' => $firstPublishedAt,
            'submitted_for_review_at' => now(),
        ]);

        $article->refresh();

        $this->actingAs($admin)
            ->post(route('article.approve', $article), [
                'lock_version' => $article->lock_version,
            ])
            ->assertRedirect(route('article.show', $article))
            ->assertSessionHas('success');

        $article->refresh();

        $this->assertSame('published', $article->status);

        $this->assertSame(
            $firstPublishedAt,
            $article->first_published_at->format('Y-m-d H:i:s')
        );

        $this->assertDatabaseHas('article_events', [
            'article_id' => $article->id,
            'actor_id' => $admin->id,
            'event_type' => 'approved',
        ]);
    }

    public function test_author_cannot_approve_own_article(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $article = Article::create([
            'author_id' => $author->id,
            'title' => 'Author Pending Article',
            'slug' => 'author-approve-test',
            'status' => 'pending',
            'submitted_for_review_at' => now(),
        ]);

        $article->refresh();

        $this->actingAs($author)
            ->post(route('article.approve', $article), [
                'lock_version' => $article->lock_version,
            ])
            ->assertForbidden();

        $article->refresh();

        $this->assertSame('pending', $article->status);

        $this->assertDatabaseMissing('article_events', [
            'article_id' => $article->id,
            'event_type' => 'approved',
        ]);
    }

    public function test_subscriber_cannot_approve_article(): void
    {
        $subscriber = User::factory()->create(['role' => 'subscriber']);
        $author = User::factory()->create(['role' => 'author']);

        $article = Article::create([
            'author_id' => $author->id,
            'title' => 'Subscriber Approval Test',
            'slug' => 'subscriber-approval-test',
            'status' => 'pending',
            'submitted_for_review_at' => now(),
        ]);

        $article->refresh();

        $this->actingAs($subscriber)
            ->post(route('article.approve', $article), [
                'lock_version' => $article->lock_version,
            ])
            ->assertForbidden();

        $this->assertSame('pending', $article->fresh()->status);

        $this->assertDatabaseMissing('article_events', [
            'article_id' => $article->id,
            'event_type' => 'approved',
        ]);
    }

    public function test_guest_cannot_approve_article(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $article = Article::create([
            'author_id' => $author->id,
            'title' => 'Guest Approval Test',
            'slug' => 'guest-approval-test',
            'status' => 'pending',
            'submitted_for_review_at' => now(),
        ]);

        $article->refresh();

        $this->post(route('article.approve', $article), [
            'lock_version' => $article->lock_version,
        ])
            ->assertRedirect(route('login'));

        $this->assertSame('pending', $article->fresh()->status);

        $this->assertDatabaseMissing('article_events', [
            'article_id' => $article->id,
            'event_type' => 'approved',
        ]);
    }


    public function test_admin_cannot_approve_draft_article(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $article = Article::create([
            'author_id' => $admin->id,
            'title' => 'Draft Article',
            'slug' => 'draft-approval-test',
            'status' => 'draft',
        ]);

        $article->refresh();

        $this->actingAs($admin)
            ->post(route('article.approve', $article), [
                'lock_version' => $article->lock_version,
            ])
            ->assertForbidden();

        $this->assertSame('draft', $article->fresh()->status);

        $this->assertDatabaseMissing('article_events', [
            'article_id' => $article->id,
            'event_type' => 'approved',
        ]);
    }

    public function test_admin_cannot_approve_already_published_article(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $article = Article::create([
            'author_id' => $admin->id,
            'title' => 'Published Article',
            'slug' => 'published-approval-test',
            'status' => 'published',
            'first_published_at' => now(),
        ]);

        $article->refresh();

        $this->actingAs($admin)
            ->post(route('article.approve', $article), [
                'lock_version' => $article->lock_version,
            ])
            ->assertForbidden();

        $this->assertSame('published', $article->fresh()->status);

        $this->assertDatabaseMissing('article_events', [
            'article_id' => $article->id,
            'event_type' => 'approved',
        ]);
    }

    public function test_admin_cannot_approve_trashed_article(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $article = Article::create([
            'author_id' => $admin->id,
            'title' => 'Trashed Pending Article',
            'slug' => 'trashed-approval-test',
            'status' => 'pending',
            'submitted_for_review_at' => now(),
        ]);

        $article->delete();

        $this->actingAs($admin)
            ->post(route('article.approve', $article->id), [
                'lock_version' => $article->lock_version,
            ])
            ->assertNotFound();

        $this->assertSoftDeleted($article);

        $this->assertDatabaseMissing('article_events', [
            'article_id' => $article->id,
            'event_type' => 'approved',
        ]);
    }

    public function test_approval_requires_valid_lock_version(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $article = Article::create([
            'author_id' => $admin->id,
            'title' => 'Version Validation',
            'slug' => 'approval-version-validation',
            'status' => 'pending',
            'submitted_for_review_at' => now(),
        ]);

        foreach ([
                     [],
                     ['lock_version' => -1],
                     ['lock_version' => 'invalid'],
                 ] as $data) {
            $this->actingAs($admin)
                ->post(route('article.approve', $article), $data)
                ->assertSessionHasErrors('lock_version');
        }

        $this->assertSame('pending', $article->fresh()->status);

        $this->assertDatabaseMissing('article_events', [
            'article_id' => $article->id,
            'event_type' => 'approved',
        ]);
    }

    public function test_approval_rejects_explanation_longer_than_limit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $article = Article::create([
            'author_id' => $admin->id,
            'title' => 'Explanation Validation',
            'slug' => 'approval-explanation-validation',
            'status' => 'pending',
            'submitted_for_review_at' => now(),
        ]);

        $article->refresh();

        $this->actingAs($admin)
            ->post(route('article.approve', $article), [
                'lock_version' => $article->lock_version,
                'explanation' => str_repeat('A', 2001),
            ])
            ->assertSessionHasErrors('explanation');

        $this->assertSame('pending', $article->fresh()->status);

        $this->assertDatabaseMissing('article_events', [
            'article_id' => $article->id,
            'event_type' => 'approved',
        ]);
    }

    public function test_admin_can_approve_without_explanation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $article = Article::create([
            'author_id' => $admin->id,
            'title' => 'Approval Without Explanation',
            'slug' => 'approval-without-explanation',
            'status' => 'pending',
            'submitted_for_review_at' => now(),
        ]);

        $article->refresh();

        $this->actingAs($admin)
            ->post(route('article.approve', $article), [
                'lock_version' => $article->lock_version,
            ])
            ->assertRedirect(route('article.show', $article))
            ->assertSessionHas('success');

        $this->assertSame('published', $article->fresh()->status);

        $this->assertDatabaseHas('article_events', [
            'article_id' => $article->id,
            'actor_id' => $admin->id,
            'event_type' => 'approved',
            'explanation' => null,
        ]);
    }


    public function test_approval_rolls_back_when_event_creation_fails(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);

        $article = Article::create([
            'author_id' => $author->id,
            'title' => 'Rollback Approval Test',
            'slug' => 'rollback-approval-test',
            'status' => 'pending',
            'submitted_for_review_at' => now(),
        ]);

        $article->refresh();

        $originalVersion = $article->lock_version;

        DB::unprepared("
        CREATE TRIGGER fail_approval_event
        BEFORE INSERT ON article_events
        WHEN NEW.event_type = 'approved'
        BEGIN
            SELECT RAISE(ABORT, 'Simulated event creation failure');
        END;
    ");

        try {
            $response = $this->actingAs($admin)
                ->post(route('article.approve', $article), [
                    'lock_version' => $originalVersion,
                ]);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS fail_approval_event');
        }

        $response
            ->assertRedirect(route('article.show', $article))
            ->assertSessionHas(
                'error',
                'تأیید مقاله به دلیل خطای سیستمی انجام نشد. لطفاً دوباره تلاش کنید.'
            )
            ->assertSessionMissing('success');

        $article->refresh();

        $this->assertSame('pending', $article->status);
        $this->assertSame($originalVersion, $article->lock_version);
        $this->assertNull($article->first_published_at);

        $this->assertDatabaseMissing('article_events', [
            'article_id' => $article->id,
            'event_type' => 'approved',
        ]);
    }
}
