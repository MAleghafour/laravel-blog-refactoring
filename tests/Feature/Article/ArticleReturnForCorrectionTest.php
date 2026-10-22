<?php

namespace Tests\Feature\Article;

use App\Models\User;
use App\Models\dashboard\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ArticleReturnForCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function createArticle(
        User $author,
        string $status = 'pending'
    ): Article {
        $article = Article::create([
            'author_id' => $author->id,
            'title' => 'Article for Correction',
            'slug' => 'article-for-correction',
            'status' => $status,
            'submitted_for_review_at' => $status === 'pending' ? now() : null,
        ]);

        return $article->refresh();
    }

    public function test_admin_can_return_pending_article_for_correction(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author);

        $this->actingAs($admin)
            ->post(route('article.return', $article), [
                'lock_version' => $article->lock_version,
                'explanation' => 'متن مقاله نیاز به اصلاح دارد.',
            ])
            ->assertRedirect(route('article.show', $article))
            ->assertSessionHas('success');

        $article->refresh();

        $this->assertSame('draft', $article->status);
        $this->assertSame(1, $article->lock_version);

        $this->assertDatabaseHas('article_events', [
            'article_id' => $article->id,
            'actor_id' => $admin->id,
            'event_type' => 'returned_for_correction',
            'explanation' => 'متن مقاله نیاز به اصلاح دارد.',
        ]);
    }

    public function test_return_preserves_first_publication_date(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $article = $this->createArticle($admin);

        $article->update([
            'first_published_at' => '2026-01-15 10:30:00',
        ]);

        $article->refresh();

        $this->actingAs($admin)
            ->post(route('article.return', $article), [
                'lock_version' => $article->lock_version,
                'explanation' => 'اصلاح محتوای مقاله',
            ])
            ->assertSessionHas('success');

        $this->assertSame(
            '2026-01-15 10:30:00',
            $article->fresh()->first_published_at->format('Y-m-d H:i:s')
        );
    }

    public function test_return_requires_explanation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $article = $this->createArticle($admin);

        foreach ([
                     null,
                     '',
                     str_repeat('A', 2001),
                 ] as $explanation) {
            $this->actingAs($admin)
                ->post(route('article.return', $article), [
                    'lock_version' => $article->lock_version,
                    'explanation' => $explanation,
                ])
                ->assertSessionHasErrors('explanation');
        }

        $this->assertSame('pending', $article->fresh()->status);

        $this->assertDatabaseMissing('article_events', [
            'article_id' => $article->id,
            'event_type' => 'returned_for_correction',
        ]);
    }

    public function test_return_requires_valid_lock_version(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $article = $this->createArticle($admin);

        foreach ([
                     [],
                     ['lock_version' => -1],
                     ['lock_version' => 'invalid'],
                 ] as $data) {
            $this->actingAs($admin)
                ->post(route('article.return', $article), array_merge(
                    ['explanation' => 'نیاز به اصلاح'],
                    $data
                ))
                ->assertSessionHasErrors('lock_version');
        }

        $this->assertSame('pending', $article->fresh()->status);
    }

    public function test_stale_version_prevents_return(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $article = $this->createArticle($admin);

        $staleVersion = $article->lock_version;

        $article->update(['title' => 'Modified Article']);

        $this->actingAs($admin)
            ->post(route('article.return', $article), [
                'lock_version' => $staleVersion,
                'explanation' => 'نیاز به اصلاح',
            ])
            ->assertSessionHas('error');

        $article->refresh();

        $this->assertSame('pending', $article->status);
        $this->assertSame($staleVersion + 1, $article->lock_version);

        $this->assertDatabaseMissing('article_events', [
            'article_id' => $article->id,
            'event_type' => 'returned_for_correction',
        ]);
    }

    public function test_author_cannot_return_article(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author);

        $this->actingAs($author)
            ->post(route('article.return', $article), [
                'lock_version' => $article->lock_version,
                'explanation' => 'نیاز به اصلاح',
            ])
            ->assertForbidden();
    }

    public function test_subscriber_cannot_return_article(): void
    {
        $subscriber = User::factory()->create(['role' => 'subscriber']);
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author);

        $this->actingAs($subscriber)
            ->post(route('article.return', $article), [
                'lock_version' => $article->lock_version,
                'explanation' => 'نیاز به اصلاح',
            ])
            ->assertForbidden();
    }

    public function test_guest_cannot_return_article(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author);

        $this->post(route('article.return', $article), [
            'lock_version' => $article->lock_version,
            'explanation' => 'نیاز به اصلاح',
        ])
            ->assertRedirect(route('login'));
    }

    public function test_admin_cannot_return_draft_article(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $article = $this->createArticle($admin, 'draft');

        $this->actingAs($admin)
            ->post(route('article.return', $article), [
                'lock_version' => $article->lock_version,
                'explanation' => 'نیاز به اصلاح',
            ])
            ->assertForbidden();
    }

    public function test_admin_cannot_return_published_article(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $article = $this->createArticle($admin, 'published');

        $this->actingAs($admin)
            ->post(route('article.return', $article), [
                'lock_version' => $article->lock_version,
                'explanation' => 'نیاز به اصلاح',
            ])
            ->assertForbidden();
    }

    public function test_admin_cannot_return_trashed_article(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $article = $this->createArticle($admin);
        $article->delete();

        $this->actingAs($admin)
            ->post(route('article.return', $article->id), [
                'lock_version' => $article->lock_version,
                'explanation' => 'نیاز به اصلاح',
            ])
            ->assertNotFound();

        $this->assertSoftDeleted($article);
    }

    public function test_return_rolls_back_when_event_creation_fails(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $article = $this->createArticle($admin);

        $originalVersion = $article->lock_version;

        DB::unprepared("
            CREATE TRIGGER fail_return_event
            BEFORE INSERT ON article_events
            WHEN NEW.event_type = 'returned_for_correction'
            BEGIN
                SELECT RAISE(ABORT, 'Simulated event failure');
            END;
        ");

        try {
            $response = $this->actingAs($admin)
                ->post(route('article.return', $article), [
                    'lock_version' => $originalVersion,
                    'explanation' => 'نیاز به اصلاح',
                ]);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS fail_return_event');
        }

        $response
            ->assertRedirect(route('article.show', $article))
            ->assertSessionHas(
                'error',
                'بازگرداندن مقاله برای اصلاح انجام نشد. لطفاً دوباره تلاش کنید.'
            )
            ->assertSessionMissing('success');

        $article->refresh();

        $this->assertSame('pending', $article->status);
        $this->assertSame($originalVersion, $article->lock_version);

        $this->assertDatabaseMissing('article_events', [
            'article_id' => $article->id,
            'event_type' => 'returned_for_correction',
        ]);
    }
}
