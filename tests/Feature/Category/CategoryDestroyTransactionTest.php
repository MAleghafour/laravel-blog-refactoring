<?php

namespace Tests\Feature\Category;

use App\Models\dashboard\Article;
use App\Models\dashboard\Category;
use App\Models\dashboard\CategorySlugHistory;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDOException;
use RuntimeException;
use Tests\TestCase;

class CategoryDestroyTransactionTest extends TestCase
{
    use RefreshDatabase;

    private const ARTICLE_MESSAGE = 'این دسته‌بندی دارای مقاله است و قابل حذف نیست.';

    private const SLUG_MESSAGE = 'حذف دسته‌بندی به دلیل تداخل نامک انجام نشد.';

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function createCategory(): Category
    {
        return Category::create([
            'name' => 'Technology',
            'slug' => 'technology',
            'description' => 'Technology category.',
            'icon' => '💻',
        ]);
    }

    public function test_history_failure_rolls_back_delete_and_propagates_unexpected_exception(): void
    {
        $category = $this->createCategory();

        CategorySlugHistory::creating(function (): void {
            throw new RuntimeException('Simulated history failure.');
        });

        $this->withoutExceptionHandling();
        $exception = null;

        try {
            $this->actingAs($this->admin())
                ->delete(route('category.destroy', $category->id), [
                    'lock_version' => $category->lock_version,
                ]);
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertSame('Simulated history failure.', $exception->getMessage());
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'slug' => 'technology',
            'lock_version' => 0,
        ]);
        $this->assertDatabaseCount('category_slug_histories', 0);
    }

    public function test_known_history_constraint_failure_returns_error_and_rolls_back(): void
    {
        $category = $this->createCategory();

        CategorySlugHistory::creating(function (): void {
            throw new QueryException(
                'sqlite',
                'insert into category_slug_histories',
                [],
                new PDOException(
                    'category slug history conflicts with categories.slug'
                )
            );
        });

        $this->actingAs($this->admin())
            ->delete(route('category.destroy', $category->id), [
                'lock_version' => $category->lock_version,
            ])
            ->assertRedirect(route('category.index'))
            ->assertSessionHas('error', self::SLUG_MESSAGE);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'slug' => 'technology',
            'lock_version' => 0,
        ]);
        $this->assertDatabaseCount('category_slug_histories', 0);
    }

    public function test_foreign_key_race_is_handled_and_delete_is_rolled_back(): void
    {
        $category = $this->createCategory();
        $author = User::factory()->create(['role' => 'author']);
        $articleInserted = false;

        DB::listen(function (QueryExecuted $query) use (
            &$articleInserted,
            $category,
            $author
        ): void {
            $sql = strtolower($query->sql);

            if (
                $articleInserted
                || ! str_contains($sql, 'select exists')
                || ! str_contains($sql, 'from "articles"')
            ) {
                return;
            }

            $articleInserted = true;

            Article::create([
                'author_id' => $author->id,
                'category_id' => $category->id,
                'title' => 'Concurrent article',
                'slug' => 'concurrent-article',
                'status' => 'draft',
            ]);
        });

        $this->actingAs($this->admin())
            ->delete(route('category.destroy', $category->id), [
                'lock_version' => $category->lock_version,
            ])
            ->assertRedirect(route('category.index'))
            ->assertSessionHas('error', self::ARTICLE_MESSAGE);

        $this->assertTrue($articleInserted);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseCount('articles', 0);
        $this->assertDatabaseCount('category_slug_histories', 0);
    }
}
