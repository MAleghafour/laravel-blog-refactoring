<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Models\dashboard\Article;
use App\Policies\ArticlePolicy;
use Tests\TestCase;

class ArticlePolicyTest extends TestCase
{
    public function test_admin_can_update_any_article(): void
    {
        $admin = new User();
        $admin->role = 'admin';

        $article = new Article([
            'author_id' => 100,
        ]);

        $this->assertTrue(
            (new ArticlePolicy())->update($admin, $article)
        );
    }

    public function test_author_can_update_own_article(): void
    {
        $author = new User();
        $author->role = 'author';
        $author->id = 10;

        $article = new Article([
            'author_id' => 10,
        ]);

        $this->assertTrue(
            (new ArticlePolicy())->update($author, $article)
        );
    }

    public function test_author_cannot_update_another_authors_article(): void
    {
        $author = new User();
        $author->role = 'author';
        $author->id = 10;

        $article = new Article([
            'author_id' => 20,
        ]);

        $this->assertFalse(
            (new ArticlePolicy())->update($author, $article)
        );
    }

    public function test_subscriber_cannot_update_article(): void
    {
        $subscriber = new User();
        $subscriber->role = 'subscriber';

        $subscriber->id = 10;

        $article = new Article([
            'author_id' => 10,
        ]);

        $this->assertFalse(
            (new ArticlePolicy())->update($subscriber, $article)
        );
    }

    public function test_admin_can_delete_any_article(): void
    {
        $admin = new User();
        $admin->role = 'admin';

        $article = new Article([
            'author_id' => 100,
        ]);

        $this->assertTrue(
            (new ArticlePolicy())->delete($admin, $article)
        );
    }

    public function test_author_can_delete_own_article(): void
    {
        $author = new User();
        $author->role = 'author';
        $author->id = 10;

        $article = new Article([
            'author_id' => 10,
        ]);

        $this->assertTrue(
            (new ArticlePolicy())->delete($author, $article)
        );
    }

    public function test_author_cannot_delete_another_authors_article(): void
    {
        $author = new User();
        $author->role = 'author';
        $author->id = 10;

        $article = new Article([
            'author_id' => 20,
        ]);

        $this->assertFalse(
            (new ArticlePolicy())->delete($author, $article)
        );
    }

    public function test_subscriber_cannot_delete_article(): void
    {
        $subscriber = new User();
        $subscriber->role = 'subscriber';

        $subscriber->id = 10;

        $article = new Article([
            'author_id' => 10,
        ]);

        $this->assertFalse(
            (new ArticlePolicy())->delete($subscriber, $article)
        );
    }
}
