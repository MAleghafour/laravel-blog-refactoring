<?php

namespace Tests\Feature\Article;

use App\Models\User;
use App\Models\dashboard\Article;
use App\Models\dashboard\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function createArticle(User $author, string $status): Article
    {
        $category = Category::create([
            'name' => 'Technology',
            'slug' => 'technology-' . uniqid(),
        ]);

        return Article::create([
            'author_id' => $author->id,
            'title' => 'Original title',
            'slug' => 'original-' . uniqid(),
            'body' => '<p>' . str_repeat('Original content ', 5) . '</p>',
            'category_id' => $category->id,
            'status' => $status,
        ]);
    }

    private function updateData(Article $article): array
    {
        return [
            'title' => 'Updated title',
            'slug' => $article->slug,
            'body' => '<p>' . str_repeat('Updated content ', 5) . '</p>',
            'category_id' => $article->category_id,
            'lock_version' => $article->lock_version,
        ];
    }

    public function test_author_update_keeps_draft_status(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author, 'draft');

        $this->actingAs($author)
            ->put(route('article.update', $article), $this->updateData($article))
            ->assertRedirect(route('article.show', $article))
            ->assertSessionHas('success');

        $article->refresh();

        $this->assertSame('draft', $article->status);
        $this->assertSame('Updated title', $article->title);
        $this->assertSame(1, $article->lock_version);
    }

    public function test_author_update_keeps_pending_status_and_queue_position(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author, 'pending');
        $article->update(['submitted_for_review_at' => now()->subDay()]);

        $submittedAt = $article->submitted_for_review_at;
        $data = $this->updateData($article);

        $this->actingAs($author)
            ->put(route('article.update', $article), $data)
            ->assertSessionHas('success');

        $article->refresh();

        $this->assertSame('pending', $article->status);
        $this->assertTrue(
            $submittedAt->equalTo($article->submitted_for_review_at)
        );
    }

    public function test_author_update_of_published_article_requires_review(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author, 'published');

        $this->actingAs($author)
            ->put(route('article.update', $article), $this->updateData($article))
            ->assertSessionHas('success');

        $article->refresh();

        $this->assertSame('pending', $article->status);
        $this->assertNotNull($article->submitted_for_review_at);
    }

    public function test_stale_lock_version_prevents_update(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author, 'draft');

        $data = $this->updateData($article);
        $data['lock_version']++;

        $this->actingAs($author)
            ->put(route('article.update', $article), $data)
            ->assertRedirect(route('article.edit', $article))
            ->assertSessionHas('error');

        $this->assertSame('Original title', $article->fresh()->title);
    }

    public function test_admin_can_change_article_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $article = $this->createArticle($admin, 'draft');

        $this->actingAs($admin)
            ->put(route('article.update', $article), [
                ...$this->updateData($article),
                'status' => 'pending',
            ])
            ->assertSessionHas('success');

        $this->assertSame('pending', $article->fresh()->status);
        $this->assertNotNull($article->fresh()->submitted_for_review_at);
    }

    public function test_admin_edit_of_another_authors_article_records_event(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author, 'draft');

        $this->actingAs($admin)
            ->put(route('article.update', $article), [
                ...$this->updateData($article),
                'explanation' => 'Corrected metadata.',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('article_events', [
            'article_id' => $article->id,
            'actor_id' => $admin->id,
            'event_type' => 'admin_edited',
            'explanation' => 'Corrected metadata.',
        ]);
    }

    public function test_body_is_sanitized_on_update(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author, 'draft');

        $data = $this->updateData($article);
        $data['body'] = '<p>Safe</p><script>alert(1)</script>';

        $this->actingAs($author)
            ->put(route('article.update', $article), $data)
            ->assertSessionHas('success');

        $body = $article->fresh()->body;

        $this->assertStringContainsString('<p>Safe</p>', $body);
        $this->assertStringNotContainsString('<script', $body);
    }

    public function test_author_editing_pending_article_does_not_create_event(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author, 'pending');

        $this->actingAs($author)
            ->put(route('article.update', $article), $this->updateData($article))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('article_events', 0);
    }

    public function test_author_editing_published_article_records_resubmission(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author, 'published');

        $this->actingAs($author)
            ->put(route('article.update', $article), $this->updateData($article))
            ->assertSessionHas('success');

        $this->assertSame('pending', $article->fresh()->status);

        $this->assertDatabaseHas('article_events', [
            'article_id' => $article->id,
            'actor_id' => $author->id,
            'event_type' => 'submitted_for_review',
        ]);

        $this->assertSame(1, $article->events()->count());
    }

    public function test_published_article_keeps_first_publication_date_after_author_edit(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author, 'published');

        $article->update([
            'first_published_at' => now()->subDays(3),
        ]);

        $publishedAt = $article->first_published_at;

        $this->actingAs($author)
            ->put(route('article.update', $article), $this->updateData($article))
            ->assertSessionHas('success');

        $article->refresh();

        $this->assertSame('pending', $article->status);
        $this->assertTrue(
            $publishedAt->equalTo($article->first_published_at)
        );
    }

    public function test_admin_publishing_article_sets_first_publication_date_only_once(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $article = $this->createArticle($admin, 'draft');

        $this->actingAs($admin)
            ->put(route('article.update', $article), [
                ...$this->updateData($article),
                'status' => 'published',
            ])
            ->assertSessionHas('success');

        $article->refresh();

        $this->assertSame('published', $article->status);
        $this->assertNotNull($article->first_published_at);

        $publishedAt = $article->first_published_at;

        $this->actingAs($admin)
            ->put(route('article.update', $article), [
                ...$this->updateData($article),
                'status' => 'published',
            ])
            ->assertSessionHas('success');

        $this->assertTrue(
            $publishedAt->equalTo($article->fresh()->first_published_at)
        );
    }

    public function test_admin_status_change_records_one_event(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $article = $this->createArticle($admin, 'draft');

        $this->actingAs($admin)
            ->put(route('article.update', $article), [
                ...$this->updateData($article),
                'status' => 'pending',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('article_events', [
            'article_id' => $article->id,
            'actor_id' => $admin->id,
            'event_type' => 'status_changed',
        ]);

        $this->assertSame(1, $article->events()->count());
    }

    public function test_stale_published_article_edit_does_not_create_event(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author, 'published');

        $data = $this->updateData($article);
        $data['lock_version']++;

        $this->actingAs($author)
            ->put(route('article.update', $article), $data)
            ->assertRedirect(route('article.edit', $article))
            ->assertSessionHas('error');

        $this->assertSame('published', $article->fresh()->status);
        $this->assertDatabaseCount('article_events', 0);
    }
}
