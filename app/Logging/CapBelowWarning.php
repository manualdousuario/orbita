<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Handler\FilterHandler;
use Monolog\Level;

/**
 * Caps every log handler to the Debug–Notice range.
 */
class CapBelowWarning
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        $monolog->setHandlers(array_map(
            fn ($handler) => new FilterHandler($handler, Level::Debug, Level::Notice, bubble: true),
            $monolog->getHandlers(),
        ));
    }
}
