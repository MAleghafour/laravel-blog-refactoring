<?php

namespace Tests\Feature\Database;

use App\Models\dashboard\Article;
use App\Models\dashboard\Category;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_many_articles(): void
    {
        $user = User::factory()->create();
        $category = Category::create([
            'name' => 'Technology',
            'slug' => 'technology',
            'description' => null,
            'icon' => '💻',
        ]);

        $article = Article::create([
            'title' => 'Test Article',
            'type' => 'article',
            'body' => 'Test body',
            'slug' => 'test-article',
            'brief' => null,
            'thumbnail_small' => null,
            'thumbnail_medium' => null,
            'thumbnail_large' => null,
            'tags' => null,
            'author_id' => $user->id,
            'category_id' => $category->id,
            'status' => 'review',
        ]);

        $this->assertTrue(
            $user->articles->contains($article)
        );
    }

    public function test_article_belongs_to_author(): void
    {
        $user = User::factory()->create();
        $category = Category::create([
            'name' => 'Technology',
            'slug' => 'technology',
            'description' => null,
            'icon' => '💻',
        ]);

        $article = Article::create([
            'title' => 'Test Article',
            'type' => 'article',
            'body' => 'Test body',
            'slug' => 'test-article',
            'brief' => null,
            'thumbnail_small' => null,
            'thumbnail_medium' => null,
            'thumbnail_large' => null,
            'tags' => null,
            'author_id' => $user->id,
            'category_id' => $category->id,
            'status' => 'review',
        ]);

        $this->assertTrue(
            $article->author->is($user)
        );
    }

    public function test_article_belongs_to_category(): void
    {
        $user = User::factory()->create();
        $category = Category::create([
            'name' => 'Technology',
            'slug' => 'technology',
            'description' => null,
            'icon' => '💻',
        ]);

        $article = Article::create([
            'title' => 'Test Article',
            'type' => 'article',
            'body' => 'Test body',
            'slug' => 'test-article',
            'brief' => null,
            'thumbnail_small' => null,
            'thumbnail_medium' => null,
            'thumbnail_large' => null,
            'tags' => null,
            'author_id' => $user->id,
            'category_id' => $category->id,
            'status' => 'review',
        ]);

        $this->assertTrue(
            $article->category->is($category)
        );
    }

    public function test_category_has_many_articles(): void
    {
        $user = User::factory()->create();
        $category = Category::create([
            'name' => 'Technology',
            'slug' => 'technology',
            'description' => null,
            'icon' => '💻',
        ]);

        $article = Article::create([
            'title' => 'Test Article',
            'type' => 'article',
            'body' => 'Test body',
            'slug' => 'test-article',
            'brief' => null,
            'thumbnail_small' => null,
            'thumbnail_medium' => null,
            'thumbnail_large' => null,
            'tags' => null,
            'author_id' => $user->id,
            'category_id' => $category->id,
            'status' => 'review',
        ]);

        $this->assertTrue(
            $category->articles->contains($article)
        );
    }

    public function test_deleting_category_with_articles_is_restricted(): void
    {
        $user = User::factory()->create();
        $category = Category::create([
            'name' => 'Technology',
            'slug' => 'technology',
            'description' => null,
            'icon' => '💻',
        ]);

        $article = Article::create([
            'title' => 'Test Article',
            'type' => 'article',
            'body' => 'Test body',
            'slug' => 'test-article',
            'brief' => null,
            'thumbnail_small' => null,
            'thumbnail_medium' => null,
            'thumbnail_large' => null,
            'tags' => null,
            'author_id' => $user->id,
            'category_id' => $category->id,
            'status' => 'review',
        ]);

        $article->delete();

        $deletionWasRestricted = false;

        try {
            $category->delete();
        } catch (QueryException) {
            $deletionWasRestricted = true;
        }

        $this->assertTrue($deletionWasRestricted);
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
        ]);
        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'category_id' => $category->id,
        ]);
        $this->assertSoftDeleted($article);
    }
}
