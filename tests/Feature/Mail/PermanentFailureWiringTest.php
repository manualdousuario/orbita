<?php

declare(strict_types=1);

/**
 * Guards the retry/report closures registered in bootstrap/app.php.
 */

namespace Tests\Feature\Mail;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use PHPUnit\Framework\Assert;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;

const OCI_SUPPRESSED = 'Expected response code "250/251/252" but got code "254", '
    .'with message "254 4.7.1 -  cesar.scapella@gmail.com is suppressed for sender '
    .'ocid1.emailsender.oc1.sa-saopaulo-1.amaaaaaartenwriavutaj4vpb4yueyxuudcgwmxpbo5mi4ky7l3isqmmcn6a".';

/*
 * The closures under test live on the framework handler, so anything else means
 * bootstrap/app.php stopped wiring them.
 *
 * Assert::assertInstanceOf, not expect(): only the PHPUnit assertion carries a
 * type-narrowing annotation. Outside the testing environment the container
 * hands back Collision's handler, so PHPStan needs that narrowing to follow
 * the calls below.
 */
function mailExceptionHandler(): Handler
{
    $handler = app(ExceptionHandler::class);

    Assert::assertInstanceOf(Handler::class, $handler);

    return $handler;
}

it('a suppressed recipient stops queue retries', function () {
    expect(mailExceptionHandler()->shouldStopRetries(new RuntimeException(OCI_SUPPRESSED)))->toBeTrue();
});

it('a suppressed recipient is not reported', function () {
    expect(mailExceptionHandler()->shouldReport(new RuntimeException(OCI_SUPPRESSED)))->toBeFalse();
});

it('a transient failure still retries and reports', function () {
    $transient = new RuntimeException(
        'Expected response code "250/251/252" but got code "451", with message "451 4.3.0 Temporary failure".'
    );

    expect(mailExceptionHandler()->shouldStopRetries($transient))->toBeFalse()
        ->and(mailExceptionHandler()->shouldReport($transient))->toBeTrue();
});

it('an smtp auth failure still reaches the reporter', function () {
    // A broken OCI credential must keep reaching the error reporter.
    $auth = new RuntimeException(
        'Expected response code "235" but got code "535", with message "535 5.7.8 Authentication credentials invalid".'
    );

    expect(mailExceptionHandler()->shouldStopRetries($auth))->toBeFalse()
        ->and(mailExceptionHandler()->shouldReport($auth))->toBeTrue();
});

it('the round robin aggregate is what production actually throws', function () {
    // RoundRobinTransport swallows the server exception and throws this aggregate instead.
    $aggregate = new TransportException('All transports failed.');

    foreach (['smtp://oci', 'smtp://resend'] as $transport) {
        $aggregate->appendDebug(sprintf(
            "Transport \"%s\": [2026-08-01T11:00:34.000000+00:00] > RCPT TO:<cesar.scapella@gmail.com>\n"
            ."[2026-08-01T11:00:34.000000+00:00] < 254 4.7.1 -  cesar.scapella@gmail.com is suppressed for sender ocid1.emailsender.oc1\n",
            $transport
        ));
    }

    expect(mailExceptionHandler()->shouldStopRetries($aggregate))->toBeTrue()
        ->and(mailExceptionHandler()->shouldReport($aggregate))->toBeFalse();
});

it('a round robin aggregate with one provider down still retries', function () {
    $aggregate = new TransportException('All transports failed.');
    $aggregate->appendDebug(
        "Transport \"smtp://oci\": [2026-08-01T11:00:34.000000+00:00] > RCPT TO:<cesar.scapella@gmail.com>\n"
        ."[2026-08-01T11:00:34.000000+00:00] < 254 4.7.1 -  cesar.scapella@gmail.com is suppressed for sender ocid1.emailsender.oc1\n"
    );
    $aggregate->appendDebug("Transport \"smtp://resend\": \n");

    expect(mailExceptionHandler()->shouldStopRetries($aggregate))->toBeFalse()
        ->and(mailExceptionHandler()->shouldReport($aggregate))->toBeTrue();
});
