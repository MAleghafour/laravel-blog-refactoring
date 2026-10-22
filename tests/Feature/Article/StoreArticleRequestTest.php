<?php

namespace Tests\Feature\Article;

use App\Http\Requests\dashboard\article\StoreArticleRequest;
use App\Models\User;
use App\Models\dashboard\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreArticleRequestTest extends TestCase
{
    use RefreshDatabase;

    private function validateData(array $data): \Illuminate\Contracts\Validation\Validator
    {
        $request = new StoreArticleRequest();

        return Validator::make($data, $request->rules());
    }

    public function test_incomplete_draft_is_valid(): void
    {
        $validator = $this->validateData([
            'slug' => 'incomplete-draft',
        ]);

        $this->assertTrue($validator->passes());
    }

    public function test_slug_is_required(): void
    {
        $validator = $this->validateData([
            'title' => 'Test Article',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('slug', $validator->errors()->toArray());
    }

    public function test_slug_must_be_unique_including_trashed_articles(): void
    {
        $author = User::factory()->create();

        $article = Article::create([
            'author_id' => $author->id,
            'title' => 'Existing Article',
            'slug' => 'existing-slug',
            'status' => 'draft',
        ]);

        $article->delete();

        $validator = $this->validateData([
            'slug' => 'existing-slug',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('slug', $validator->errors()->toArray());
    }

    public function test_brief_cannot_exceed_260_characters(): void
    {
        $validator = $this->validateData([
            'slug' => 'brief-limit',
            'brief' => str_repeat('A', 261),
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('brief', $validator->errors()->toArray());
    }

    public function test_category_must_exist(): void
    {
        $validator = $this->validateData([
            'slug' => 'invalid-category',
            'category_id' => 999999,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('category_id', $validator->errors()->toArray());
    }

    public function test_title_cannot_exceed_255_characters(): void
    {
        $validator = $this->validateData([
            'slug' => 'title-limit',
            'title' => str_repeat('A', 256),
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('title', $validator->errors()->toArray());
    }
}
