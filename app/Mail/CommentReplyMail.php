<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\RetriesWithBackoff;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email notifying a comment author of a new reply.
 */
class CommentReplyMail extends Mailable implements ShouldQueue
{
    use Queueable, RetriesWithBackoff, SerializesModels;

    public function __construct(
        public string $postTitle,
        public string $authorName,
        public string $replierName,
        public string $replyContent,
        public string $parentContent,
        public string $replyLink,
    ) {
        $this->onQueue('emails');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nova resposta ao seu comentário',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notification-comment-reply',
            text: 'emails.text.notification-comment-reply',
        );
    }
}
