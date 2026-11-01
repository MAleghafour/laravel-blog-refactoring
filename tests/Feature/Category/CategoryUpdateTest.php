<?php

namespace Tests\Feature\Category;

use App\Models\dashboard\Category;
use App\Models\dashboard\CategorySlugHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryUpdateTest extends TestCase
{
    use RefreshDatabase;

    private const SUCCESS_MESSAGE = 'دسته‌بندی با موفقیت به‌روزرسانی شد.';

    private const UNCHANGED_MESSAGE = 'تغییری برای ذخیره وجود نداشت.';

    private const STALE_MESSAGE = 'دسته‌بندی پس از باز شدن فرم تغییر کرده است. لطفاً صفحه را دوباره بارگذاری کنید.';

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
            'description' => 'Original description.',
            'icon' => '💻',
        ]);
    }

    private function validData(Category $category): array
    {
        return [
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'icon' => $category->icon,
            'lock_version' => $category->lock_version,
        ];
    }

    public function test_admin_can_update_category(): void
    {
        $category = $this->createCategory();
        $data = [
            ...$this->validData($category),
            'name' => 'Software',
            'description' => 'Updated description.',
            'icon' => '🚀',
        ];

        $this->actingAs($this->admin())
            ->put(route('category.update', $category->id), $data)
            ->assertRedirect(route('category.index'))
            ->assertSessionHas('success', self::SUCCESS_MESSAGE);

        $category->refresh();

        $this->assertSame('Software', $category->name);
        $this->assertSame('technology', $category->slug);
        $this->assertSame('Updated description.', $category->description);
        $this->assertSame('🚀', $category->icon);
        $this->assertSame(1, $category->lock_version);
    }

    public function test_changing_name_does_not_change_slug_or_create_history(): void
    {
        $category = $this->createCategory();
        $data = [
            ...$this->validData($category),
            'name' => 'Modern Technology',
        ];

        $this->actingAs($this->admin())
            ->put(route('category.update', $category->id), $data)
            ->assertRedirect(route('category.index'))
            ->assertSessionHas('success', self::SUCCESS_MESSAGE);

        $category->refresh();

        $this->assertSame('Modern Technology', $category->name);
        $this->assertSame('technology', $category->slug);
        $this->assertSame(1, $category->lock_version);
        $this->assertDatabaseCount('category_slug_histories', 0);
    }

    public function test_changing_slug_records_previous_slug_in_same_update(): void
    {
        $category = $this->createCategory();
        $data = [
            ...$this->validData($category),
            'slug' => 'modern-technology',
        ];

        $this->actingAs($this->admin())
            ->put(route('category.update', $category->id), $data)
            ->assertRedirect(route('category.index'))
            ->assertSessionHas('success', self::SUCCESS_MESSAGE);

        $category->refresh();

        $this->assertSame('modern-technology', $category->slug);
        $this->assertSame(1, $category->lock_version);
        $this->assertDatabaseHas('category_slug_histories', [
            'category_id' => $category->id,
            'slug' => 'technology',
        ]);
    }

    public function test_unchanged_update_checks_version_without_writing(): void
    {
        $category = $this->createCategory();
        $originalUpdatedAt = $category->updated_at;

        $this->actingAs($this->admin())
            ->put(
                route('category.update', $category->id),
                $this->validData($category)
            )
            ->assertRedirect(route('category.index'))
            ->assertSessionHas('success', self::UNCHANGED_MESSAGE);

        $category->refresh();

        $this->assertSame(0, $category->lock_version);
        $this->assertTrue($originalUpdatedAt->equalTo($category->updated_at));
        $this->assertDatabaseCount('category_slug_histories', 0);
    }

    public function test_stale_version_prevents_update(): void
    {
        $category = $this->createCategory();
        $staleVersion = $category->lock_version;

        $category->update(['description' => 'Concurrent description.']);

        $data = [
            ...$this->validData($category),
            'name' => 'Stale update',
            'lock_version' => $staleVersion,
        ];

        $this->actingAs($this->admin())
            ->put(route('category.update', $category->id), $data)
            ->assertRedirect(route('category.index'))
            ->assertSessionHas('category_form_edit_id', $category->id)
            ->assertSessionHas('error', self::STALE_MESSAGE);

        $category->refresh();

        $this->assertSame('Technology', $category->name);
        $this->assertSame('Concurrent description.', $category->description);
        $this->assertSame(1, $category->lock_version);
        $this->assertDatabaseCount('category_slug_histories', 0);
    }

    public function test_stale_version_is_rejected_for_unchanged_data(): void
    {
        $category = $this->createCategory();
        $staleVersion = $category->lock_version;

        $category->update(['description' => 'Concurrent description.']);
        $category->refresh();

        $data = [
            ...$this->validData($category),
            'lock_version' => $staleVersion,
        ];

        $this->actingAs($this->admin())
            ->put(route('category.update', $category->id), $data)
            ->assertRedirect(route('category.index'))
            ->assertSessionHas('category_form_edit_id', $category->id)
            ->assertSessionHas('error', self::STALE_MESSAGE);

        $category->refresh();

        $this->assertSame('Concurrent description.', $category->description);
        $this->assertSame(1, $category->lock_version);
        $this->assertDatabaseCount('category_slug_histories', 0);
    }

    public function test_historical_slug_is_rejected(): void
    {
        $category = $this->createCategory();

        CategorySlugHistory::create([
            'category_id' => $category->id,
            'slug' => 'legacy-technology',
        ]);

        $data = [
            ...$this->validData($category),
            'slug' => 'legacy-technology',
        ];

        $this->actingAs($this->admin())
            ->put(route('category.update', $category->id), $data)
            ->assertRedirect()
            ->assertSessionHasErrors('slug');

        $category->refresh();

        $this->assertSame('technology', $category->slug);
        $this->assertSame(0, $category->lock_version);
    }

    public function test_duplicate_name_is_rejected(): void
    {
        $category = $this->createCategory();
        $this->createCategory('Science', 'science');
        $data = [
            ...$this->validData($category),
            'name' => 'Science',
        ];

        $this->actingAs($this->admin())
            ->put(route('category.update', $category->id), $data)
            ->assertRedirect()
            ->assertSessionHasErrors('name');

        $this->assertSame('Technology', $category->fresh()->name);
        $this->assertSame(0, $category->fresh()->lock_version);
    }

    public function test_duplicate_current_slug_is_rejected(): void
    {
        $category = $this->createCategory();
        $this->createCategory('Science', 'science');
        $data = [
            ...$this->validData($category),
            'slug' => 'science',
        ];

        $this->actingAs($this->admin())
            ->put(route('category.update', $category->id), $data)
            ->assertRedirect()
            ->assertSessionHasErrors('slug');

        $this->assertSame('technology', $category->fresh()->slug);
        $this->assertSame(0, $category->fresh()->lock_version);
    }

    public function test_validation_error_preserves_edit_form_input(): void
    {
        $category = $this->createCategory();
        $this->createCategory('Science', 'science');
        $data = [
            ...$this->validData($category),
            'name' => 'Science',
            'slug' => 'attempted-slug',
            'description' => 'Attempted description.',
            'icon' => '🚀',
        ];
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('category.index'))
            ->put(route('category.update', $category->id), $data)
            ->assertRedirect(route('category.index'))
            ->assertSessionHasErrors('name')
            ->assertSessionHas('category_form_edit_id', $category->id)
            ->assertSessionHasInput($data);

        $this->actingAs($admin)
            ->get(route('category.index'))
            ->assertOk()
            ->assertSee('data-mode="edit"', false)
            ->assertSee(
                'action="'.route('category.update', $category->id).'"',
                false
            )
            ->assertSee('value="Science"', false)
            ->assertSee('value="attempted-slug"', false)
            ->assertSee('Attempted description.')
            ->assertSee('value="🚀" selected', false)
            ->assertSee(
                'name="lock_version" value="0"',
                false
            );
    }

    public function test_non_admin_cannot_update_category(): void
    {
        $category = $this->createCategory();
        $author = User::factory()->create(['role' => 'author']);
        $data = [
            ...$this->validData($category),
            'name' => 'Unauthorized update',
        ];

        $this->actingAs($author)
            ->put(route('category.update', $category->id), $data)
            ->assertForbidden();

        $this->assertSame('Technology', $category->fresh()->name);
        $this->assertSame(0, $category->fresh()->lock_version);
    }
}
