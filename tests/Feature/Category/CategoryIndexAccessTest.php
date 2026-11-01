<?php

namespace Tests\Feature\Category;

use App\Models\dashboard\Article;
use App\Models\dashboard\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryIndexAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_category_index(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('category.index'))
            ->assertOk()
            ->assertViewIs('dashboard.category');
    }

    public function test_author_and_subscriber_cannot_view_category_index(): void
    {
        foreach (['author', 'subscriber'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('category.index'))
                ->assertForbidden();
        }
    }

    public function test_guest_is_redirected_to_login_from_category_index(): void
    {
        $this->get(route('category.index'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_is_redirected_from_legacy_edit_route_with_selected_category_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create([
            'name' => 'Technology',
            'slug' => 'technology',
            'description' => 'Technology articles',
            'icon' => '💻',
        ]);

        $this->actingAs($admin)
            ->get(route('category.edit', $category->id))
            ->assertRedirect(route('category.index'))
            ->assertSessionHas('category_form_edit_id', $category->id)
            ->assertSessionHasInput([
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'icon' => $category->icon,
                'lock_version' => $category->lock_version,
            ]);

        $this->get(route('category.index'))
            ->assertOk()
            ->assertSee('data-mode="edit"', false)
            ->assertSee($category->name)
            ->assertSee($category->slug);
    }

    public function test_author_and_subscriber_cannot_access_category_edit_route(): void
    {
        $category = $this->createCategory('Technology', 'technology');

        foreach (['author', 'subscriber'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('category.edit', $category->id))
                ->assertForbidden();
        }
    }

    public function test_guest_is_redirected_to_login_from_category_edit_route(): void
    {
        $category = $this->createCategory('Technology', 'technology');

        $this->get(route('category.edit', $category->id))
            ->assertRedirect(route('login'));
    }

    public function test_missing_category_edit_redirects_to_index_with_error_message(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('category.edit', 999999))
            ->assertRedirect(route('category.index'))
            ->assertSessionHas('error', 'دسته بندی مورد نظر پیدا نشد!');
    }

    public function test_categories_are_sorted_by_name_ascending(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createCategory('Zulu', 'zulu');
        $this->createCategory('Alpha', 'alpha');
        $this->createCategory('Beta', 'beta');

        $categories = $this->actingAs($admin)
            ->get(route('category.index'))
            ->assertOk()
            ->viewData('categories');

        $this->assertSame(
            ['Alpha', 'Beta', 'Zulu'],
            $categories->pluck('name')->all(),
        );
    }

    public function test_category_pagination_has_fifteen_records_per_page_without_duplicates_or_omissions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $expectedIds = collect();

        foreach (range(1, 32) as $number) {
            $suffix = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $expectedIds->push(
                $this->createCategory("Category {$suffix}", "category-{$suffix}")->id,
            );
        }

        $pageOne = $this->actingAs($admin)
            ->get(route('category.index', ['page' => 1]))
            ->assertOk()
            ->viewData('categories');
        $pageTwo = $this->get(route('category.index', ['page' => 2]))
            ->assertOk()
            ->viewData('categories');
        $pageThree = $this->get(route('category.index', ['page' => 3]))
            ->assertOk()
            ->viewData('categories');

        $this->assertSame(15, $pageOne->perPage());
        $this->assertCount(15, $pageOne->items());
        $this->assertCount(15, $pageTwo->items());
        $this->assertCount(2, $pageThree->items());

        $actualIds = collect($pageOne->items())
            ->concat($pageTwo->items())
            ->concat($pageThree->items())
            ->pluck('id');

        $this->assertSame($expectedIds->all(), $actualIds->all());
        $this->assertSame(32, $actualIds->unique()->count());
    }

    public function test_index_displays_the_correct_article_count_for_each_category(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);
        $category = $this->createCategory('Technology', 'technology');

        foreach (range(1, 2) as $number) {
            Article::create([
                'author_id' => $author->id,
                'category_id' => $category->id,
                'title' => "Article {$number}",
                'slug' => "article-{$number}",
                'status' => 'draft',
            ]);
        }

        $response = $this->actingAs($admin)
            ->get(route('category.index'))
            ->assertOk()
            ->assertSee($category->name)
            ->assertSee('class=" px-2 py-1 bg-success rounded text-white">2</h5>', false);

        $categoryFromView = $response->viewData('categories')->firstWhere('id', $category->id);

        $this->assertSame(2, $categoryFromView->articles_count);
    }

    public function test_index_displays_empty_state_when_no_categories_exist(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('category.index'))
            ->assertOk()
            ->assertSee('تا این لحظه دسته بندی ثبت نشده است!');
    }

    private function createCategory(string $name, string $slug): Category
    {
        return Category::create([
            'name' => $name,
            'slug' => $slug,
            'description' => null,
            'icon' => '💻',
        ]);
    }
}
