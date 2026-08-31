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
 * Queued verification email sent to a user's new address.
 */
class EmailChangeVerificationMail extends Mailable implements ShouldQueue
{
    use Queueable, RetriesWithBackoff, SerializesModels;

    public function __construct(
        public string $displayName,
        public string $newEmail,
        public string $token,
    ) {
        $this->onQueue('emails');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirme seu novo email - '.config('orbita.name', 'Órbita'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verify-email-change',
            text: 'emails.text.verify-email-change',
            with: [
                'verificationUrl' => route('users.email.confirm', ['token' => $this->token]),
            ],
        );
    }
}
