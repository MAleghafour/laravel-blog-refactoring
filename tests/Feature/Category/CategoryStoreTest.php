<?php

namespace Tests\Feature\Category;

use App\Models\dashboard\Category;
use App\Models\dashboard\CategorySlugHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class CategoryStoreTest extends TestCase
{
    use RefreshDatabase;

    private const SUCCESS_MESSAGE = 'دسته‌بندی با موفقیت ایجاد شد.';

    private const CONSTRAINT_MESSAGE = 'نام یا نامک انتخاب‌شده قبلاً استفاده شده است.';

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function validData(): array
    {
        return [
            'name' => 'Technology',
            'slug' => 'technology',
            'description' => 'Technology category.',
            'icon' => '🏆',
        ];
    }

    private function createCategory(
        string $name = 'Existing Category',
        string $slug = 'existing-category'
    ): Category {
        return Category::create([
            'name' => $name,
            'slug' => $slug,
            'description' => 'Existing description.',
            'icon' => '💻',
        ]);
    }

    public function test_admin_can_create_category(): void
    {
        $this->actingAs($this->admin())
            ->post(route('category.index'), $this->validData())
            ->assertRedirect(route('category.index'))
            ->assertSessionHas('success', self::SUCCESS_MESSAGE);

        $this->assertDatabaseHas('categories', [
            'name' => 'Technology',
            'slug' => 'technology',
            'description' => 'Technology category.',
            'icon' => '🏆',
            'lock_version' => 0,
        ]);
        $this->assertDatabaseCount('category_slug_histories', 0);
    }

    public function test_author_and_subscriber_cannot_create_category(): void
    {
        foreach (['author', 'subscriber'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->post(route('category.index'), $this->validData())
                ->assertForbidden();
        }

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->post(route('category.index'), $this->validData())
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_duplicate_name_is_rejected(): void
    {
        $this->createCategory('Technology');

        $this->actingAs($this->admin())
            ->post(route('category.index'), $this->validData())
            ->assertRedirect()
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('categories', 1);
    }

    public function test_current_slug_is_rejected(): void
    {
        $this->createCategory('Science', 'technology');

        $this->actingAs($this->admin())
            ->post(route('category.index'), $this->validData())
            ->assertRedirect()
            ->assertSessionHasErrors('slug');

        $this->assertDatabaseCount('categories', 1);
    }

    public function test_historical_slug_is_rejected(): void
    {
        $category = $this->createCategory();
        CategorySlugHistory::create([
            'category_id' => $category->id,
            'slug' => 'technology',
        ]);

        $this->actingAs($this->admin())
            ->post(route('category.index'), $this->validData())
            ->assertRedirect()
            ->assertSessionHasErrors('slug');

        $this->assertDatabaseCount('categories', 1);
    }

    public function test_extra_field_is_rejected(): void
    {
        $data = [
            ...$this->validData(),
            'status' => 'active',
        ];

        $this->actingAs($this->admin())
            ->post(route('category.index'), $data)
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_validation_error_preserves_create_form_input(): void
    {
        $this->createCategory('Science', 'science');
        $data = [
            ...$this->validData(),
            'name' => 'Science',
            'slug' => 'attempted-slug',
            'description' => 'Attempted description.',
            'icon' => '🚀',
        ];
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('category.index'))
            ->post(route('category.index'), $data)
            ->assertRedirect(route('category.index'))
            ->assertSessionHasErrors('name')
            ->assertSessionHasInput($data);

        $this->actingAs($admin)
            ->get(route('category.index'))
            ->assertOk()
            ->assertSee('value="Science"', false)
            ->assertSee('value="attempted-slug"', false)
            ->assertSee('Attempted description.')
            ->assertSee('value="🚀" selected', false);
    }

    public function test_known_trigger_failure_preserves_input_and_shows_error(): void
    {
        Category::creating(function (Category $category): void {
            DB::table('category_slug_histories')->insert([
                'category_id' => 999,
                'slug' => $category->slug,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $data = $this->validData();

        $this->actingAs($this->admin())
            ->post(route('category.index'), $data)
            ->assertRedirect(route('category.index'))
            ->assertSessionHas('error', self::CONSTRAINT_MESSAGE)
            ->assertSessionHasInput($data);

        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseHas('category_slug_histories', [
            'category_id' => 999,
            'slug' => 'technology',
        ]);
    }

    public function test_unexpected_system_error_is_propagated(): void
    {
        Category::creating(function (): void {
            throw new RuntimeException('Simulated system failure.');
        });

        $this->withoutExceptionHandling();

        $exception = null;

        try {
            $this->actingAs($this->admin())
                ->post(route('category.index'), $this->validData());
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertSame('Simulated system failure.', $exception->getMessage());
        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('category_slug_histories', 0);
    }
}
