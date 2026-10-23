<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ArticleReturnedForCorrectionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $articleTitle,
        public string $explanation,
        public int $articleId,
        public string $actorName,
        public string $occurredAt
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'مقاله شما برای اصلاح بازگردانده شد - بلاگ زین'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.article-returned-for-correction'
        );
    }
}
