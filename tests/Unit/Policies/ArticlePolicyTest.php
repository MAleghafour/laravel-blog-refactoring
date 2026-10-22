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


    public function test_admin_and_author_can_view_article_list(): void
    {
        $policy = new ArticlePolicy();

        foreach (['admin', 'author'] as $role) {
            $user = new User();
            $user->role = $role;

            $this->assertTrue($policy->viewAny($user));
        }
    }

    public function test_subscriber_cannot_view_article_list(): void
    {
        $subscriber = new User();
        $subscriber->role = 'subscriber';

        $this->assertFalse(
            (new ArticlePolicy())->viewAny($subscriber)
        );
    }

    public function test_admin_and_author_can_create_articles(): void
    {
        $policy = new ArticlePolicy();

        foreach (['admin', 'author'] as $role) {
            $user = new User();
            $user->role = $role;

            $this->assertTrue($policy->create($user));
        }
    }

    public function test_subscriber_cannot_create_articles(): void
    {
        $subscriber = new User();
        $subscriber->role = 'subscriber';

        $this->assertFalse(
            (new ArticlePolicy())->create($subscriber)
        );
    }

    public function test_article_view_respects_ownership(): void
    {
        $policy = new ArticlePolicy();

        $admin = new User();
        $admin->role = 'admin';

        $author = new User();
        $author->role = 'author';
        $author->id = 10;

        $ownArticle = new Article(['author_id' => 10]);
        $otherArticle = new Article(['author_id' => 20]);

        $this->assertTrue($policy->view($admin, $otherArticle));
        $this->assertTrue($policy->view($author, $ownArticle));
        $this->assertFalse($policy->view($author, $otherArticle));
    }

    public function test_trashed_article_cannot_be_updated_or_soft_deleted_again(): void
    {
        $policy = new ArticlePolicy();

        $admin = new User();
        $admin->role = 'admin';

        $author = new User();
        $author->role = 'author';
        $author->id = 10;

        $article = new Article(['author_id' => 10]);
        $article->deleted_at = now();

        foreach ([$admin, $author] as $user) {
            $this->assertFalse($policy->update($user, $article));
            $this->assertFalse($policy->delete($user, $article));
        }
    }

    public function test_restore_requires_ownership_and_trashed_state(): void
    {
        $policy = new ArticlePolicy();

        $admin = new User();
        $admin->role = 'admin';

        $author = new User();
        $author->role = 'author';
        $author->id = 10;

        $otherAuthor = new User();
        $otherAuthor->role = 'author';
        $otherAuthor->id = 20;

        $article = new Article(['author_id' => 10]);

        $this->assertFalse($policy->restore($admin, $article));

        $article->deleted_at = now();

        $this->assertTrue($policy->restore($admin, $article));
        $this->assertTrue($policy->restore($author, $article));
        $this->assertFalse($policy->restore($otherAuthor, $article));
    }

    public function test_only_admin_can_permanently_delete_trashed_article(): void
    {
        $policy = new ArticlePolicy();

        $admin = new User();
        $admin->role = 'admin';

        $author = new User();
        $author->role = 'author';
        $author->id = 10;

        $article = new Article(['author_id' => 10]);

        $this->assertFalse($policy->forceDelete($admin, $article));

        $article->deleted_at = now();

        $this->assertTrue($policy->forceDelete($admin, $article));
        $this->assertFalse($policy->forceDelete($author, $article));
    }

    public function test_only_admin_can_review_pending_articles(): void
    {
        $policy = new ArticlePolicy();

        $admin = new User();
        $admin->role = 'admin';

        $author = new User();
        $author->role = 'author';
        $author->id = 10;

        $article = new Article([
            'author_id' => 10,
            'status' => 'pending',
        ]);

        $this->assertTrue($policy->approve($admin, $article));
        $this->assertTrue($policy->returnForCorrection($admin, $article));

        $this->assertFalse($policy->approve($author, $article));
        $this->assertFalse($policy->returnForCorrection($author, $article));

        $article->status = 'draft';

        $this->assertFalse($policy->approve($admin, $article));
        $this->assertFalse($policy->returnForCorrection($admin, $article));

        $article->status = 'pending';
        $article->deleted_at = now();

        $this->assertFalse($policy->approve($admin, $article));
        $this->assertFalse($policy->returnForCorrection($admin, $article));
    }

    public function test_author_can_submit_only_own_draft(): void
    {
        $policy = new ArticlePolicy();

        $author = new User();
        $author->role = 'author';
        $author->id = 10;

        $admin = new User();
        $admin->role = 'admin';

        $article = new Article([
            'author_id' => 10,
            'status' => 'draft',
        ]);

        $this->assertTrue($policy->submitForReview($author, $article));
        $this->assertFalse($policy->submitForReview($admin, $article));

        $article->author_id = 20;

        $this->assertFalse($policy->submitForReview($author, $article));

        $article->author_id = 10;
        $article->status = 'pending';

        $this->assertFalse($policy->submitForReview($author, $article));

        $article->status = 'draft';
        $article->deleted_at = now();

        $this->assertFalse($policy->submitForReview($author, $article));
    }

    public function test_author_can_withdraw_only_own_pending_article(): void
    {
        $policy = new ArticlePolicy();

        $author = new User();
        $author->role = 'author';
        $author->id = 10;

        $admin = new User();
        $admin->role = 'admin';

        $article = new Article([
            'author_id' => 10,
            'status' => 'pending',
        ]);

        $this->assertTrue($policy->withdraw($author, $article));
        $this->assertFalse($policy->withdraw($admin, $article));

        $article->author_id = 20;

        $this->assertFalse($policy->withdraw($author, $article));

        $article->author_id = 10;
        $article->status = 'draft';

        $this->assertFalse($policy->withdraw($author, $article));

        $article->status = 'pending';
        $article->deleted_at = now();

        $this->assertFalse($policy->withdraw($author, $article));
    }

}
