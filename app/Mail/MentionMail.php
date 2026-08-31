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
 * Email notifying a user they were mentioned.
 */
class MentionMail extends Mailable implements ShouldQueue
{
    use Queueable, RetriesWithBackoff, SerializesModels;

    public function __construct(
        public string $mentionedUserName,
        public string $content,
        public string $link,
    ) {
        $this->onQueue('emails');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Você foi mencionado no '.config('orbita.name', 'Órbita'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notification-mention',
            text: 'emails.text.notification-mention',
        );
    }
}
