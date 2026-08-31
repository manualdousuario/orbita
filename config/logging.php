<?php

use App\Logging\CapBelowWarning;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\PsrLogMessageProcessor;

// Log files: app.log (debug+), error.log (warning+), access.log (JSON). Exceptions are reported to Glitchtip (Sentry).

$logPath = rtrim((string) env('LOG_PATH', storage_path('logs')), '/\\');

$logLevel = env('LOG_LEVEL', in_array(env('APP_ENV', 'production'), ['local', 'testing'], true) ? 'debug' : 'info');

$logRetentionDays = (int) env('LOG_RETENTION_DAYS', 14);

$logStack = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('LOG_STACK', 'app,error'))
), 'strlen'));

return [

    'default' => env('LOG_CHANNEL', 'stack'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => $logStack !== [] ? $logStack : ['null'],
            'ignore_exceptions' => false,
        ],

        'app' => [
            'driver' => 'daily',
            'path' => $logPath.'/app.log',
            'level' => $logLevel,
            'days' => $logRetentionDays,
            'replace_placeholders' => true,
            'tap' => [CapBelowWarning::class],
        ],

        'error' => [
            'driver' => 'daily',
            'path' => $logPath.'/error.log',
            'level' => env('LOG_ERROR_LEVEL', 'warning'),
            'days' => $logRetentionDays,
            'replace_placeholders' => true,
        ],

        'access' => [
            'driver' => 'daily',
            'path' => $logPath.'/access.log',
            'level' => 'info',
            'days' => $logRetentionDays,
            'name' => 'access',
            'formatter' => JsonFormatter::class,
        ],

        'single' => [
            'driver' => 'single',
            'path' => $logPath.'/laravel.log',
            'level' => $logLevel,
            'replace_placeholders' => true,
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => $logLevel,
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => $logPath.'/emergency.log',
        ],

    ],

];
