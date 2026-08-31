<?php

declare(strict_types=1);

/**
 * Regressions for the findings that survived triage of the Open Code Review report.
 */

namespace Tests\Feature;

use App\Jobs\ResyncReactionScores;
use App\Models\Comment;
use App\Models\Post;
use App\Models\ReactionType;
use App\Models\User;
use App\Models\UserReaction;
use App\Models\UserToken;
use App\Services\CounterReconciliationService;
use App\Services\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/** A comment carrying one reaction worth 5 points, with a deliberately wrong stored score. */
function rfCommentWithStaleScore(): Comment
{
    $author = User::factory()->createOne();
    $reactor = User::factory()->createOne();

    $type = ReactionType::firstOrCreate(
        ['slug' => 'like'],
        ['name' => 'Curtir', 'emoji' => '👍', 'score' => 5, 'allowed_roles' => ['user'], 'display_order' => 1],
    );

    $post = Post::create([
        'user_id' => $author->id,
        'hashid' => 'rf'.random_int(1000, 9999),
        'title' => 'T',
        'slug' => 'rf-'.random_int(1000, 9999),
        'content' => 'c',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $comment = Comment::withoutEvents(fn () => Comment::create([
        'user_id' => $author->id, 'post_id' => $post->id, 'hashid' => 'rfc'.random_int(1000, 9999),
        'content' => 'x', 'status' => 'visible', 'nesting_level' => 0,
    ]));

    UserReaction::create([
        'user_id' => $reactor->id, 'reaction_type_id' => $type->id,
        'reactable_type' => 'comment', 'reactable_id' => $comment->id,
    ]);

    DB::table('comments')->where('id', $comment->id)->update(['score' => 999]);

    return $comment;
}

function rfScoreOf(Comment $comment): int
{
    return (int) DB::table('comments')->where('id', $comment->id)->value('score');
}

// -------------------------------------------------------- comments.score reconcile

/**
 * ResyncReactionScores called a method that did not exist, so it threw on every run and
 * comments.score was never recomputed by anything.
 */
it('reconciles comments.score from the reactions', function () {
    $comment = rfCommentWithStaleScore();

    $rows = app(CounterReconciliationService::class)->reconcileCommentScore();

    expect($rows)->toBe(1)
        ->and(rfScoreOf($comment))->toBe(5);
});

it('the hourly reconcile covers comments.score', function () {
    $comment = rfCommentWithStaleScore();

    $result = app(CounterReconciliationService::class)->reconcileAll();

    expect($result)->toHaveKey('comments.score')
        ->and(rfScoreOf($comment))->toBe(5);
});

it('the dry run predicts the comments.score rows', function () {
    rfCommentWithStaleScore();

    $reconcile = app(CounterReconciliationService::class);

    expect($reconcile->previewCommentScore())->toBe(1)
        ->and($reconcile->reconcileCommentScore())->toBe(1)
        ->and($reconcile->previewCommentScore())->toBe(0);
});

it('the resync job completes instead of throwing', function () {
    $comment = rfCommentWithStaleScore();

    app()->call([new ResyncReactionScores, 'handle']);

    expect(rfScoreOf($comment))->toBe(5);
});

// ------------------------------------------------------------- single-use tokens

it('a token can only be consumed once', function () {
    $user = User::factory()->createOne();

    $token = UserToken::create([
        'user_id' => $user->id,
        'token' => UserToken::hashToken('plain-token'),
        'token_type' => 'account_deletion',
        'email' => $user->email,
        'request_ip' => '127.0.0.1',
        'is_used' => false,
        'expires_at' => now()->addHour(),
    ]);

    expect($token->consume())->toBeTrue()
        ->and($token->is_used)->toBeTrue();

    // A second holder of the same row loses the race and must not run the side effect.
    $racer = UserToken::query()->find($token->getKey());
    expect($racer->consume())->toBeFalse();
});

// ------------------------------------------------------------ search LIMIT clamps

it('search survives a client-supplied negative page size', function () {
    $result = app(SearchService::class)->searchUnifiedPaginated('orbita', [], -1, 0);

    expect($result->perPage())->toBeGreaterThan(0)
        ->and($result->currentPage())->toBeGreaterThan(0);
});

// ------------------------------------------------------------------- url schemes

it('only http and https survive the url validation', function (string $url, bool $expected) {
    $passes = Validator::make(['url' => $url], ['url' => ['url:http,https']])->passes();

    expect($passes)->toBe($expected, $url);
})->with([
    'https' => ['https://example.com/x', true],
    'http' => ['http://example.com/x', true],
    'javascript' => ['javascript:alert(1)', false],
    'data' => ['data://text/html;base64,PHN2Zz48L3N2Zz4=', false],
    'view-source' => ['view-source://evil.example/x', false],
    'ftp' => ['ftp://evil.example/x', false],
]);
