<?php

namespace Tests\Feature\Category;

use App\Models\dashboard\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryModelTest extends TestCase
{
    use RefreshDatabase;

    private function createCategory(): Category
    {
        return Category::create([
            'name' => 'Technology',
            'slug' => 'technology',
        ]);
    }

    public function test_category_lock_version_defaults_to_zero(): void
    {
        $category = $this->createCategory();

        $category->refresh();

        $this->assertSame(0, $category->lock_version);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'lock_version' => 0,
        ]);
    }

    public function test_lock_version_increments_when_category_is_updated(): void
    {
        $category = $this->createCategory();

        $category->refresh();

        $this->assertSame(0, $category->lock_version);

        $category->update([
            'name' => 'Updated Technology',
        ]);

        $category->refresh();

        $this->assertSame(1, $category->lock_version);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated Technology',
            'lock_version' => 1,
        ]);
    }

    public function test_lock_version_does_not_increment_without_changes(): void
    {
        $category = $this->createCategory();

        $category->refresh();

        $this->assertSame(0, $category->lock_version);

        $category->save();

        $this->assertSame(0, $category->fresh()->lock_version);

        $category->update([
            'name' => $category->name,
        ]);

        $this->assertSame(0, $category->fresh()->lock_version);
    }

    public function test_lock_version_cannot_be_mass_assigned(): void
    {
        $category = Category::create([
            'name' => 'Technology',
            'slug' => 'technology',
            'lock_version' => 10,
        ]);

        $this->assertSame(0, $category->fresh()->lock_version);

        $category->update([
            'name' => 'Updated Technology',
            'lock_version' => 10,
        ]);

        $this->assertSame(1, $category->fresh()->lock_version);
    }
}
