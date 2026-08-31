<?php

declare(strict_types=1);

/**
 * Preventive auto-hide once a target collects the report threshold, at most once.
 */

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Moderation;
use App\Models\Post;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The report flow now also alerts the staff; delivery is not what this file is about.
    Mail::fake();
});

/**
 * Monotonic across the file, so hashids never collide between tests.
 */
function autoHideSeq(): int
{
    static $n = 0;

    return ++$n;
}

function autoHidePost(string $status = 'published'): Post
{
    return Post::create([
        'user_id' => User::factory()->createOne(['email_verified_at' => now()])->id,
        'hashid' => 'ah'.autoHideSeq(),
        'title' => 'Post denunciado',
        'slug' => 'post-denunciado-'.uniqid(),
        'content' => 'corpo',
        'status' => $status,
        'published_at' => now(),
    ]);
}

function autoHideComment(string $status = 'visible'): Comment
{
    $post = autoHidePost();

    return Comment::create([
        'post_id' => $post->id,
        'user_id' => $post->user_id,
        'hashid' => 'ahc'.autoHideSeq(),
        'content' => 'comentário denunciado',
        'nesting_level' => 0,
        'status' => $status,
    ]);
}

/**
 * @return list<User>
 */
function autoHideReporters(int $count): array
{
    return array_map(
        fn () => User::factory()->createOne(['email_verified_at' => now()]),
        range(1, $count),
    );
}

function reportAll(string $type, int $id, User ...$users): void
{
    $service = app(ReportService::class);

    foreach ($users as $user) {
        $service->report((int) $user->id, $type, $id);
    }
}

it('three reports hide a comment and log a system action', function () {
    $comment = autoHideComment();
    [$a, $b, $c] = autoHideReporters(3);

    reportAll('comment', (int) $comment->id, $a, $b);
    expect((string) $comment->refresh()->status)->toBe('visible', 'two reports are not enough');

    reportAll('comment', (int) $comment->id, $c);

    expect((string) $comment->refresh()->status)->toBe('hidden');

    $log = Moderation::query()->where('action', 'auto_hide')->sole();
    expect($log->moderator_id)->toBeNull('there is no human moderator behind this')
        ->and((string) $log->target_type)->toBe('comment')
        ->and((int) $log->target_id)->toBe((int) $comment->id)
        ->and((int) $log->metadata['reports'])->toBe(3)
        ->and((int) $log->metadata['threshold'])->toBe(3)
        ->and((string) $log->metadata['previous_status'])->toBe('visible');
});

it('three reports hide a post and record its previous status', function () {
    $post = autoHidePost();
    [$a, $b, $c] = autoHideReporters(3);

    reportAll('post', (int) $post->id, $a, $b, $c);

    expect((string) $post->refresh()->status)->toBe('hidden')
        ->and((string) Moderation::query()->where('action', 'auto_hide')->sole()->metadata['previous_status'])
        ->toBe('published');
});

it('never fires twice on the same target', function () {
    $comment = autoHideComment();
    [$a, $b, $c, $d, $e] = autoHideReporters(5);

    reportAll('comment', (int) $comment->id, $a, $b, $c);
    expect((string) $comment->refresh()->status)->toBe('hidden');

    // A moderator looks at it and decides the crowd was wrong.
    $comment->update(['status' => 'visible']);

    reportAll('comment', (int) $comment->id, $d, $e);

    expect((string) $comment->refresh()->status)
        ->toBe('visible', 'o julgamento do moderador vence o da multidão')
        ->and(Moderation::query()->where('action', 'auto_hide')->count())->toBe(1);
});

it('a repeat report from the same person does not advance the count', function () {
    $comment = autoHideComment();
    [$a, $b] = autoHideReporters(2);
    $service = app(ReportService::class);

    $service->report((int) $a->id, 'comment', (int) $comment->id);
    $service->report((int) $a->id, 'comment', (int) $comment->id);
    $service->report((int) $a->id, 'comment', (int) $comment->id);
    $service->report((int) $b->id, 'comment', (int) $comment->id);

    expect((string) $comment->refresh()->status)->toBe('visible')
        ->and(Moderation::query()->where('action', 'auto_hide')->count())->toBe(0);
});

it('a threshold of zero disables the trigger', function () {
    config(['orbita.moderation.auto_hide_threshold' => 0]);

    $comment = autoHideComment();
    reportAll('comment', (int) $comment->id, ...autoHideReporters(5));

    expect((string) $comment->refresh()->status)->toBe('visible')
        ->and(Moderation::query()->where('action', 'auto_hide')->count())->toBe(0);
});

it('the threshold is configurable', function () {
    config(['orbita.moderation.auto_hide_threshold' => 2]);

    $comment = autoHideComment();
    reportAll('comment', (int) $comment->id, ...autoHideReporters(2));

    expect((string) $comment->refresh()->status)->toBe('hidden');
});

it('content a moderator already hid is left alone', function () {
    $comment = autoHideComment('hidden');
    reportAll('comment', (int) $comment->id, ...autoHideReporters(3));

    expect((string) $comment->refresh()->status)->toBe('hidden')
        ->and(Moderation::query()->where('action', 'auto_hide')->count())
        ->toBe(0, 'nada mudou, nada a registrar');
});

it('a closed post is eligible but a draft is not', function () {
    $closed = autoHidePost('closed');
    reportAll('post', (int) $closed->id, ...autoHideReporters(3));
    expect((string) $closed->refresh()->status)->toBe('hidden', 'a closed post is still read publicly');

    $draft = autoHidePost('draft');
    reportAll('post', (int) $draft->id, ...autoHideReporters(3));
    expect((string) $draft->refresh()->status)->toBe('draft', 'um rascunho nunca foi público');
});
