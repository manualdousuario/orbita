<?php

use Spatie\ScheduleMonitor\Jobs\PingOhDearJob;
use Spatie\ScheduleMonitor\Models\MonitoredScheduledTask;
use Spatie\ScheduleMonitor\Models\MonitoredScheduledTaskLogItem;

return [
    'delete_log_items_older_than_days' => 30,
    'date_format' => 'Y-m-d H:i:s',

    'models' => [
        'monitored_scheduled_task' => MonitoredScheduledTask::class,
        'monitored_scheduled_log_item' => MonitoredScheduledTaskLogItem::class,
    ],

    'oh_dear' => [
        'api_token' => env('OH_DEAR_API_TOKEN', ''),
        'monitor_id' => env('OH_DEAR_MONITOR_ID'),
        'queue' => env('OH_DEAR_QUEUE'),
        'ping_oh_dear_job' => PingOhDearJob::class,
        'retry_job_for_minutes' => 10,
        'retry_delay_ms' => env('OH_DEAR_RETRY_DELAY_MS', 10_000),
        'silence_ping_oh_dear_job_in_horizon' => true,
        'send_starting_ping' => env('OH_DEAR_SEND_STARTING_PING', false),
        'grace_time_in_minutes' => 5,
        'endpoint_url' => env('OH_DEAR_PING_ENDPOINT_URL'),
        'api_url' => env('OH_DEAR_API_URL', 'https://ohdear.app/api/'),
        'debug_logging' => env('OH_DEAR_DEBUG_LOGGING', false),
    ],
];
