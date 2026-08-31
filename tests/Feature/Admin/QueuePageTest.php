<?php

declare(strict_types=1);

/**
 * The admin queue page for failed jobs.
 */

namespace Tests\Feature\Admin;

use App\Filament\Pages\Queue;
use App\Jobs\ResyncReactionScores;
use App\Models\FailedJob;
use App\Support\QueueMetrics;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue as QueueFacade;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Feature\Admin\Concerns\AdminUsers;

uses(RefreshDatabase::class);

function failedJob(): FailedJob
{
    DB::table('failed_jobs')->insert([
        'uuid' => 'f1b1b0b0-0000-4000-8000-000000000001',
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode([
            'uuid' => 'f1b1b0b0-0000-4000-8000-000000000001',
            'displayName' => 'App\\Jobs\\SendWelcomeEmail',
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
        ]),
        'exception' => "Illuminate\\Database\\QueryException: SQLSTATE[42S02]: Base table or view not found in /var/www/app/Jobs/SendWelcomeEmail.php:42\nStack trace:\n#0 /var/www/vendor/laravel/framework/src/Illuminate/Queue/Jobs/Job.php(98): App\\Jobs\\SendWelcomeEmail->handle()\n#1 {main}",
        'failed_at' => now(),
    ]);

    return FailedJob::query()->firstOrFail();
}

it('splits the exception into class message and trace', function () {
    $job = failedJob();

    expect($job->jobName)->toBe('App\Jobs\SendWelcomeEmail')
        ->and($job->exceptionClass)->toBe('Illuminate\Database\QueryException')
        ->and($job->exceptionMessage)->toBe('SQLSTATE[42S02]: Base table or view not found');

    expect($job->exceptionTrace)->toStartWith('#0 /var/www/vendor');
});

it('keeps multi line messages out of the stack trace', function () {
    $job = failedJob();
    $job->exception = "Illuminate\\Database\\QueryException: SQLSTATE[23000]: Integrity constraint violation in /var/www/app/Jobs/Sync.php:12\n(Connection: mysql, SQL: insert into `posts` (`title`) values (?))\nStack trace:\n#0 {main}";
    $job->save();

    $job->refresh();

    expect($job->exceptionClass)->toBe('Illuminate\Database\QueryException')
        ->and($job->exceptionMessage)->toBe(
            "SQLSTATE[23000]: Integrity constraint violation\n(Connection: mysql, SQL: insert into `posts` (`title`) values (?))",
        )
        ->and($job->exceptionTrace)->toBe('#0 {main}');
});

it('falls back when the exception has no trace', function () {
    $job = failedJob();
    $job->exception = 'Something went very wrong';
    $job->save();

    $job->refresh();

    expect($job->exceptionClass)->toBe('Something went very wrong')
        ->and($job->exceptionMessage)->toBe('Something went very wrong')
        ->and($job->exceptionTrace)->toBe('');
});

it('admin sees the exception summary in the table', function () {
    failedJob();

    Livewire::actingAs(AdminUsers::admin('queue_admin'))
        ->test(Queue::class)
        ->assertCanSeeTableRecords(FailedJob::all())
        ->assertSee('Illuminate\Database\QueryException')
        ->assertSee('App\Jobs\SendWelcomeEmail');
});

it('admin can open the exception details modal', function () {
    $job = failedJob();

    Livewire::actingAs(AdminUsers::admin('queue_admin'))
        ->test(Queue::class)
        ->mountAction(TestAction::make('viewException')->table($job))
        ->assertActionMounted(TestAction::make('viewException')->table($job));

    $modal = view('filament.pages.queue-exception', ['record' => $job])->render();

    expect($modal)->toContain('Stack trace')
        ->toContain('SQLSTATE[42S02]: Base table or view not found')
        ->toContain('App\Jobs\SendWelcomeEmail');
});

it('table search matches the exception text', function () {
    failedJob();

    Livewire::actingAs(AdminUsers::admin('queue_admin'))
        ->test(Queue::class)
        ->searchTable('42S02')
        ->assertCanSeeTableRecords(FailedJob::all());
});

it('the pending count reads the queue driver, not a table', function () {
    expect(Schema::hasTable('jobs'))->toBeFalse();

    Livewire::actingAs(AdminUsers::admin('queue_admin'))
        ->test(Queue::class)
        ->assertOk();

    expect(app(Queue::class)->pendingCount())->toBe(0);
});

it('the pending count sums every queue the worker drains', function () {
    QueueFacade::fake();

    QueueFacade::push(new ResyncReactionScores, '', 'emails');
    QueueFacade::push(new ResyncReactionScores, '', 'default');
    QueueFacade::push(new ResyncReactionScores, '', 'nao-processada');

    expect(QueueMetrics::pending())->toBe(2);
});
