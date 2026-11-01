<?php

namespace Tests\Feature\Database;

use App\Models\dashboard\Category;
use App\Models\dashboard\CategorySlugHistory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class CategorySlugCrossTableConstraintTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = '2026_10_26_000000_enforce_category_slug_uniqueness_across_tables.php';

    private function createCategory(string $name, string $slug): Category
    {
        return Category::create([
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    private function migration(): Migration
    {
        return require database_path('migrations/'.self::MIGRATION);
    }

    private function queryException(callable $operation): QueryException
    {
        try {
            $operation();
        } catch (QueryException $exception) {
            return $exception;
        }

        $this->fail('Expected a database constraint violation.');
    }

    public function test_category_cannot_use_historical_slug_on_insert(): void
    {
        CategorySlugHistory::create([
            'category_id' => 100,
            'slug' => 'reserved-slug',
        ]);

        $exception = $this->queryException(fn () => $this->createCategory(
            'Technology',
            'reserved-slug'
        ));

        $this->assertStringContainsString(
            'category slug conflicts with category_slug_histories.slug',
            $exception->getMessage()
        );
        $this->assertDatabaseMissing('categories', [
            'slug' => 'reserved-slug',
        ]);
    }

    public function test_category_cannot_use_historical_slug_on_update(): void
    {
        $category = $this->createCategory('Technology', 'technology');

        CategorySlugHistory::create([
            'category_id' => $category->id,
            'slug' => 'reserved-slug',
        ]);

        $exception = $this->queryException(
            fn () => Category::query()
                ->whereKey($category->id)
                ->update(['slug' => 'reserved-slug'])
        );

        $this->assertStringContainsString(
            'category slug conflicts with category_slug_histories.slug',
            $exception->getMessage()
        );
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'slug' => 'technology',
        ]);
    }

    public function test_history_cannot_use_slug_owned_by_another_category(): void
    {
        $owner = $this->createCategory('Technology', 'technology');
        $other = $this->createCategory('Science', 'science');

        $exception = $this->queryException(
            fn () => CategorySlugHistory::create([
                'category_id' => $other->id,
                'slug' => $owner->slug,
            ])
        );

        $this->assertStringContainsString(
            'category slug history conflicts with categories.slug',
            $exception->getMessage()
        );
        $this->assertDatabaseMissing('category_slug_histories', [
            'category_id' => $other->id,
            'slug' => 'technology',
        ]);
    }

    public function test_history_cannot_be_updated_to_current_slug(): void
    {
        $category = $this->createCategory('Technology', 'technology');
        $history = CategorySlugHistory::create([
            'category_id' => $category->id,
            'slug' => 'legacy-technology',
        ]);

        $exception = $this->queryException(
            fn () => CategorySlugHistory::query()
                ->whereKey($history->id)
                ->update(['slug' => 'technology'])
        );

        $this->assertStringContainsString(
            'category slug history conflicts with categories.slug',
            $exception->getMessage()
        );
        $this->assertDatabaseHas('category_slug_histories', [
            'id' => $history->id,
            'slug' => 'legacy-technology',
        ]);
    }

    public function test_new_non_conflicting_slugs_are_accepted(): void
    {
        $category = $this->createCategory('Technology', 'technology');

        $history = CategorySlugHistory::create([
            'category_id' => $category->id,
            'slug' => 'legacy-technology',
        ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'slug' => 'technology',
        ]);
        $this->assertDatabaseHas('category_slug_histories', [
            'id' => $history->id,
            'slug' => 'legacy-technology',
        ]);
    }

    public function test_categories_slug_remains_unique_within_its_table(): void
    {
        $this->createCategory('Technology', 'technology');

        $exception = $this->queryException(fn () => $this->createCategory(
            'Science',
            'technology'
        ));

        $this->assertStringContainsString(
            'UNIQUE constraint failed: categories.slug',
            $exception->getMessage()
        );
    }

    public function test_history_slug_remains_unique_within_its_table(): void
    {
        CategorySlugHistory::create([
            'category_id' => 100,
            'slug' => 'legacy-technology',
        ]);

        $exception = $this->queryException(
            fn () => CategorySlugHistory::create([
                'category_id' => 200,
                'slug' => 'legacy-technology',
            ])
        );

        $this->assertStringContainsString(
            'UNIQUE constraint failed: category_slug_histories.slug',
            $exception->getMessage()
        );
    }

    public function test_migration_preserves_existing_non_conflicting_data(): void
    {
        $migration = $this->migration();
        $migration->down();

        $category = $this->createCategory('Technology', 'technology');
        $history = CategorySlugHistory::create([
            'category_id' => $category->id,
            'slug' => 'legacy-technology',
        ]);

        $migration->up();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'slug' => 'technology',
        ]);
        $this->assertDatabaseHas('category_slug_histories', [
            'id' => $history->id,
            'slug' => 'legacy-technology',
        ]);
    }

    public function test_migration_rejects_existing_cross_table_conflict(): void
    {
        $migration = $this->migration();
        $migration->down();

        $category = $this->createCategory('Technology', 'shared-slug');
        $history = CategorySlugHistory::create([
            'category_id' => $category->id,
            'slug' => 'shared-slug',
        ]);

        try {
            $migration->up();
            $this->fail('Expected the migration to reject conflicting data.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'slug "shared-slug" exists in both',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'slug' => 'shared-slug',
        ]);
        $this->assertDatabaseHas('category_slug_histories', [
            'id' => $history->id,
            'slug' => 'shared-slug',
        ]);
    }

    public function test_slug_can_be_changed_and_archived_in_one_transaction(): void
    {
        $category = $this->createCategory('Technology', 'technology');

        DB::transaction(function () use ($category) {
            $updated = Category::query()
                ->whereKey($category->id)
                ->update([
                    'slug' => 'modern-technology',
                    'lock_version' => DB::raw('lock_version + 1'),
                    'updated_at' => now(),
                ]);

            $this->assertSame(1, $updated);

            CategorySlugHistory::create([
                'category_id' => $category->id,
                'slug' => 'technology',
            ]);
        });

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'slug' => 'modern-technology',
            'lock_version' => 1,
        ]);
        $this->assertDatabaseHas('category_slug_histories', [
            'category_id' => $category->id,
            'slug' => 'technology',
        ]);
    }
}
