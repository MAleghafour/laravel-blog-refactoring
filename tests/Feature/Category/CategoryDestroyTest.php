<?php

namespace Tests\Feature\Category;

use App\Models\dashboard\Article;
use App\Models\dashboard\Category;
use App\Models\dashboard\CategorySlugHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryDestroyTest extends TestCase
{
    use RefreshDatabase;

    private const SUCCESS_MESSAGE = 'دسته‌بندی با موفقیت حذف شد و نامک آن محفوظ ماند.';

    private const ARTICLE_MESSAGE = 'این دسته‌بندی دارای مقاله است و قابل حذف نیست.';

    private const STALE_MESSAGE = 'دسته‌بندی پس از نمایش صفحه تغییر کرده است. لطفاً صفحه را دوباره بارگذاری کنید.';

    private const MISSING_MESSAGE = 'دسته‌بندی مورد نظر یافت نشد یا قبلاً حذف شده است.';

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function createCategory(
        string $name = 'Technology',
        string $slug = 'technology'
    ): Category {
        return Category::create([
            'name' => $name,
            'slug' => $slug,
            'description' => 'Technology category.',
            'icon' => '💻',
        ]);
    }

    private function createArticle(Category $category): Article
    {
        $author = User::factory()->create(['role' => 'author']);

        return Article::create([
            'author_id' => $author->id,
            'category_id' => $category->id,
            'title' => 'Category article',
            'slug' => 'category-article-'.$category->id,
            'status' => 'draft',
        ]);
    }

    public function test_admin_can_delete_empty_category_and_preserve_all_slugs(): void
    {
        $category = $this->createCategory();

        foreach (['old-technology', 'older-technology'] as $slug) {
            CategorySlugHistory::create([
                'category_id' => $category->id,
                'slug' => $slug,
            ]);
        }

        $this->actingAs($this->admin())
            ->delete(route('category.destroy', $category->id), [
                'lock_version' => $category->lock_version,
            ])
            ->assertRedirect(route('category.index'))
            ->assertSessionHas('success', self::SUCCESS_MESSAGE);

        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);

        foreach (['technology', 'old-technology', 'older-technology'] as $slug) {
            $this->assertDatabaseHas('category_slug_histories', [
                'category_id' => $category->id,
                'slug' => $slug,
            ]);
        }

        $this->assertDatabaseCount('category_slug_histories', 3);
    }

    public function test_active_article_prevents_category_deletion(): void
    {
        $category = $this->createCategory();
        $article = $this->createArticle($category);

        $this->actingAs($this->admin())
            ->delete(route('category.destroy', $category->id), [
                'lock_version' => $category->lock_version,
            ])
            ->assertRedirect(route('category.index'))
            ->assertSessionHas('error', self::ARTICLE_MESSAGE);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('articles', ['id' => $article->id]);
        $this->assertDatabaseCount('category_slug_histories', 0);
    }

    public function test_soft_deleted_article_prevents_category_deletion(): void
    {
        $category = $this->createCategory();
        $article = $this->createArticle($category);
        $article->delete();

        $this->actingAs($this->admin())
            ->delete(route('category.destroy', $category->id), [
                'lock_version' => $category->lock_version,
            ])
            ->assertRedirect(route('category.index'))
            ->assertSessionHas('error', self::ARTICLE_MESSAGE);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertSoftDeleted($article);
        $this->assertDatabaseCount('category_slug_histories', 0);
    }

    public function test_stale_version_prevents_category_deletion(): void
    {
        $category = $this->createCategory();
        $staleVersion = $category->lock_version;

        $category->update(['description' => 'Concurrent change.']);

        $this->actingAs($this->admin())
            ->delete(route('category.destroy', $category->id), [
                'lock_version' => $staleVersion,
            ])
            ->assertRedirect(route('category.index'))
            ->assertSessionHas('error', self::STALE_MESSAGE);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'description' => 'Concurrent change.',
            'lock_version' => 1,
        ]);
        $this->assertDatabaseCount('category_slug_histories', 0);
    }

    public function test_missing_or_invalid_lock_version_is_rejected(): void
    {
        foreach ([
            [],
            ['lock_version' => 'invalid'],
            ['lock_version' => -1],
        ] as $data) {
            $category = $this->createCategory(
                'Category '.count($data).uniqid(),
                'category-'.uniqid()
            );

            $this->actingAs($this->admin())
                ->from(route('category.index'))
                ->delete(route('category.destroy', $category->id), $data)
                ->assertRedirect(route('category.index'))
                ->assertSessionHasErrors('lock_version');

            $this->assertDatabaseHas('categories', ['id' => $category->id]);
        }

        $this->assertDatabaseCount('category_slug_histories', 0);
    }

    public function test_missing_category_returns_friendly_error(): void
    {
        $this->actingAs($this->admin())
            ->delete(route('category.destroy', 999999), [
                'lock_version' => 0,
            ])
            ->assertRedirect(route('category.index'))
            ->assertSessionHas('error', self::MISSING_MESSAGE);
    }

    public function test_already_deleted_category_returns_friendly_error(): void
    {
        $category = $this->createCategory();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete(route('category.destroy', $category->id), [
                'lock_version' => $category->lock_version,
            ])
            ->assertSessionHas('success', self::SUCCESS_MESSAGE);

        $this->actingAs($admin)
            ->delete(route('category.destroy', $category->id), [
                'lock_version' => $category->lock_version,
            ])
            ->assertRedirect(route('category.index'))
            ->assertSessionHas('error', self::MISSING_MESSAGE);

        $this->assertDatabaseCount('category_slug_histories', 1);
    }

    public function test_author_and_subscriber_cannot_delete_category(): void
    {
        foreach (['author', 'subscriber'] as $role) {
            $category = $this->createCategory(
                ucfirst($role).' category',
                $role.'-category'
            );
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->delete(route('category.destroy', $category->id), [
                    'lock_version' => $category->lock_version,
                ])
                ->assertForbidden();

            $this->assertDatabaseHas('categories', ['id' => $category->id]);
        }

        $this->assertDatabaseCount('category_slug_histories', 0);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $category = $this->createCategory();

        $this->delete(route('category.destroy', $category->id), [
            'lock_version' => $category->lock_version,
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseCount('category_slug_histories', 0);
    }

    public function test_index_delete_trigger_contains_current_lock_version(): void
    {
        $category = $this->createCategory();
        $category->update(['description' => 'Changed description.']);

        $this->actingAs($this->admin())
            ->get(route('category.index'))
            ->assertOk()
            ->assertSee('data-lock-version="1"', false)
            ->assertSee('name="lock_version" value=""', false);
    }
}
