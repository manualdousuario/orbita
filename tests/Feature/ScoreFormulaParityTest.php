<?php

declare(strict_types=1);

/**
 * The PHP and SQL score formulas must agree on every input.
 */

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\ReactionType;
use App\Models\User;
use App\Models\UserReaction;
use App\Services\CounterReconciliationService;
use App\Services\RankingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Author, second actor and reaction type for the test currently running;
 * beforeEach() reseeds all three.
 */
function sfpAuthor(?User $set = null): User
{
    static $author = null;

    if ($set instanceof User) {
        $author = $set;
    }

    return $author;
}

function sfpOther(?User $set = null): User
{
    static $other = null;

    if ($set instanceof User) {
        $other = $set;
    }

    return $other;
}

function sfpLike(?ReactionType $set = null): ReactionType
{
    static $like = null;

    if ($set instanceof ReactionType) {
        $like = $set;
    }

    return $like;
}

beforeEach(function () {
    sfpAuthor(User::factory()->createOne(['username' => 'sfp_author']));
    sfpOther(User::factory()->createOne(['username' => 'sfp_other']));
    sfpLike(ReactionType::firstOrCreate(
        ['slug' => 'like'],
        ['name' => 'Curtir', 'emoji' => '👍', 'score' => 5, 'allowed_roles' => ['user'], 'display_order' => 1],
    ));
});

function sfpScoreOf(Post $post): int
{
    return (int) DB::table('posts')->where('id', $post->id)->value('score');
}

/** posts.hashid is only 10 chars wide, so the counter has to stay short. */
function sfpSeq(): int
{
    static $n = 0;

    return ++$n;
}

function sfpSeedPost(int $ageHours, int $comments, int $reactions, bool $publishedNull): Post
{
    $when = now()->subHours($ageHours);
    $n = sfpSeq();

    $post = Post::create([
        'user_id' => sfpAuthor()->id,
        'hashid' => 'sfp'.$n,
        'title' => 'T',
        'slug' => 'sfp-'.$n,
        'content' => 'c',
        'status' => 'published',
        'published_at' => $publishedNull ? null : $when,
    ]);

    DB::table('posts')->where('id', $post->id)->update(['created_at' => $when]);

    for ($i = 0; $i < $comments; $i++) {
        Comment::withoutEvents(fn () => Comment::create([
            'user_id' => sfpOther()->id, 'post_id' => $post->id, 'hashid' => 'c'.$n.'_'.$i,
            'content' => 'x', 'status' => 'visible', 'nesting_level' => 0,
        ]));
    }

    for ($i = 0; $i < $reactions; $i++) {
        $u = User::factory()->createOne(['username' => 'sfp_u'.$n.'_'.$i]);
        UserReaction::create([
            'user_id' => $u->id, 'reaction_type_id' => sfpLike()->id,
            'reactable_type' => 'post', 'reactable_id' => $post->id,
        ]);
    }

    return $post->fresh();
}

it('php and sql score formulas agree', function (int $ageHours, int $comments, int $reactions, bool $publishedNull) {
    $ranking = app(RankingService::class);
    $post = sfpSeedPost($ageHours, $comments, $reactions, $publishedNull);

    $php = $ranking->calculatePostScore((int) $post->id);

    $ranking->recalculatePostScore((int) $post->id);
    $sqlOne = sfpScoreOf($post);

    // Poison the column so recalculateAllScores has to actually write it.
    DB::table('posts')->where('id', $post->id)->update(['score' => -999]);
    $ranking->recalculateAllScores();
    $sqlAll = sfpScoreOf($post);

    expect($sqlOne)->toBe($php, "PHP e SQL-de-um-post divergem (php={$php}, sql={$sqlOne})")
        ->and($sqlAll)->toBe($sqlOne, "SQL-de-um-post e SQL-de-todos divergem ({$sqlOne} vs {$sqlAll})")
        ->and($sqlAll)->toBeGreaterThanOrEqual(0, 'the score can never be negative');
})->with([
    // ageHours, comments, reactions, publishedAtNull
    'recém-publicado' => [0, 3, 1, false],
    'dentro do primeiro decay' => [2, 3, 1, false],
    'exatamente um decay' => [3, 3, 1, false],
    'um decay e resto' => [5, 3, 1, false],
    'antigo: penalidade excede o ganho' => [30, 1, 0, false],
    'sem sinal algum' => [0, 0, 0, false],
    'published_at nulo, cai em created_at' => [4, 2, 1, true],
    'muitos comentários, zero reações' => [1, 10, 0, false],
    'borda: 1 hora antes do decay' => [2, 0, 0, false],
]);

/** recalculatePostScore takes two bindings; a swapped order would silently score the wrong row. */
it('single post recalculation only touches that post', function () {
    $ranking = app(RankingService::class);

    $target = sfpSeedPost(0, 2, 1, false);
    $bystander = sfpSeedPost(0, 0, 0, false);

    DB::table('posts')->whereIn('id', [$target->id, $bystander->id])->update(['score' => 777]);

    $ranking->recalculatePostScore((int) $target->id);

    expect(sfpScoreOf($target))->toBe(7, 'the target post must be recalculated')
        ->and(sfpScoreOf($bystander))->toBe(777, 'no other post may be touched');
});

