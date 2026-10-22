<?php

namespace Tests\Feature\Article;

use App\Jobs\SendArticleEditedMailJob;
use App\Mail\ArticleEditedMail;
use App\Models\User;
use App\Models\dashboard\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ArticleUpdateNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createArticle(User $author): Article
    {
        return Article::create([
            'author_id' => $author->id,
            'title' => 'Original title',
            'slug' => 'article-' . uniqid(),
            'status' => 'draft',
        ]);
    }

    private function data(Article $article): array
    {
        return [
            'title' => 'Updated title',
            'slug' => $article->slug,
            'lock_version' => $article->lock_version,
            'explanation' => 'Corrected article content.',
        ];
    }

    public function test_admin_edit_of_another_authors_article_dispatches_notification(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author);

        $this->actingAs($admin)
            ->put(route('article.update', $article), $this->data($article))
            ->assertSessionHas('success');

        $this->assertSame('Updated title', $article->fresh()->title);

        Queue::assertPushed(
            SendArticleEditedMailJob::class,
            function (SendArticleEditedMailJob $job) use ($author, $article) {
                return $job->recipientEmail === $author->email
                    && $job->articleId === $article->id
                    && $job->articleTitle === 'Updated title'
                    && $job->explanation === 'Corrected article content.';
            }
        );

        Queue::assertPushed(SendArticleEditedMailJob::class, 1);
    }

    public function test_admin_edit_of_own_article_does_not_dispatch_notification(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $article = $this->createArticle($admin);

        $this->actingAs($admin)
            ->put(route('article.update', $article), [
                'title' => 'Updated title',
                'slug' => $article->slug,
                'lock_version' => $article->lock_version,
            ])
            ->assertSessionHas('success');

        Queue::assertNotPushed(SendArticleEditedMailJob::class);
    }

    public function test_stale_update_does_not_dispatch_notification(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author);

        $data = $this->data($article);
        $data['lock_version']++;

        $this->actingAs($admin)
            ->put(route('article.update', $article), $data)
            ->assertSessionHas('error');

        Queue::assertNotPushed(SendArticleEditedMailJob::class);
    }

    public function test_job_sends_mail_to_article_author(): void
    {
        Mail::fake();

        $job = new SendArticleEditedMailJob(
            'author@example.com',
            'Updated title',
            'Corrected article content.',
            42
        );

        $job->handle();

        Mail::assertSent(
            ArticleEditedMail::class,
            function (ArticleEditedMail $mail) {
                return $mail->hasTo('author@example.com')
                    && $mail->articleTitle === 'Updated title'
                    && $mail->articleId === 42;
            }
        );
    }
}
