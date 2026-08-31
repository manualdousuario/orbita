<?php

declare(strict_types=1);

/**
 * Tests the classifier that decides whether a mail failure is permanent.
 */

namespace Tests\Unit;

use App\Support\PermanentMailFailure;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;

/**
 * A real OCI SMTP suppression response observed in production.
 */
const OCI_SUPPRESSED = 'Expected response code "250/251/252" but got code "254", '
    .'with message "254 4.7.1 -  cesar.scapella@gmail.com is suppressed for sender '
    .'ocid1.emailsender.oc1.sa-saopaulo-1.amaaaaaartenwriavutaj4vpb4yueyxuudcgwmxpbo5mi4ky7l3isqmmcn6a".';

/**
 * A realistic SMTP conversation as AbstractStream logs it, ending in $finalReply.
 */
function smtpConversation(string $finalReply): string
{
    $ts = '2026-08-01T11:00:34.000000+00:00';

    return implode("\n", [
        "[{$ts}] < 220 mail.provider.example ESMTP ready",
        "[{$ts}] > EHLO orbita.social.br",
        "[{$ts}] < 250-mail.provider.example",
        "[{$ts}] < 250-SIZE 52428800",
        "[{$ts}] < 250-AUTH LOGIN PLAIN",
        "[{$ts}] < 250 STARTTLS",
        "[{$ts}] > AUTH LOGIN",
        "[{$ts}] < 235 2.7.0 Authentication successful",
        "[{$ts}] > MAIL FROM:<naoresponda@orbita.social.br>",
        "[{$ts}] < 250 2.1.0 Ok",
        "[{$ts}] > RCPT TO:<cesar.scapella@gmail.com>",
        "[{$ts}] < {$finalReply}",
    ])."\n";
}

/**
 * Builds an aggregate TransportException with one debug entry per provider.
 *
 * @param  list<string>  $conversations
 */
function mailAggregate(array $conversations): TransportException
{
    $e = new TransportException('All transports failed.');

    foreach ($conversations as $i => $conversation) {
        $e->appendDebug(sprintf("Transport \"smtp://provider%d\": %s\n", $i, $conversation));
    }

    return $e;
}

it('the oracle suppression response is permanent and names the recipient', function () {
    $failure = PermanentMailFailure::match(new RuntimeException(OCI_SUPPRESSED));

    expect($failure)->not->toBeNull();
    expect($failure?->reason)->toBe(PermanentMailFailure::REASON_SUPPRESSED)
        ->and($failure?->email)->toBe('cesar.scapella@gmail.com');
    expect(PermanentMailFailure::isPermanent(new RuntimeException(OCI_SUPPRESSED)))->toBeTrue();
});

it('stores the server response without the symfony wrapper', function () {
    $failure = PermanentMailFailure::match(new RuntimeException(OCI_SUPPRESSED));

    expect((string) $failure?->response)->toStartWith('254 4.7.1');
    expect((string) $failure?->response)->not->toContain('Expected response code');
});

it('the oci sender ocid is not mistaken for an address', function () {
    $failure = PermanentMailFailure::match(new RuntimeException(OCI_SUPPRESSED));

    expect((string) $failure?->email)->not->toContain('ocid1');
});

it('classifies permanent responses', function (string $message, string $expectedReason) {
    $failure = PermanentMailFailure::match(new RuntimeException($message));

    expect($failure)->not->toBeNull("Expected a permanent failure for: {$message}");
    expect($failure?->reason)->toBe($expectedReason);
})->with([
    'user unknown' => [
        'Expected response code "250/251/252" but got code "550", with message "550 5.1.1 <joao@exemplo.com>: Recipient address rejected: User unknown in local recipient table".',
        PermanentMailFailure::REASON_MAILBOX_UNAVAILABLE,
    ],
    'user not local' => [
        'Expected response code "250/251/252" but got code "551", with message "551 User not local".',
        PermanentMailFailure::REASON_INVALID_RECIPIENT,
    ],
    'invalid recipient' => [
        'Expected response code "250/251/252" but got code "553", with message "553 5.1.3 Invalid recipient".',
        PermanentMailFailure::REASON_INVALID_RECIPIENT,
    ],
    'delivery not authorized' => [
        'Expected response code "250/251/252" but got code "554", with message "554 5.7.1 Delivery not authorized".',
        PermanentMailFailure::REASON_REJECTED,
    ],
    'resend suppression, no smtp code' => [
        'The request did not succeed: You are trying to send to a suppressed address (bruno@exemplo.com).',
        PermanentMailFailure::REASON_SUPPRESSED,
    ],
]);

it('keeps retrying and reporting transient responses', function (string $message) {
    expect(PermanentMailFailure::match(new RuntimeException($message)))
        ->toBeNull("Expected a transient (retryable) failure for: {$message}");
})->with([
    'rate limit' => ['Expected response code "250" but got code "421", with message "421 4.7.0 Too many messages".'],
    'mailbox busy' => ['Expected response code "250/251/252" but got code "450", with message "450 4.2.0 Mailbox busy".'],
    'temporary failure' => ['Expected response code "250/251/252" but got code "451", with message "451 4.3.0 Temporary failure, try again later".'],
    'quota exceeded' => ['Expected response code "250/251/252" but got code "452", with message "452 4.2.2 Quota exceeded for maria@exemplo.com".'],
    // 552 is mailbox full / too large: temporary, never permanent.
    'mailbox full' => ['Expected response code "250/251/252" but got code "552", with message "552 Mailbox full".'],
    'unrelated exception' => ['boom'],
]);

/**
 * Config and auth failures are misconfiguration, never permanent.
 */