/** A non-published post is excluded by the WHERE clause, so its stored score stays put. */
it('a closed post keeps its stored score', function () {
    $ranking = app(RankingService::class);

    $post = sfpSeedPost(0, 3, 0, false);
    DB::table('posts')->where('id', $post->id)->update(['status' => 'closed', 'score' => 42]);

    $ranking->recalculatePostScore((int) $post->id);

    expect(sfpScoreOf($post))->toBe(42);
});

/**
 * A zero score_decay_hours must not break scoring (division by zero / NULL write).
 */
it('a zero decay setting does not break scoring', function () {
    config(['orbita.posts.score_decay_hours' => 0]);

    $ranking = new RankingService;
    $post = sfpSeedPost(0, 2, 1, false);

    $php = $ranking->calculatePostScore((int) $post->id);
    $ranking->recalculatePostScore((int) $post->id);

    expect(sfpScoreOf($post))->toBe($php)
        ->and(sfpScoreOf($post))->toBeGreaterThanOrEqual(0);
});

/** A post published in the future has negative elapsed hours; both formulas must agree. */
it('a future published at agrees between php and sql', function () {
    $ranking = app(RankingService::class);

    foreach ([-1, -2, -4, -7] as $ageHours) {
        $post = sfpSeedPost($ageHours, 1, 1, false);

        $php = $ranking->calculatePostScore((int) $post->id);
        $ranking->recalculatePostScore((int) $post->id);

        expect(sfpScoreOf($post))->toBe($php, "divergiram para published_at daqui a {$ageHours}h");
    }
});

/** The scheduled reconcile must actually recompute score, or the time decay never advances. */
it('the reconcile recomputes score', function () {
    $post = sfpSeedPost(0, 3, 1, false);
    DB::table('posts')->where('id', $post->id)->update(['score' => 999]);

    $result = app(CounterReconciliationService::class)->reconcileAll();

    // reconcile must recalculate the score, not only the counters
    expect($result)->toHaveKey('posts.score');
    expect(sfpScoreOf($post))->toBe(8);
});

/** --dry-run must predict exactly what the real run changes, score included. */
it('reconcile dry run agrees with the real run on score', function () {
    $reconcile = app(CounterReconciliationService::class);

    $stale = sfpSeedPost(0, 2, 1, false);
    $fresh = sfpSeedPost(0, 0, 0, false);

    DB::table('posts')->where('id', $stale->id)->update(['score' => 123]);
    app(RankingService::class)->recalculatePostScore((int) $fresh->id);

    $preview = $reconcile->previewReconcileAll();
    // --dry-run must list posts.score
    expect($preview)->toHaveKey('posts.score');
    expect($preview['posts.score'])->toBe(1, 'only one post has a wrong score');

    $applied = $reconcile->reconcileAll();
    expect($applied['posts.score'])->toBe($preview['posts.score'], 'the preview and the run must agree');

    // And a second run has nothing left to change.
    expect($reconcile->reconcileAll()['posts.score'])->toBe(0);
});

/**
 * The SQL score must not depend on the database session timezone.
 */
it('the sql score does not depend on the database session timezone', function () {
    $ranking = app(RankingService::class);
    $post = sfpSeedPost(5, 3, 1, false);

    $ranking->recalculatePostScore((int) $post->id);
    $expected = sfpScoreOf($post);

    foreach (['+00:00', '-03:00', '+09:00'] as $tz) {
        DB::statement("SET time_zone = '{$tz}'");

        DB::table('posts')->where('id', $post->id)->update(['score' => 999]);
        $ranking->recalculatePostScore((int) $post->id);

        expect(sfpScoreOf($post))->toBe($expected, "o score mudou com a sessao do banco em {$tz}")
            ->and($ranking->countPostsWithStaleScore())->toBe(0, "o preview divergiu com a sessao em {$tz}");
    }

    DB::statement("SET time_zone = 'SYSTEM'");
});

/** The bulk recalculation must not depend on a single statement covering the whole table. */
it('bulk recalculation handles more posts than one chunk', function () {
    $ranking = app(RankingService::class);

    $posts = [];
    for ($i = 0; $i < 12; $i++) {
        $posts[] = sfpSeedPost(0, $i % 3, 0, false);
    }

    DB::table('posts')->update(['score' => 999]);

    $changed = $ranking->recalculateAllScores();

    expect($changed)->toBe(12, 'every post must be fixed, even across several batches');

    foreach ($posts as $p) {
        expect(sfpScoreOf($p))->toBe($ranking->calculatePostScore((int) $p->id));
    }

    expect($ranking->recalculateAllScores())->toBe(0, 'the second pass changes nothing');
});
