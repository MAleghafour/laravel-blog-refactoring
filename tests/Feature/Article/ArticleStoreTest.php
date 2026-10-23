<?php

namespace Tests\Feature\Article;

use App\Models\User;
use App\Models\dashboard\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Services\FileUploadService;
use Illuminate\Http\UploadedFile;

class ArticleStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_create_draft_with_server_controlled_fields(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $other = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)
            ->post(route('article.store'), [
                'title' => 'First Article',
                'slug' => 'My First Article',
                'body' => '<p>Article content</p>',
                'author_id' => $other->id,
                'status' => 'published',
            ])
            ->assertRedirect(route('article.index'))
            ->assertSessionHas('success');

        $article = Article::sole();

        $this->assertSame($author->id, $article->author_id);
        $this->assertSame('draft', $article->status);
        $this->assertSame('my-first-article', $article->slug);
    }

    public function test_admin_can_create_draft(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('article.store'), [
                'slug' => 'admin-draft',
            ])
            ->assertRedirect(route('article.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('articles', [
            'slug' => 'admin-draft',
            'author_id' => $admin->id,
            'status' => 'draft',
        ]);
    }

    public function test_subscriber_cannot_create_article(): void
    {
        $subscriber = User::factory()->create([
            'role' => 'subscriber',
        ]);

        $this->actingAs($subscriber)
            ->post(route('article.store'), [
                'slug' => 'subscriber-article',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('articles', 0);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->post(route('article.store'), [
            'slug' => 'guest-article',
        ])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('articles', 0);
    }

    public function test_body_html_is_sanitized_before_storage(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)
            ->post(route('article.store'), [
                'slug' => 'safe-article',
                'body' => '<p>Safe <strong>content</strong></p>'
                    . '<script>alert(1)</script>',
            ])
            ->assertRedirect(route('article.index'));

        $body = Article::sole()->body;

        $this->assertStringContainsString(
            '<strong>content</strong>',
            $body
        );
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('alert(1)', $body);
    }

    public function test_slug_is_required(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)
            ->post(route('article.store'), [
                'title' => 'Article without slug',
            ])
            ->assertSessionHasErrors('slug');

        $this->assertDatabaseCount('articles', 0);
    }

    public function test_duplicate_slug_is_rejected_even_when_article_is_trashed(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $article = Article::create([
            'author_id' => $author->id,
            'slug' => 'reserved-slug',
            'status' => 'draft',
        ]);

        $article->delete();

        $this->actingAs($author)
            ->post(route('article.store'), [
                'slug' => 'Reserved Slug',
            ])
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, Article::withTrashed()->count());
    }

    public function test_incomplete_draft_can_be_created(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)
            ->post(route('article.store'), [
                'slug' => 'incomplete-draft',
            ])
            ->assertRedirect(route('article.index'))
            ->assertSessionHas('success');

        $article = Article::sole();

        $this->assertNull($article->title);
        $this->assertNull($article->body);
        $this->assertSame('draft', $article->status);
    }

    public function test_uploaded_thumbnails_are_removed_when_article_insert_fails(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $filename = 'failed-store-' . uniqid() . '.jpg';

        $thumbnails = [
            'thumbnail_small' => $filename,
            'thumbnail_medium' => $filename,
            'thumbnail_large' => $filename,
        ];

        $paths = [];

        foreach (['small', 'medium', 'large'] as $size) {
            $directory = public_path("uploads/thumbnails/{$size}");

            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $path = "{$directory}/{$filename}";

            file_put_contents($path, 'test image');

            $paths[] = $path;
        }

        $uploadService = $this->mock(FileUploadService::class);

        $uploadService
            ->shouldReceive('fileUpload')
            ->once()
            ->andReturn($thumbnails);

        $uploadService
            ->shouldReceive('deleteExclusiveThumbnails')
            ->once()
            ->with($thumbnails)
            ->andReturnUsing(function () use ($paths) {
                foreach ($paths as $path) {
                    if (file_exists($path)) {
                        unlink($path);
                    }
                }
            });

        // Force article creation to fail using an invalid database column.
        \Illuminate\Support\Facades\DB::statement(
            "CREATE TRIGGER fail_article_insert
         BEFORE INSERT ON articles
         BEGIN
             SELECT RAISE(ABORT, 'Simulated database failure');
         END"
        );

        try {
            $this->actingAs($author)
                ->post(route('article.store'), [
                    'slug' => 'failed-store',
                    'thumbnail' => UploadedFile::fake()->image('test.jpg'),
                ])
                ->assertRedirect(route('article.create'))
                ->assertSessionHas('error');

            $this->assertDatabaseCount('articles', 0);

            foreach ($paths as $path) {
                $this->assertFileDoesNotExist($path);
            }
        } finally {
            foreach ($paths as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }
    }

    public function test_creating_draft_records_event(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)
            ->post(route('article.store'), [
                'title' => 'New article',
                'slug' => 'new-article',
            ])
            ->assertSessionHas('success');

        $article = Article::where('slug', 'new-article')->firstOrFail();

        $this->assertDatabaseHas('article_events', [
            'article_id' => $article->id,
            'actor_id' => $author->id,
            'event_type' => 'created',
        ]);
    }
}
