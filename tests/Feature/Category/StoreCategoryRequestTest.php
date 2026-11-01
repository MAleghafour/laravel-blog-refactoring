<?php

namespace Tests\Feature\Category;

use App\Http\Requests\dashboard\category\StoreCategoryRequest;
use App\Models\dashboard\Category;
use App\Models\dashboard\CategorySlugHistory;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreCategoryRequestTest extends TestCase
{
    use RefreshDatabase;

    private function validData(): array
    {
        return [
            'name' => 'Technology',
            'slug' => 'technology',
            'description' => 'Technology category.',
            'icon' => '🏆',
        ];
    }

    private function request(
        array $data,
        ?User $user = null
    ): StoreCategoryRequest {
        $request = StoreCategoryRequest::create(
            '/dashboard/article/category',
            'POST',
            $data
        );
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function validateData(array $data): ValidatorContract
    {
        $request = $this->request($data);
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

    private function createCategory(
        string $name = 'Existing Category',
        string $slug = 'existing-category'
    ): Category {
        return Category::create([
            'name' => $name,
            'slug' => $slug,
            'icon' => '💻',
        ]);
    }

    public function test_valid_data_passes_validation(): void
    {
        $validator = $this->validateData($this->validData());

        $this->assertTrue($validator->passes());
    }

    public function test_duplicate_name_is_rejected(): void
    {
        $this->createCategory('Technology');

        $validator = $this->validateData($this->validData());

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'این نام قبلاً ثبت شده است.',
            $validator->errors()->first('name')
        );
    }

    public function test_current_category_slug_is_rejected(): void
    {
        $this->createCategory('Science', 'technology');

        $validator = $this->validateData($this->validData());

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'این نامک قبلاً استفاده شده است.',
            $validator->errors()->first('slug')
        );
    }

    public function test_historical_slug_is_rejected(): void
    {
        $category = $this->createCategory();

        CategorySlugHistory::create([
            'category_id' => $category->id,
            'slug' => 'technology',
        ]);

        $validator = $this->validateData($this->validData());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('slug', $validator->errors()->toArray());
    }

    public function test_required_fields_are_rejected_when_missing(): void
    {
        $validator = $this->validateData([]);

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'نام دسته‌بندی الزامی است.',
            $validator->errors()->first('name')
        );
        $this->assertSame(
            'نامک دسته‌بندی الزامی است.',
            $validator->errors()->first('slug')
        );
        $this->assertSame(
            'انتخاب آیکون دسته‌بندی الزامی است.',
            $validator->errors()->first('icon')
        );
    }

    public function test_name_and_slug_cannot_exceed_255_characters(): void
    {
        $data = [
            ...$this->validData(),
            'name' => str_repeat('n', 256),
            'slug' => str_repeat('s', 256),
        ];

        $validator = $this->validateData($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
        $this->assertArrayHasKey('slug', $validator->errors()->toArray());
    }

    public function test_description_is_optional(): void
    {
        $data = $this->validData();
        unset($data['description']);

        $validator = $this->validateData($data);

        $this->assertTrue($validator->passes());
    }

    public function test_invalid_icon_is_rejected(): void
    {
        $data = [
            ...$this->validData(),
            'icon' => '❌',
        ];

        $validator = $this->validateData($data);

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'آیکون انتخاب‌شده معتبر نیست.',
            $validator->errors()->first('icon')
        );
    }

    public function test_extra_fields_are_rejected(): void
    {
        $data = [
            ...$this->validData(),
            'id' => 10,
            'role' => 'admin',
            'status' => 'active',
            'lock_version' => 0,
        ];

        $validator = $this->validateData($data);

        $this->assertTrue($validator->fails());

        foreach (['id', 'role', 'status', 'lock_version'] as $field) {
            $this->assertArrayHasKey($field, $validator->errors()->toArray());
        }
    }

    public function test_laravel_framework_fields_are_allowed(): void
    {
        $data = [
            ...$this->validData(),
            '_token' => 'csrf-token',
            '_method' => 'POST',
        ];

        $validator = $this->validateData($data);

        $this->assertTrue($validator->passes());
    }

    public function test_only_admin_is_authorized(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);
        $subscriber = User::factory()->create(['role' => 'subscriber']);

        $this->assertTrue($this->request([], $admin)->authorize());
        $this->assertFalse($this->request([], $author)->authorize());
        $this->assertFalse($this->request([], $subscriber)->authorize());
        $this->assertFalse($this->request([])->authorize());
    }
}
