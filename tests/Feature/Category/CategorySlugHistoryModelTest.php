<?php

namespace Tests\Feature\Category;

use App\Models\dashboard\Category;
use App\Models\dashboard\CategorySlugHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorySlugHistoryModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_can_create_and_read_slug_history(): void
    {
        $category = Category::create([
            'name' => 'Technology',
            'slug' => 'technology',
        ]);

        $history = $category->slugHistories()->create([
            'slug' => 'legacy-technology',
        ]);

        $this->assertInstanceOf(CategorySlugHistory::class, $history);
        $this->assertSame($category->id, $history->category_id);
        $this->assertTrue(
            $category->fresh()->slugHistories->contains($history)
        );

        $category->delete();

        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
        $this->assertDatabaseHas('category_slug_histories', [
            'id' => $history->id,
            'category_id' => $category->id,
            'slug' => 'legacy-technology',
        ]);
    }
}