it('never treats config and auth failures as permanent', function (string $message) {
    expect(PermanentMailFailure::match(new RuntimeException($message)))
        ->toBeNull("A config/auth failure must stay reportable: {$message}");
})->with([
    'bad credentials' => ['Expected response code "235" but got code "535", with message "535 5.7.8 Authentication credentials invalid".'],
    'auth required' => ['Expected response code "250" but got code "530", with message "530 5.7.0 Authentication required".'],
    'starttls required' => ['Expected response code "250" but got code "530", with message "530 Must issue a STARTTLS command first".'],
    'sender rejected' => ['Expected response code "250" but got code "550", with message "550 5.7.1 Sender address rejected: <naoresponda@orbita.social.br> not authorized to send".'],
    'spf failure' => ['Expected response code "250" but got code "550", with message "550 5.7.23 SPF validation failed for sender".'],
]);

it('a permanent failure without an address yields a null email', function () {
    $failure = PermanentMailFailure::match(new RuntimeException(
        'Expected response code "250/251/252" but got code "550", with message "550 5.1.1 Requested action not taken".'
    ));

    expect($failure)->not->toBeNull();
    expect($failure?->reason)->toBe(PermanentMailFailure::REASON_MAILBOX_UNAVAILABLE)
        ->and($failure?->email)->toBeNull();
});

it('classifies a nested previous exception', function () {
    $nested = new RuntimeException('Unable to send an email.', 0, new RuntimeException(OCI_SUPPRESSED));

    $failure = PermanentMailFailure::match($nested);

    expect($failure?->reason)->toBe(PermanentMailFailure::REASON_SUPPRESSED)
        ->and($failure?->email)->toBe('cesar.scapella@gmail.com');
});

it('an auth failure anywhere in the chain disqualifies the whole chain', function () {
    $nested = new RuntimeException(
        'Expected response code "250/251/252" but got code "550", with message "550 User unknown".',
        0,
        new RuntimeException('535 5.7.8 Authentication credentials invalid')
    );

    expect(PermanentMailFailure::match($nested))->toBeNull();
});

it('a null exception is not permanent', function () {
    expect(PermanentMailFailure::match(null))->toBeNull();
    expect(PermanentMailFailure::isPermanent(null))->toBeFalse();
});

// --- RoundRobinTransport aggregates ------------------------------------------------

it('an aggregate where both providers suppressed is permanent', function () {
    $failure = PermanentMailFailure::match(mailAggregate([
        smtpConversation('254 4.7.1 -  cesar.scapella@gmail.com is suppressed for sender ocid1.emailsender.oc1'),
        smtpConversation('550 5.7.1 cesar.scapella@gmail.com is on the suppression list'),
    ]));

    expect($failure)->not->toBeNull();
    expect($failure?->reason)->toBe(PermanentMailFailure::REASON_SUPPRESSED)
        ->and($failure?->email)->toBe('cesar.scapella@gmail.com');
});

it('an aggregate attributes the recipient from rcpt to', function () {
    // Some servers reject without naming the address; the RCPT TO we sent still has it.
    $failure = PermanentMailFailure::match(mailAggregate([
        smtpConversation('550 5.1.1 Requested action not taken'),
        smtpConversation('550 5.1.1 Requested action not taken'),
    ]));

    expect($failure?->reason)->toBe(PermanentMailFailure::REASON_MAILBOX_UNAVAILABLE)
        ->and($failure?->email)->toBe('cesar.scapella@gmail.com');
});

it('an aggregate is not permanent when one provider merely went down', function () {
    // The other provider never replied; the message may still be deliverable.
    $failure = PermanentMailFailure::match(mailAggregate([
        smtpConversation('254 4.7.1 -  cesar.scapella@gmail.com is suppressed for sender ocid1.emailsender.oc1'),
        '',
    ]));

    expect($failure)->toBeNull();
});

it('an aggregate of transient failures is not permanent', function () {
    $failure = PermanentMailFailure::match(mailAggregate([
        smtpConversation('421 4.7.0 Too many messages'),
        smtpConversation('421 4.7.0 Too many messages'),
    ]));

    expect($failure)->toBeNull();
});

it('an aggregate containing an auth failure is not permanent', function () {
    $failure = PermanentMailFailure::match(mailAggregate([
        smtpConversation('254 4.7.1 -  cesar.scapella@gmail.com is suppressed for sender ocid1.emailsender.oc1'),
        smtpConversation('535 5.7.8 Authentication credentials invalid'),
    ]));

    expect($failure)->toBeNull();
});

it('the ehlo banner does not trip the config guard', function () {
    // The EHLO greeting advertises AUTH on 250- lines; must not trip the config guard.
    $failure = PermanentMailFailure::match(mailAggregate([
        smtpConversation('254 4.7.1 -  cesar.scapella@gmail.com is suppressed for sender ocid1.emailsender.oc1'),
        smtpConversation('254 4.7.1 -  cesar.scapella@gmail.com is suppressed for sender ocid1.emailsender.oc1'),
    ]));

    expect($failure?->reason)->toBe(PermanentMailFailure::REASON_SUPPRESSED);
});

it('a single transport debug is read when the message says nothing', function () {
    $e = new TransportException('Unable to send an email.');
    $e->appendDebug(smtpConversation('550 5.1.1 <cesar.scapella@gmail.com> User unknown'));

    $failure = PermanentMailFailure::match($e);

    expect($failure?->reason)->toBe(PermanentMailFailure::REASON_MAILBOX_UNAVAILABLE)
        ->and($failure?->email)->toBe('cesar.scapella@gmail.com');
});

it('a transport exception without debug falls back to the message', function () {
    expect(PermanentMailFailure::match(new TransportException(OCI_SUPPRESSED))?->reason)
        ->toBe(PermanentMailFailure::REASON_SUPPRESSED);
});
