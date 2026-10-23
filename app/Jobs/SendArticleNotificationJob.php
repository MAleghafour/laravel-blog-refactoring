<?php

namespace App\Jobs;

use App\Mail\ArticleEditedMail;
use App\Mail\ArticleReturnedForCorrectionMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendArticleNotificationJob implements ShouldQueue
{
    use Queueable;

    public const EDITED = 'edited';
    public const RETURNED = 'returned';

    public int $tries = 3;
    public int $timeout = 60;

    public function backoff(): array
    {
        return [10, 30];
    }

    public function __construct(
        public string $type,
        public string $recipientEmail,
        public string $articleTitle,
        public string $explanation,
        public int $articleId,
        public string $actorName,
        public string $occurredAt,
    ) {
        if (! in_array($type, [self::EDITED, self::RETURNED], true)) {
            throw new \InvalidArgumentException(
                'Unsupported article notification type.'
            );
        }

        $this->afterCommit();
    }

    /**
     * Select the email template for the editorial event.
     */
    public function handle(): void
    {
        $mail = match ($this->type) {
            self::EDITED => new ArticleEditedMail(
                $this->articleTitle,
                $this->explanation,
                $this->articleId,
                $this->actorName,
                $this->occurredAt
            ),
            self::RETURNED => new ArticleReturnedForCorrectionMail(
                $this->articleTitle,
                $this->explanation,
                $this->articleId,
                $this->actorName,
                $this->occurredAt
            ),
            default => throw new \InvalidArgumentException(
                'Unsupported article notification type.'
            ),
        };

        Mail::to($this->recipientEmail)->send($mail);
    }
}
