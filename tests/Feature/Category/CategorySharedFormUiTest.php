<?php

namespace Tests\Feature\Category;

use App\Models\dashboard\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorySharedFormUiTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_shared_form_defaults_to_create_mode(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('category.index'))
            ->assertOk()
            ->assertSee('id="category-form"', false)
            ->assertSee('data-mode="create"', false)
            ->assertSee('action="'.route('category.index').'"', false)
            ->assertSee('افزودن دسته‌بندی')
            ->assertSee('id="category-form-cancel"', false);

        $this->assertSame(
            1,
            substr_count($response->getContent(), 'id="category-form"')
        );
        $this->assertMatchesRegularExpression(
            '/id="category-form-cancel"[^>]+hidden/s',
            $response->getContent()
        );
    }

    public function test_edit_trigger_exposes_category_data_to_shared_form(): void
    {
        $category = $this->createCategory(
            'Technology & Security',
            'technology-security'
        );

        $this->actingAs($this->admin())
            ->get(route('category.index'))
            ->assertOk()
            ->assertSee('class="category-edit-trigger', false)
            ->assertSee(
                'data-update-url="'.route('category.update', $category->id).'"',
                false
            )
            ->assertSee(
                'data-category-name="'.e($category->name).'"',
                false
            )
            ->assertSee('data-category-slug="technology-security"', false)
            ->assertSee('data-category-description="Technology category."', false)
            ->assertSee('data-category-icon="💻"', false)
            ->assertSee('data-lock-version="0"', false)
            ->assertSee(
                "nameInput.value = trigger.dataset.categoryName ?? '';",
                false
            )
            ->assertSee(
                "slugInput.value = trigger.dataset.categorySlug ?? '';",
                false
            );
    }

    public function test_cancel_restores_create_mode(): void
    {
        $this->createCategory();

        $this->actingAs($this->admin())
            ->get(route('category.index'))
            ->assertOk()
            ->assertSee('const setCreateMode = () => {', false)
            ->assertSee("form.dataset.mode = 'create';", false)
            ->assertSee('methodInput.disabled = true;', false)
            ->assertSee('lockVersionInput.value = \'\';', false)
            ->assertSee('slugWasEdited = false;', false)
            ->assertSee(
                "cancelButton.addEventListener('click', setCreateMode);",
                false
            );
    }

    public function test_create_validation_error_preserves_create_mode_and_input(): void
    {
        $this->createCategory('Science', 'science');
        $data = [
            'name' => 'Science',
            'slug' => 'attempted-slug',
            'description' => 'Attempted description.',
            'icon' => '🚀',
        ];

        $this->actingAs($this->admin())
            ->from(route('category.index'))
            ->followingRedirects()
            ->post(route('category.index'), $data)
            ->assertOk()
            ->assertSee('data-mode="create"', false)
            ->assertSee('value="Science"', false)
            ->assertSee('value="attempted-slug"', false)
            ->assertSee('Attempted description.')
            ->assertSee('value="🚀" selected', false);
    }

    public function test_update_validation_error_preserves_edit_mode_version_and_input(): void
    {
        $category = $this->createCategory();
        $this->createCategory('Science', 'science');
        $data = [
            'name' => 'Science',
            'slug' => 'attempted-slug',
            'description' => 'Attempted edit.',
            'icon' => '🚀',
            'lock_version' => $category->lock_version,
        ];

        $this->actingAs($this->admin())
            ->from(route('category.index'))
            ->followingRedirects()
            ->put(route('category.update', $category->id), $data)
            ->assertOk()
            ->assertSee('data-mode="edit"', false)
            ->assertSee(
                'action="'.route('category.update', $category->id).'"',
                false
            )
            ->assertSee('name="lock_version" value="0"', false)
            ->assertSee('value="Science"', false)
            ->assertSee('value="attempted-slug"', false)
            ->assertSee('Attempted edit.')
            ->assertSee('value="🚀" selected', false);
    }

    public function test_slug_suggestion_runs_only_in_create_mode(): void
    {
        $this->actingAs($this->admin())
            ->get(route('category.index'))
            ->assertOk()
            ->assertSee("if (form.dataset.mode !== 'create')", false)
            ->assertSee('slugInput.value = suggestSlug(nameInput.value);', false)
            ->assertSee("form.dataset.mode = 'edit';", false)
            ->assertSee('slugWasEdited = true;', false);
    }

    public function test_icon_options_match_shared_configuration_without_duplicates(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('category.index'))
            ->assertOk();

        preg_match(
            '/<select id="category-icon".*?<\/select>/su',
            $response->getContent(),
            $select
        );
        preg_match_all('/<option value="([^"]+)"/u', $select[0], $matches);

        $icons = array_map(
            fn (string $icon): string => html_entity_decode(
                $icon,
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            ),
            $matches[1]
        );
        $configuredIcons = array_keys(config('category.icons'));

        $this->assertSame($configuredIcons, $icons);
        $this->assertCount(count(array_unique($icons)), $icons);
        $this->assertContains('🏆', $icons);
    }

    public function test_success_and_error_messages_are_rendered(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withSession(['success' => 'پیام موفقیت دسته‌بندی'])
            ->get(route('category.index'))
            ->assertOk()
            ->assertSee('پیام موفقیت دسته‌بندی');

        $this->actingAs($admin)
            ->withSession(['error' => 'پیام خطای دسته‌بندی'])
            ->get(route('category.index'))
            ->assertOk()
            ->assertSee('پیام خطای دسته‌بندی');
    }

    public function test_delete_modal_remains_independent_with_empty_initial_version(): void
    {
        $category = $this->createCategory();

        $this->actingAs($this->admin())
            ->get(route('category.index'))
            ->assertOk()
            ->assertSee('id="deleteCategoryModal"', false)
            ->assertSee('id="delete-category-form" method="post"', false)
            ->assertSee('data-lock-version="0"', false)
            ->assertSee('name="lock_version" value=""', false)
            ->assertSee(
                "lockVersion.value = trigger.dataset.lockVersion ?? '';",
                false
            );
    }
}
