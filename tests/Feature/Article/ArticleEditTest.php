<?php

namespace Tests\Feature\Article;

use App\Models\User;
use App\Models\dashboard\Article;
use App\Models\dashboard\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleEditTest extends TestCase
{
    use RefreshDatabase;

    private function createArticle(User $author): Article
    {
        return Article::create([
            'author_id' => $author->id,
            'slug' => 'edit-test-' . uniqid(),
            'status' => 'draft',
        ]);
    }

    public function test_author_can_edit_own_article(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author);

        $this->actingAs($author)
            ->get(route('article.edit', $article))
            ->assertOk()
            ->assertViewIs('dashboard.articles.edit')
            ->assertViewHas('article', fn ($value) =>
                $value->id === $article->id
            );
    }

    public function test_author_cannot_edit_another_authors_article(): void
    {
        $owner = User::factory()->create(['role' => 'author']);
        $other = User::factory()->create(['role' => 'author']);

        $article = $this->createArticle($owner);

        $this->actingAs($other)
            ->get(route('article.edit', $article))
            ->assertForbidden();
    }

    public function test_admin_can_edit_another_authors_article(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);

        $article = $this->createArticle($author);

        $this->actingAs($admin)
            ->get(route('article.edit', $article))
            ->assertOk();
    }

    public function test_subscriber_cannot_edit_article(): void
    {
        $subscriber = User::factory()->create(['role' => 'subscriber']);
        $author = User::factory()->create(['role' => 'author']);

        $article = $this->createArticle($author);

        $this->actingAs($subscriber)
            ->get(route('article.edit', $article))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author);

        $this->get(route('article.edit', $article))
            ->assertRedirect(route('login'));
    }

    public function test_trashed_article_cannot_be_edited(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author);

        $article->delete();

        // Soft-deleted articles are excluded from implicit route binding.
        $this->actingAs($author)
            ->get(route('article.edit', $article))
            ->assertNotFound();
    }

    public function test_edit_form_receives_categories(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author);

        $this->actingAs($author)
            ->get(route('article.edit', $article))
            ->assertOk()
            ->assertViewHas('categories', fn ($categories) =>
                $categories->count() === Category::count()
            );
    }

    public function test_edit_form_displays_existing_article_values(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $article = Article::create([
            'author_id' => $author->id,
            'title' => 'Original title',
            'slug' => 'original-slug',
            'body' => '<p>Original body</p>',
            'brief' => 'Original brief',
            'tags' => 'Laravel, PHP',
            'status' => 'draft',
        ]);

        $this->actingAs($author)
            ->get(route('article.edit', $article))
            ->assertOk()
            ->assertSee('value="Original title"', false)
            ->assertSee('value="original-slug"', false)
            ->assertSee('&lt;p&gt;Original body&lt;/p&gt;', false)
            ->assertSee('Original brief')
            ->assertSee('Laravel, PHP');
    }

    public function test_edit_form_preserves_old_values_after_validation_failure(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $article = Article::create([
            'author_id' => $author->id,
            'title' => 'Original title',
            'slug' => 'original-slug',
            'status' => 'draft',
        ]);

        $this->actingAs($author)
            ->withSession([
                '_old_input' => [
                    'title' => 'Edited title',
                    'slug' => 'edited-slug',
                    'body' => '<p>Edited body</p>',
                    'brief' => 'Edited brief',
                    'tags' => 'PHP, Testing',
                ],
            ])
            ->get(route('article.edit', $article))
            ->assertOk()
            ->assertSee('value="Edited title"', false)
            ->assertSee('value="edited-slug"', false)
            ->assertSee('&lt;p&gt;Edited body&lt;/p&gt;', false)
            ->assertSee('Edited brief')
            ->assertSee('PHP, Testing')
            ->assertDontSee('value="Original title"', false);
    }
}
