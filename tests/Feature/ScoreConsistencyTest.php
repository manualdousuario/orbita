<?php

declare(strict_types=1);

/**
 * Every writer of posts.score must compute the same number.
 */

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\ReactionType;
use App\Models\User;
use App\Services\RankingService;
use App\Services\ReactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function storedScoreOf(Post $post): int
{
    return (int) DB::table('posts')->where('id', $post->id)->value('score');
}

/** What the reconcile cron would compute for every post, i.e. the reference value. */
function reconciledScoreOf(Post $post): int
{
    app(RankingService::class)->recalculateAllScores();

    return storedScoreOf($post);
}

/**
 * @return array{Post, User, ReactionType}
 */
function seedPostWithComments(int $comments): array
{
    $author = User::factory()->createOne(['username' => 'sc_author']);
    $reader = User::factory()->createOne(['username' => 'sc_reader']);

    $post = Post::create([
        'user_id' => $author->id, 'hashid' => 'scr1', 'title' => 'T', 'slug' => 't',
        'content' => 'c', 'status' => 'published', 'published_at' => now(),
    ]);

    for ($i = 0; $i < $comments; $i++) {
        Comment::create([
            'user_id' => $reader->id, 'post_id' => $post->id, 'hashid' => 'scc'.$i,
            'content' => 'comentario '.$i, 'status' => 'visible', 'nesting_level' => 0,
        ]);
    }

    $like = ReactionType::firstOrCreate(
        ['slug' => 'like'],
        ['name' => 'Curtir', 'emoji' => '👍', 'score' => 5, 'allowed_roles' => ['user'], 'display_order' => 1],
    );

    return [$post->fresh(), $reader, $like];
}

it('a reaction toggle writes the same score the cron would', function () {
    [$post, $reader, $like] = seedPostWithComments(3);

    app(ReactionService::class)->toggle((int) $reader->id, 'post', (int) $post->id, $like, $post);

    $afterReaction = storedScoreOf($post);

    // reaction score 5 + 3 non-author comments - 0 hours of decay.
    expect($afterReaction)->toBe(8, 'the reaction alone does not define the score');

    expect($afterReaction)->toBe(
        reconciledScoreOf($post),
        'reagir e reconciliar devem produzir o mesmo score',
    );
});

it('a comment write writes the same score the cron would', function () {
    [$post, $reader, $like] = seedPostWithComments(1);

    app(ReactionService::class)->toggle((int) $reader->id, 'post', (int) $post->id, $like, $post);

    // A new comment changes the comment component of the score.
    Comment::create([
        'user_id' => $reader->id, 'post_id' => $post->id, 'hashid' => 'scnew',
        'content' => 'mais um', 'status' => 'visible', 'nesting_level' => 0,
    ]);

    $afterComment = storedScoreOf($post);

    expect($afterComment)->toBe(7, 'score = 5 (reaction) + 2 (comments)')
        ->and($afterComment)->toBe(reconciledScoreOf($post));
});

/** The author's own comments never count towards their post's score. */
it('author comments do not inflate the score', function () {
    [$post] = seedPostWithComments(0);

    Comment::create([
        'user_id' => $post->user_id, 'post_id' => $post->id, 'hashid' => 'scself',
        'content' => 'autocomentario', 'status' => 'visible', 'nesting_level' => 0,
    ]);

    expect(storedScoreOf($post))->toBe(0);
    expect(storedScoreOf($post))->toBe(reconciledScoreOf($post));
});

/** comments.score IS the reaction sum -- no comment/decay component applies to it. */
it('comment score remains the reaction sum', function () {
    [$post, $reader, $like] = seedPostWithComments(1);

    $comment = Comment::query()->where('post_id', $post->id)->firstOrFail();

    app(ReactionService::class)->toggle((int) $reader->id, 'comment', (int) $comment->id, $like, $comment);

    expect((int) $comment->fresh()?->score)->toBe(5)
        ->and((int) $comment->fresh()?->reaction_count)->toBe(1);
});
