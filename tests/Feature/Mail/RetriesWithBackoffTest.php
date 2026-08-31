<?php

declare(strict_types=1);

/**
 * Every queued mailable and notification must use the shared backoff trait.
 */

namespace Tests\Feature\Mail;

use App\Mail\Concerns\RetriesWithBackoff;
use App\Notifications\Concerns\RetriesWithBackoff as NotificationRetriesWithBackoff;
use Illuminate\Contracts\Queue\ShouldQueue;
use Symfony\Component\Finder\Finder;

function expectEveryQueuedClassUses(string $directory, string $trait): void
{
    $checked = [];

    foreach (Finder::create()->files()->name('*.php')->in(app_path($directory)) as $file) {
        $class = 'App\\'.$directory.'\\'.str_replace(
            ['/', '.php'],
            ['\\', ''],
            (string) str($file->getRelativePathname())
        );

        if (! class_exists($class) || ! is_subclass_of($class, ShouldQueue::class)) {
            continue;
        }

        expect(in_array($trait, class_uses_recursive($class), true))->toBeTrue(
            "{$class} implements ShouldQueue but does not use {$trait} -- ".
            'a rate-limit/quota error on it will exhaust the default --tries within '.
            'seconds and never retry, and a permanent failure will be logged at error '.
            'level, landing in Glitchtip as if it were a bug.'
        );

        $checked[] = $class;
    }

    expect($checked)->not->toBeEmpty("Expected to find at least one queued class under app/{$directory}.");
}

it('every queued mailable uses the shared backoff trait', function () {
    expectEveryQueuedClassUses('Mail', RetriesWithBackoff::class);
});

it('every queued notification uses the shared backoff trait', function () {
    expectEveryQueuedClassUses('Notifications', NotificationRetriesWithBackoff::class);
});
