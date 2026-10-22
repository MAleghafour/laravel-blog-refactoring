<?php

namespace App\Jobs;

use App\Mail\ArticleEditedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendArticleEditedMailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function backoff(): array
    {
        return [10, 30];
    }

    public function __construct(
        public string $recipientEmail,
        public string $articleTitle,
        public string $explanation,
        public int $articleId
    ) {
        $this->afterCommit();
    }

    public function handle(): void
    {
        Mail::to($this->recipientEmail)->send(
            new ArticleEditedMail(
                $this->articleTitle,
                $this->explanation,
                $this->articleId
            )
        );
    }
}
