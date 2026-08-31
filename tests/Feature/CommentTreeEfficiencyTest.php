<?php

declare(strict_types=1);

/**
 * The comment thread must cost a constant number of queries and render one page at a time.
 */

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\ReactionType;
use App\Models\User;
use App\Models\UserReaction;
use App\Services\ReactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function seedThread(int $comments, string $tag = 'a'): Post
{
    $u = User::factory()->create(['username' => 'autor_'.$tag]);
    $post = Post::create([
        'user_id' => $u->id, 'hashid' => 'eff'.$tag, 'title' => 'T', 'slug' => 't-'.$tag,
        'content' => 'c', 'status' => 'published', 'published_at' => now(),
    ]);

    $like = ReactionType::firstOrCreate(
        ['slug' => 'like'],
        ['name' => 'Curtir', 'emoji' => '👍', 'score' => 1, 'allowed_roles' => ['user'], 'display_order' => 1],
    );

    for ($i = 0; $i < $comments; $i++) {
        $c = Comment::create([
            'user_id' => $u->id, 'post_id' => $post->id, 'hashid' => $tag.'k'.$i,
            'content' => 'comentario '.$i, 'status' => 'visible', 'nesting_level' => 0,
        ]);
        UserReaction::create(['user_id' => $u->id, 'reaction_type_id' => $like->id, 'reactable_type' => 'comment', 'reactable_id' => $c->id]);
    }

    return $post;
}

/** A single chain of replies, `$depth` levels deep (depth 1 = a lone root comment). */
function seedNestedThread(int $depth, string $tag): Post
{
    $u = User::factory()->create(['username' => 'autor_'.$tag]);
    $post = Post::create([
        'user_id' => $u->id, 'hashid' => 'eff'.$tag, 'title' => 'T', 'slug' => 't-'.$tag,
        'content' => 'c', 'status' => 'published', 'published_at' => now(),
    ]);

    $like = ReactionType::firstOrCreate(
        ['slug' => 'like'],
        ['name' => 'Curtir', 'emoji' => '👍', 'score' => 1, 'allowed_roles' => ['user'], 'display_order' => 1],
    );

    $parentId = null;
    for ($i = 0; $i < $depth; $i++) {
        $c = Comment::create([
            'user_id' => $u->id, 'post_id' => $post->id, 'hashid' => $tag.'k'.$i, 'parent_id' => $parentId,
            'content' => 'comentario '.$i, 'status' => 'visible', 'nesting_level' => $i,
        ]);
        UserReaction::create(['user_id' => $u->id, 'reaction_type_id' => $like->id, 'reactable_type' => 'comment', 'reactable_id' => $c->id]);
        $parentId = $c->id;
    }

    return $post;
}

/**
 * Counts queries on a fresh request (reaction types are memoised as a singleton).
 */
function countQueries(callable $fn): int
{
    app()->forgetInstance(ReactionService::class);

    $n = 0;
    DB::listen(function () use (&$n) {
        $n++;
    });
    $fn();

    return $n;
}

it('query count does not grow with the number of comments', function () {
    config(['orbita.comments.per_page' => 50]);

    $small = seedThread(5, 'small');
    $big = seedThread(40, 'big');

    $qSmall = countQueries(fn () => Livewire::test('comment-tree', ['postId' => $small->id, 'allowComments' => true])->html());
    $qBig = countQueries(fn () => Livewire::test('comment-tree', ['postId' => $big->id, 'allowComments' => true])->html());

    expect($qBig)->toBe(
        $qSmall,
        "rendering 40 comments must cost the same as 5 (got {$qSmall} vs {$qBig}) — the reactions N+1 is back",
    );
});

it('query count does not grow with thread depth', function () {
    config(['orbita.comments.per_page' => 50, 'orbita.comments.max_nesting_level' => 5]);

    $shallow = seedNestedThread(1, 'shallow');
    $deep = seedNestedThread(5, 'deep');

    $qShallow = countQueries(fn () => Livewire::test('comment-tree', ['postId' => $shallow->id, 'allowComments' => true])->html());
    $qDeep = countQueries(fn () => Livewire::test('comment-tree', ['postId' => $deep->id, 'allowComments' => true])->html());

    expect($qDeep)->toBe(
        $qShallow,
        "a 5-level-deep thread must cost the same as a 1-level thread (got {$qShallow} vs {$qDeep}) — the per-depth-level N+1 is back",
    );
});

/**
 * loadMore renders ONE page; the browser accumulates revealed roots.
 */
it('load more renders one page at a time', function () {
    config(['orbita.comments.per_page' => 10]);

    $post = seedThread(25);

    $component = Livewire::test('comment-tree', ['postId' => $post->id, 'allowComments' => true]);

    $first = array_column($component->instance()->tree, 'id');
    expect($first)->toHaveCount(10);
    expect($component->instance()->roots->hasMorePages())->toBeTrue();

    $component->call('loadMore', 'comentarios');
    $second = array_column($component->instance()->tree, 'id');
    expect($second)->toHaveCount(10, 'the second page is rendered on its own, not re-rendered on top of the first');
    expect(array_intersect($first, $second))->toBe([]);
    expect($component->instance()->roots->hasMorePages())->toBeTrue();

    $component->call('loadMore', 'comentarios');
    $third = array_column($component->instance()->tree, 'id');
    expect($third)->toHaveCount(5, 'the last page holds the remainder');
    expect($component->instance()->roots->hasMorePages())->toBeFalse();

    expect(array_unique([...$first, ...$second, ...$third]))->toHaveCount(25, 'the three pages cover every root exactly once');
});

it('total still counts the whole thread', function () {
    config(['orbita.comments.per_page' => 5]);

    $post = seedThread(12);

    $component = Livewire::test('comment-tree', ['postId' => $post->id, 'allowComments' => true]);

    expect($component->instance()->tree)->toHaveCount(5, 'only a page is rendered');
    expect($component->instance()->total)->toBe(12, 'but the header counts everything');
});
