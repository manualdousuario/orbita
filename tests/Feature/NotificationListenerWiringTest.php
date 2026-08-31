<?php

declare(strict_types=1);

/**
 * Each notification event must register exactly one listener.
 */

namespace Tests\Feature;

use App\Events\CommentCreated;
use App\Events\CommentReplied;
use App\Events\EmailChangeRequested;
use App\Events\UsersMentioned;
use Illuminate\Support\Facades\Event;

it('each notification event has exactly the listeners it needs', function () {
    $expected = [
        CommentCreated::class => 2,
        CommentReplied::class => 2,
        UsersMentioned::class => 1,
        EmailChangeRequested::class => 1,
    ];

    foreach ($expected as $event => $count) {
        expect(Event::getListeners($event))->toHaveCount(
            $count,
            "{$event} must have exactly {$count} listener(s); a duplicate registration sends every notification twice.",
        );
    }
});
