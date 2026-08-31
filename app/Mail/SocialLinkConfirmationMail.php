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
 * Email confirming a social account link.
 */
class SocialLinkConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, RetriesWithBackoff, SerializesModels;

    public function __construct(
        public string $displayName,
        public string $providerLabel,
        public string $token,
    ) {
        $this->onQueue('emails');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Conectar sua conta do '.$this->providerLabel.' - '.config('orbita.name', 'Órbita'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.confirm-social-link',
            text: 'emails.text.confirm-social-link',
            with: [
                'confirmUrl' => route('social.link.confirm', ['token' => $this->token]),
            ],
        );
    }
}
