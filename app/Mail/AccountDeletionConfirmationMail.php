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
 * Email confirming a requested account deletion.
 */
class AccountDeletionConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, RetriesWithBackoff, SerializesModels;

    public function __construct(
        public string $displayName,
        public string $token,
    ) {
        $this->onQueue('emails');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirme a exclusão da sua conta - '.config('orbita.name', 'Órbita'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.confirm-account-deletion',
            text: 'emails.text.confirm-account-deletion',
            with: [
                'confirmationUrl' => route('users.delete-account.confirm', ['token' => $this->token]),
            ],
        );
    }
}
