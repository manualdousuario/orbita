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
 * Email notifying staff of a new report.
 */
class NewReportMail extends Mailable implements ShouldQueue
{
    use Queueable, RetriesWithBackoff, SerializesModels;

    public function __construct(
        public string $targetLabel,
        public string $postTitle,
        public string $targetContent,
        public string $targetAuthor,
        public string $reporterName,
        public ?string $reason,
        public string $contentUrl,
        public string $adminUrl,
        public int $reportCount,
        public bool $autoHidden,
    ) {
        $this->onQueue('emails');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🚨 Denúncia: '.mb_strtolower($this->targetLabel).' em "'.$this->postTitle.'"',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notification-new-report',
            text: 'emails.text.notification-new-report',
        );
    }
}
