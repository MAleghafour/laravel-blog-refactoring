<?php

namespace Tests\Feature\Category;

use App\Models\dashboard\Article;
use App\Models\dashboard\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryDestroyModalUiTest extends TestCase
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

    public function test_each_category_has_a_modal_trigger_and_one_shared_modal(): void
    {
        $first = $this->createCategory('Technology', 'technology');
        $second = $this->createCategory('Science', 'science');

        $response = $this->actingAs($this->admin())
            ->get(route('category.index'))
            ->assertOk();

        $content = $response->getContent();

        $this->assertSame(
            2,
            substr_count($content, 'data-bs-target="#deleteCategoryModal"')
        );
        $this->assertSame(
            1,
            substr_count($content, 'id="deleteCategoryModal"')
        );
        $this->assertSame(
            2,
            substr_count($content, 'class="category-delete-trigger')
        );
        $response
            ->assertSee('data-category-id="'.$first->id.'"', false)
            ->assertSee('data-category-id="'.$second->id.'"', false)
            ->assertSee(
                'data-delete-url="'.route('category.destroy', $first->id).'"',
                false
            )
            ->assertSee(
                'data-delete-url="'.route('category.destroy', $second->id).'"',
                false
            );
    }

    public function test_trigger_is_a_button_and_does_not_submit_delete_directly(): void
    {
        $this->createCategory();

        $response = $this->actingAs($this->admin())
            ->get(route('category.index'))
            ->assertOk();

        $content = $response->getContent();

        $this->assertMatchesRegularExpression(
            '/<button type="button"\s+class="category-delete-trigger[^>]+'.
            'data-bs-toggle="modal"[^>]+>/',
            $content
        );
        $this->assertSame(
            1,
            substr_count($content, 'id="delete-category-form"')
        );
    }

    public function test_modal_contains_delete_form_controls_and_current_lock_version_data(): void
    {
        $category = $this->createCategory();
        $category->update(['description' => 'Updated description.']);

        $this->actingAs($this->admin())
            ->get(route('category.index'))
            ->assertOk()
            ->assertSee('data-lock-version="1"', false)
            ->assertSee('id="delete-category-form" method="post"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertSee('name="lock_version" value=""', false)
            ->assertSee('data-bs-dismiss="modal"', false)
            ->assertSee('انصراف')
            ->assertSee('حذف دسته‌بندی');
    }

    public function test_delete_modal_layout_is_right_aligned_for_rtl(): void
    {
        $this->createCategory();

        $this->actingAs($this->admin())
            ->get(route('category.index'))
            ->assertOk()
            ->assertSee('class="modal-content text-start"', false)
            ->assertSee('class="modal-header text-start"', false)
            ->assertSee('class="modal-title flex-grow-1 text-start"', false)
            ->assertSee('class="modal-body text-start"', false)
            ->assertSee('class="modal-footer justify-content-start"', false);
    }

    public function test_selected_category_name_is_transferred_safely_to_modal(): void
    {
        $name = 'Technology <script>alert("unsafe")</script>';
        $this->createCategory($name);

        $response = $this->actingAs($this->admin())
            ->get(route('category.index'))
            ->assertOk()
            ->assertSee(
                'data-category-name="'.e($name).'"',
                false
            )
            ->assertSee(
                "categoryName.textContent = trigger.dataset.categoryName ?? '';",
                false
            );

        $this->assertStringNotContainsString(
            'categoryName.innerHTML',
            $response->getContent()
        );
    }

    public function test_category_with_article_still_has_modal_trigger(): void
    {
        $category = $this->createCategory();
        $author = User::factory()->create(['role' => 'author']);

        Article::create([
            'author_id' => $author->id,
            'category_id' => $category->id,
            'title' => 'Category article',
            'slug' => 'category-article',
            'status' => 'draft',
        ]);

        $this->actingAs($this->admin())
            ->get(route('category.index'))
            ->assertOk()
            ->assertSee('data-category-id="'.$category->id.'"', false)
            ->assertSee('data-bs-target="#deleteCategoryModal"', false);
    }

    public function test_repeated_delete_submission_is_prevented(): void
    {
        $this->createCategory();

        $this->actingAs($this->admin())
            ->get(route('category.index'))
            ->assertOk()
            ->assertSee("form.addEventListener('submit'", false)
            ->assertSee('if (isSubmitting || !form.hasAttribute(\'action\'))', false)
            ->assertSee('submitButton.disabled = true;', false);
    }

    public function test_lock_version_validation_error_is_visible_on_page(): void
    {
        $category = $this->createCategory();

        $this->actingAs($this->admin())
            ->from(route('category.index'))
            ->followingRedirects()
            ->delete(route('category.destroy', $category->id))
            ->assertOk()
            ->assertSee(
                'نسخه دسته‌بندی مشخص نیست. صفحه را دوباره بارگذاری کنید.'
            );
    }

    public function test_backend_delete_error_is_visible_after_redirect(): void
    {
        $category = $this->createCategory();
        $author = User::factory()->create(['role' => 'author']);

        Article::create([
            'author_id' => $author->id,
            'category_id' => $category->id,
            'title' => 'Protected article',
            'slug' => 'protected-article',
            'status' => 'draft',
        ]);

        $this->actingAs($this->admin())
            ->followingRedirects()
            ->delete(route('category.destroy', $category->id), [
                'lock_version' => $category->lock_version,
            ])
            ->assertOk()
            ->assertSee('این دسته‌بندی دارای مقاله است و قابل حذف نیست.');
    }
}
