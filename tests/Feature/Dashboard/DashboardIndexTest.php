<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use App\Models\dashboard\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_latest_five_articles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $baseTime = now()->subDay();

        foreach (range(1, 6) as $number) {
            $this->travelTo($baseTime->copy()->addMinutes($number));

            Article::create([
                'author_id' => $admin->id,
                'title' => "Article {$number}",
                'slug' => "article-{$number}",
                'status' => 'draft',
            ]);
        }

        $this->travelBack();

        $this->actingAs($admin)
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertViewHas('latestArticles', fn ($articles) =>
                $articles->count() === 5
            )
            ->assertSee('Article 6')
            ->assertDontSee('Article 1');
    }

    public function test_author_sees_only_own_articles(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $other = User::factory()->create(['role' => 'author']);

        Article::create([
            'author_id' => $author->id,
            'title' => 'My article',
            'slug' => 'my-article',
            'status' => 'draft',
        ]);

        Article::create([
            'author_id' => $other->id,
            'title' => 'Other article',
            'slug' => 'other-article',
            'status' => 'draft',
        ]);

        $this->actingAs($author)
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertSee('My article')
            ->assertDontSee('Other article');
    }

    public function test_subscriber_cannot_access_dashboard(): void
    {
        $subscriber = User::factory()->create(['role' => 'subscriber']);

        $this->actingAs($subscriber)
            ->get(route('dashboard.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard.index'))
            ->assertRedirect(route('login'));
    }

    public function test_dashboard_does_not_show_trashed_articles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $article = Article::create([
            'author_id' => $admin->id,
            'title' => 'Deleted article',
            'slug' => 'deleted-article',
            'status' => 'draft',
        ]);

        $article->delete();

        $this->actingAs($admin)
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertDontSee('Deleted article');
    }

    public function test_dashboard_navigation_uses_article_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertSee('href="' . route('article.index') . '"', false)
            ->assertSee('href="' . route('article.create') . '"', false)
            ->assertDontSee('dashboard-post-list.html')
            ->assertDontSee('dashboard-post-create.html')
            ->assertDontSee('dashboard-post-edit.html');
    }

    public function test_legacy_news_routes_redirect_to_article_index(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/dashboard/news')
            ->assertRedirect(route('article.index'));

        $this->get('/dashboard/news/edit')
            ->assertRedirect(route('article.index'));
    }

    public function test_subscriber_cannot_access_legacy_news_routes(): void
    {
        $subscriber = User::factory()->create(['role' => 'subscriber']);

        $this->actingAs($subscriber)
            ->get('/dashboard/news')
            ->assertForbidden();

        $this->get('/dashboard/news/edit')
            ->assertForbidden();
    }
}
