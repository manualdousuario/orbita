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
 * Email notifying a post author, or a follower of the post, of a new comment.
 */
class NewCommentMail extends Mailable implements ShouldQueue
{
    use Queueable, RetriesWithBackoff, SerializesModels;

    public function __construct(
        public string $postTitle,
        public string $authorName,
        public string $commenterName,
        public string $commentContent,
        public string $commentLink,
        public bool $following = false,
    ) {
        $this->onQueue('emails');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Novo comentário em: '.$this->postTitle,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notification-new-comment',
            text: 'emails.text.notification-new-comment',
        );
    }
}
