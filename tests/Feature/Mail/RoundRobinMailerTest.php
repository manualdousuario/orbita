<?php

declare(strict_types=1);

/**
 * Guards the shape of the roundrobin mailer, not the network.
 */

namespace Tests\Feature\Mail;

use Illuminate\Support\Facades\Mail;
use ReflectionProperty;
use Symfony\Component\Mailer\Transport\FailoverTransport;
use Symfony\Component\Mailer\Transport\RoundRobinTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

afterEach(function () {
    Mail::forgetMailers();
});

it('the roundrobin mailer lists both providers', function () {
    expect(config('mail.mailers.roundrobin.mailers'))->toBe(['oci', 'resend'])
        ->and(config('mail.mailers.roundrobin.transport'))->toBe('roundrobin');
});

it('resolves to a round robin and not a failover transport', function () {
    $transport = Mail::mailer('roundrobin')->getSymfonyTransport();

    // FailoverTransport extends RoundRobinTransport; the exact class proves the split.
    expect($transport::class)->toBe(RoundRobinTransport::class);
    expect($transport)->not->toBeInstanceOf(FailoverTransport::class);
});

it('both providers are built as smtp transports', function () {
    $transports = (new ReflectionProperty(RoundRobinTransport::class, 'transports'))
        ->getValue(Mail::mailer('roundrobin')->getSymfonyTransport());

    expect($transports)->toHaveCount(2);

    foreach ((array) $transports as $transport) {
        expect($transport)->toBeInstanceOf(EsmtpTransport::class);
    }
});

it('the retry period is configured where laravel actually reads it', function () {
    // MailManager reads retry_after from the last sub-mailer, not the roundrobin block.
    expect(config('mail.mailers.roundrobin.mailers')[1])->toBe('resend');
    expect(config('mail.mailers.resend.retry_after'))->not->toBeNull();

    config()->set('mail.mailers.resend.retry_after', 15);
    Mail::forgetMailers();

    $retryPeriod = (new ReflectionProperty(RoundRobinTransport::class, 'retryPeriod'))
        ->getValue(Mail::mailer('roundrobin')->getSymfonyTransport());

    expect($retryPeriod)->toBe(15);
});
