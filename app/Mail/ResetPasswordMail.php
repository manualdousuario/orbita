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
 * Email containing a password reset link.
 */
class ResetPasswordMail extends Mailable implements ShouldQueue
{
    use Queueable, RetriesWithBackoff, SerializesModels;

    public function __construct(
        public string $displayName,
        public string $email,
        public string $resetUrl,
    ) {
        $this->onQueue('emails');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Redefinir senha - '.config('orbita.name', 'Órbita'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reset-password',
            text: 'emails.text.reset-password',
        );
    }
}
