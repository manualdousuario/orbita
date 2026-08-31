<?php

declare(strict_types=1);

/**
 * ReportService: one report per user per target, triage via read state.
 */

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

/**
 * Monotonic across the file, so hashids never collide between tests.
 */
function reportServiceSeq(): int
{
    static $n = 0;

    return ++$n;
}

function reportedPost(): Post
{
    return Post::create([
        'user_id' => User::factory()->createOne(['email_verified_at' => now()])->id,
        'hashid' => 'rp'.reportServiceSeq(),
        'title' => 'Post reportado',
        'slug' => 'post-reportado-'.uniqid(),
        'content' => 'corpo',
        'status' => 'published',
    ]);
}

it('reporting a post creates an unread report', function () {
    $user = User::factory()->createOne(['email_verified_at' => now()]);
    $post = reportedPost();

    $report = app(ReportService::class)->report((int) $user->id, 'post', (int) $post->id, 'spam');

    expect($report)->not->toBeNull();
    expect($report?->isRead())->toBeFalse();

    assertDatabaseHas('reports', [
        'user_id' => $user->id,
        'reportable_type' => 'post',
        'reportable_id' => $post->id,
        'reason' => 'spam',
        'read_at' => null,
    ]);
});

it('reporting a comment works', function () {
    $user = User::factory()->createOne(['email_verified_at' => now()]);
    $post = reportedPost();
    $comment = Comment::create([
        'post_id' => $post->id,
        'user_id' => $post->user_id,
        'hashid' => 'rc'.reportServiceSeq(),
        'content' => 'comentário',
        'status' => 'visible',
    ]);

    $report = app(ReportService::class)->report((int) $user->id, 'comment', (int) $comment->id);

    expect($report)->not->toBeNull();
    expect($report?->reason)->toBeNull('the reason is optional');

    assertDatabaseHas('reports', [
        'reportable_type' => 'comment',
        'reportable_id' => $comment->id,
    ]);
});

it('a repeat report is silently ignored', function () {
    $user = User::factory()->createOne(['email_verified_at' => now()]);
    $post = reportedPost();

    $service = app(ReportService::class);
    $first = $service->report((int) $user->id, 'post', (int) $post->id, 'motivo original');
    $second = $service->report((int) $user->id, 'post', (int) $post->id, 'outro motivo');

    expect(Report::count())->toBe(1, 'o unique (user, type, id) impede duplicatas')
        ->and((int) $second?->id)->toBe((int) $first?->id)
        ->and((string) $second?->reason)->toBe('motivo original', 'the duplicate does not overwrite the reason');
});

it('invalid type or target returns null', function () {
    $user = User::factory()->createOne(['email_verified_at' => now()]);
    $service = app(ReportService::class);

    expect($service->report((int) $user->id, 'user', (int) $user->id))->toBeNull()
        ->and($service->report((int) $user->id, 'post', 999999))->toBeNull()
        ->and(Report::count())->toBe(0);
});

it('reported ids for batches the lookup', function () {
    $user = User::factory()->createOne(['email_verified_at' => now()]);
    $posts = [reportedPost(), reportedPost(), reportedPost()];
    $service = app(ReportService::class);

    $service->report((int) $user->id, 'post', (int) $posts[0]->id);
    $service->report((int) $user->id, 'post', (int) $posts[2]->id);

    $ids = $service->reportedIdsFor((int) $user->id, 'post', array_map(fn ($p) => (int) $p->id, $posts));

    sort($ids);
    expect($ids)->toBe([(int) $posts[0]->id, (int) $posts[2]->id])
        ->and($service->hasReported((int) $user->id, 'post', (int) $posts[0]->id))->toBeTrue()
        ->and($service->hasReported((int) $user->id, 'post', (int) $posts[1]->id))->toBeFalse();
});

it('mark read and unread drive the moderation triage', function () {
    $reporter = User::factory()->createOne(['email_verified_at' => now()]);
    $moderator = User::factory()->createOne(['role' => 'moderator', 'email_verified_at' => now()]);
    $post = reportedPost();

    $service = app(ReportService::class);
    $report = $service->report((int) $reporter->id, 'post', (int) $post->id);

    $service->markRead($report, (int) $moderator->id);
    $report->refresh();
    expect($report->isRead())->toBeTrue()
        ->and((int) $report->read_by)->toBe((int) $moderator->id);

    $service->markUnread($report);
    $report->refresh();
    expect($report->isRead())->toBeFalse()
        ->and($report->read_by)->toBeNull();
});
