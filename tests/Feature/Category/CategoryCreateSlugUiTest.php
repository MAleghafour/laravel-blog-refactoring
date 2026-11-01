<?php

namespace Tests\Feature\Category;

use App\Models\dashboard\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryCreateSlugUiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_create_form_contains_slug_suggestion_controls(): void
    {
        $this->actingAs($this->admin())
            ->get(route('category.index'))
            ->assertOk()
            ->assertSee('id="category-form"', false)
            ->assertSee('data-mode="create"', false)
            ->assertSee('id="category-name"', false)
            ->assertSee('id="category-slug"', false)
            ->assertSee('data-has-old-value="false"', false)
            ->assertSee(
                "nameInput.addEventListener('input', updateSuggestion);",
                false
            );
    }

    public function test_manual_slug_state_is_encoded_in_script(): void
    {
        $this->actingAs($this->admin())
            ->get(route('category.index'))
            ->assertOk()
            ->assertSee(
                "slugWasEdited = slugInput.dataset.hasOldValue === 'true';",
                false
            )
            ->assertSee(
                "slugInput.addEventListener('input', () => {",
                false
            )
            ->assertSee('slugWasEdited = true;', false)
            ->assertSee('if (!slugWasEdited)', false);
    }

    public function test_slug_suggestion_uses_conservative_latin_conversion(): void
    {
        $this->actingAs($this->admin())
            ->get(route('category.index'))
            ->assertOk()
            ->assertSee(
                'if (!/^[A-Za-z0-9 _-]*$/.test(normalized))',
                false
            )
            ->assertSee(".normalize('NFKD')", false)
            ->assertSee(".replace(/[\\s_-]+/g, '-')", false);
    }

    public function test_old_slug_is_preserved_after_validation_error(): void
    {
        $this->actingAs($this->admin())
            ->withSession([
                '_old_input' => [
                    'name' => 'Attempted Name',
                    'slug' => 'custom-manual-slug',
                    'description' => 'Attempted description.',
                    'icon' => '🚀',
                ],
            ])
            ->get(route('category.index'))
            ->assertOk()
            ->assertSee('value="custom-manual-slug"', false)
            ->assertSee('data-has-old-value="true"', false);
    }

    public function test_legacy_edit_route_opens_shared_form_in_edit_mode(): void
    {
        $category = Category::create([
            'name' => 'Technology',
            'slug' => 'technology',
            'icon' => '💻',
        ]);

        $this->actingAs($this->admin())
            ->followingRedirects()
            ->get(route('category.edit', $category->id))
            ->assertOk()
            ->assertSee('id="category-form"', false)
            ->assertSee('data-mode="edit"', false)
            ->assertSee('value="Technology"', false)
            ->assertSee('value="technology"', false)
            ->assertSee('name="lock_version" value="0"', false);
    }
}
