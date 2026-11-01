<?php

namespace Tests\Feature\Category;

use App\Http\Requests\dashboard\category\UpdateCategoryRequest;
use App\Models\dashboard\Category;
use App\Models\dashboard\CategorySlugHistory;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateCategoryRequestTest extends TestCase
{
    use RefreshDatabase;

    private function createCategory(
        string $name = 'Technology',
        string $slug = 'technology'
    ): Category {
        return Category::create([
            'name' => $name,
            'slug' => $slug,
            'icon' => '💻',
        ]);
    }

    private function validData(Category $category): array
    {
        return [
            'name' => 'Updated Technology',
            'slug' => $category->slug,
            'description' => 'Updated category description.',
            'icon' => '💻',
            'lock_version' => $category->lock_version,
        ];
    }

    private function request(
        array $data,
        Category $category,
        ?User $user = null
    ): UpdateCategoryRequest {
        $request = UpdateCategoryRequest::create(
            '/category/'.$category->id,
            'PUT',
            $data
        );
        $request->setUserResolver(fn () => $user);

        $route = new Route(['PUT'], '/category/{id}', fn () => null);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    private function validateData(
        array $data,
        Category $category
    ): ValidatorContract {
        $request = $this->request($data, $category);
        $validator = Validator::make(
            $request->all(),
            $request->rules(),
            $request->messages()
        );

        foreach ($request->after() as $after) {
            $validator->after($after);
        }

        return $validator;
    }

    public function test_valid_update_passes_validation(): void
    {
        $category = $this->createCategory();

        $validator = $this->validateData(
            $this->validData($category),
            $category
        );

        $this->assertTrue($validator->passes());
    }

    public function test_duplicate_name_is_rejected(): void
    {
        $category = $this->createCategory();
        $this->createCategory('Science', 'science');

        $data = $this->validData($category);
        $data['name'] = 'Science';

        $validator = $this->validateData($data, $category);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_slug_used_by_another_category_is_rejected(): void
    {
        $category = $this->createCategory();
        $this->createCategory('Science', 'science');

        $data = $this->validData($category);
        $data['slug'] = 'science';

        $validator = $this->validateData($data, $category);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('slug', $validator->errors()->toArray());
    }

    public function test_historical_slug_is_rejected(): void
    {
        $category = $this->createCategory();

        CategorySlugHistory::create([
            'category_id' => $category->id,
            'slug' => 'legacy-technology',
        ]);

        $data = $this->validData($category);
        $data['slug'] = 'legacy-technology';

        $validator = $this->validateData($data, $category);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('slug', $validator->errors()->toArray());
    }

    public function test_current_name_and_slug_are_allowed(): void
    {
        $category = $this->createCategory();
        $data = $this->validData($category);
        $data['name'] = $category->name;

        $validator = $this->validateData($data, $category);

        $this->assertTrue($validator->passes());
    }

    public function test_lock_version_is_required(): void
    {
        $category = $this->createCategory();
        $data = $this->validData($category);
        unset($data['lock_version']);

        $validator = $this->validateData($data, $category);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'lock_version',
            $validator->errors()->toArray()
        );
    }

    public function test_lock_version_must_be_a_non_negative_integer(): void
    {
        $category = $this->createCategory();

        foreach (['invalid', -1] as $invalidVersion) {
            $data = $this->validData($category);
            $data['lock_version'] = $invalidVersion;

            $validator = $this->validateData($data, $category);

            $this->assertTrue($validator->fails());
            $this->assertArrayHasKey(
                'lock_version',
                $validator->errors()->toArray()
            );
        }
    }

    public function test_description_is_optional(): void
    {
        $category = $this->createCategory();
        $data = $this->validData($category);
        unset($data['description']);

        $validator = $this->validateData($data, $category);

        $this->assertTrue($validator->passes());
    }

    public function test_icon_must_belong_to_current_site_icon_set(): void
    {
        $category = $this->createCategory();
        $data = $this->validData($category);
        $data['icon'] = '❌';

        $validator = $this->validateData($data, $category);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('icon', $validator->errors()->toArray());
    }

    public function test_extra_fields_are_rejected(): void
    {
        $category = $this->createCategory();
        $data = [
            ...$this->validData($category),
            'status' => 'published',
            'author_id' => 100,
        ];

        $validator = $this->validateData($data, $category);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('status', $validator->errors()->toArray());
        $this->assertArrayHasKey('author_id', $validator->errors()->toArray());
    }

    public function test_only_admin_is_authorized(): void
    {
        $category = $this->createCategory();
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);

        $this->assertTrue(
            $this->request([], $category, $admin)->authorize()
        );
        $this->assertFalse(
            $this->request([], $category, $author)->authorize()
        );
        $this->assertFalse(
            $this->request([], $category)->authorize()
        );
    }
}
