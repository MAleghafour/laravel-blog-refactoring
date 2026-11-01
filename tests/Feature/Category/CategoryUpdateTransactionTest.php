<?php

namespace Tests\Feature\Category;

use App\Models\dashboard\Category;
use App\Models\dashboard\CategorySlugHistory;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PDOException;
use Tests\TestCase;

class CategoryUpdateTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_failure_rolls_back_category_update(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create([
            'name' => 'Technology',
            'slug' => 'technology',
            'description' => 'Original description.',
            'icon' => '💻',
        ]);

        CategorySlugHistory::creating(function (): void {
            throw new \RuntimeException('Simulated history failure.');
        });

        $this->withoutExceptionHandling();

        $exception = null;

        try {
            $this->actingAs($admin)->put(
                route('category.update', $category->id),
                [
                    'name' => 'Updated Technology',
                    'slug' => 'modern-technology',
                    'description' => 'Updated description.',
                    'icon' => '🚀',
                    'lock_version' => $category->lock_version,
                ]
            );
        } catch (\RuntimeException $caught) {
            $exception = $caught;
        }

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame('Simulated history failure.', $exception->getMessage());

        $category->refresh();

        $this->assertSame('Technology', $category->name);
        $this->assertSame('technology', $category->slug);
        $this->assertSame('Original description.', $category->description);
        $this->assertSame('💻', $category->icon);
        $this->assertSame(0, $category->lock_version);
        $this->assertDatabaseCount('category_slug_histories', 0);
    }

    public function test_known_trigger_failure_returns_error_and_rolls_back(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create([
            'name' => 'Technology',
            'slug' => 'technology',
            'description' => 'Original description.',
            'icon' => '💻',
        ]);

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

        $this->actingAs($admin)
            ->put(route('category.update', $category->id), [
                'name' => 'Updated Technology',
                'slug' => 'modern-technology',
                'description' => 'Updated description.',
                'icon' => '🚀',
                'lock_version' => $category->lock_version,
            ])
            ->assertRedirect(route('category.index'))
            ->assertSessionHas('category_form_edit_id', $category->id)
            ->assertSessionHas(
                'error',
                'نام یا نامک انتخاب‌شده قبلاً استفاده شده است.'
            );

        $category->refresh();

        $this->assertSame('Technology', $category->name);
        $this->assertSame('technology', $category->slug);
        $this->assertSame('Original description.', $category->description);
        $this->assertSame('💻', $category->icon);
        $this->assertSame(0, $category->lock_version);
        $this->assertDatabaseCount('category_slug_histories', 0);
    }
}
