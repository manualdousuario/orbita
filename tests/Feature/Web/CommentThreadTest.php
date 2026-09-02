<?php

declare(strict_types=1);

/**
 * Behaviour of the threaded comment view (⚡comment-tree + comment-node).
 */

namespace Tests\Feature\Web;

use App\Models\Comment;
use App\Models\Post;
use App\Models\ReactionType;
use App\Models\User;
use App\Models\UserReaction;
use Database\Seeders\ReactionTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

/**
 * Monotonic across the file: it drives both the hashid and the created_at
 * ordering the tree reads, so it must never repeat.
 */
function threadSeq(): int
{
    static $n = 0;

    return ++$n;
}

function threadPost(): Post
{
    $author = User::factory()->createOne(['username' => 'autor_thread', 'email_verified_at' => now()]);

    return Post::create([
        'user_id' => $author->id, 'hashid' => 'thr1', 'title' => 'Assunto', 'slug' => 'assunto',
        'content' => 'corpo', 'status' => 'published', 'allow_comments' => true, 'published_at' => now(),
    ]);
}

function threadComment(Post $post, string $text, ?Comment $parent = null): Comment
{
    $seq = threadSeq();

    return Comment::create([
        'user_id' => $post->user_id,
        'post_id' => $post->id,
        'parent_id' => $parent?->id,
        'hashid' => 'ch'.$seq,
        'content' => $text,
        'status' => 'visible',
        'nesting_level' => $parent ? $parent->nesting_level + 1 : 0,
        'created_at' => now()->addSeconds($seq),
    ]);
}

/** Gives the comment $n reactions of one slug and syncs the aggregates the tree reads. */
function reactToComment(Comment $comment, string $slug, int $n): void
{
    $type = ReactionType::where('slug', $slug)->firstOrFail();

    for ($i = 0; $i < $n; $i++) {
        UserReaction::create([
            'user_id' => User::factory()->createOne(['username' => 'v'.$slug.$comment->id.$i])->id,
            'reaction_type_id' => $type->id,
            'reactable_type' => 'comment',
            'reactable_id' => $comment->id,
        ]);
    }

    $comment->forceFill([
        'reaction_count' => $n,
        'score' => $n * (int) $type->score,
    ])->save();
}

/** Each sort control renders one class attribute and each ordering is its own URL. */
it('the sort control renders one class attribute per link', function () {
    $post = threadPost();
    threadComment($post, 'oi');

    $html = (string) get(route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]))
        ->assertOk()->getContent();

    preg_match_all('/<a [^>]*rel="nofollow"[^>]*>/', $html, $m);

    expect($m[0])->toHaveCount(3, 'three sort links');

    foreach ($m[0] as $link) {
        expect(substr_count($link, ' class="'))
            ->toBe(1, "a second class attribute is dropped by the browser: {$link}");
        expect($link)->toContain('py-3');  // the control keeps its sizing
    }

    expect($html)->toContain('sort=oldest')   // each ordering is addressable
        ->toContain('sort=most_reactions');
});

/** A logged-in user's saved preference becomes the tree's initial sort. */
it('a users saved sort preference is used as the default', function () {
    $post = threadPost();
    threadComment($post, 'oi');
    $viewer = User::factory()->createOne(['default_comment_sort' => 'oldest', 'email_verified_at' => now()]);

    $sort = Livewire::actingAs($viewer)
        ->test('comment-tree', ['postId' => $post->id, 'allowComments' => true])
        ->instance()->sort;

    expect($sort)->toBe('oldest');
});

/** No saved preference (null) falls back to config('orbita.comments.default_sort'). */
it('no saved preference falls back to the site default', function () {
    $post = threadPost();
    threadComment($post, 'oi');
    $viewer = User::factory()->createOne(['default_comment_sort' => null, 'email_verified_at' => now()]);

    $sort = Livewire::actingAs($viewer)
        ->test('comment-tree', ['postId' => $post->id, 'allowComments' => true])
        ->instance()->sort;

    expect($sort)->toBe(config('orbita.comments.default_sort', 'newest'));
});

/** A ?sort= in the URL overrides the saved preference. */
it('the url sort beats the saved preference', function () {
    $post = threadPost();
    threadComment($post, 'oi');
    $viewer = User::factory()->createOne(['default_comment_sort' => 'oldest', 'email_verified_at' => now()]);

    $sort = Livewire::actingAs($viewer)
        ->withQueryParams(['sort' => 'most_reactions'])
        ->test('comment-tree', ['postId' => $post->id, 'allowComments' => true])
        ->instance()->sort;

    expect($sort)->toBe('most_reactions');
});

/** A junk ?sort= falls back rather than producing an unordered thread. */
it('an unknown url sort falls back to the preference', function () {
    $post = threadPost();
    threadComment($post, 'oi');
    $viewer = User::factory()->createOne(['default_comment_sort' => 'oldest', 'email_verified_at' => now()]);

    $sort = Livewire::actingAs($viewer)
        ->withQueryParams(['sort' => 'drop table'])
        ->test('comment-tree', ['postId' => $post->id, 'allowComments' => true])
        ->instance()->sort;

    expect($sort)->toBe('oldest');
});

/** "Populares" ranks by total reaction score, not by reaction count. */
it('populares ranks by reaction score not by reaction count', function () {
    seed(ReactionTypeSeeder::class);
    $post = threadPost();

    $liked = threadComment($post, 'comentario bem avaliado');
    $disliked = threadComment($post, 'comentario mal avaliado');

    reactToComment($liked, 'like', 4);        // score +4
    reactToComment($disliked, 'dislike', 5);  // score -5, but MORE reactions

    $tree = Livewire::withQueryParams(['sort' => 'most_reactions'])
        ->test('comment-tree', ['postId' => $post->id, 'allowComments' => true])
        ->instance()->tree;

    expect($tree[0]['id'])->toBe((int) $liked->id, 'the +4 comment must outrank the -5 one')
        ->and($tree[1]['id'])->toBe((int) $disliked->id);
});

/** The reaction chip is the control and no second score is printed beside it. */
it('the reaction chip is the control and no second score is printed', function () {
    seed(ReactionTypeSeeder::class);
    $post = threadPost();
    $comment = threadComment($post, 'comentario com reacoes');
    reactToComment($comment, 'like', 3);

    $reader = User::factory()->createOne(['username' => 'leitor_chip', 'email_verified_at' => now()]);

    $html = (string) actingAs($reader)
        ->get(route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]))
        ->assertOk()->getContent();

    expect($html)->toContain('wire:click="react(\'like\')"')  // clicking the 👍3 chip must react
        ->toContain('aria-label="Curtir (3)"');               // the chip names itself and its count
    // The signed score must not be printed beside the chip.
    expect($html)->not->toContain('+3');
});

/** The comment body must not depend on Alpine: x-cloak on it would hide it without JS. */
it('the comment body is visible without javascript', function () {
    $post = threadPost();
    threadComment($post, 'corpo que precisa aparecer sem js');

    $html = (string) get(route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]))
        ->assertOk()->getContent();

    // The x-show="!collapsed" wrapper must carry no x-cloak or it hides without JS.
    preg_match('/<div x-show="!collapsed"[^>]*>/', $html, $m);

    expect($m)->not->toBeEmpty('the comment body wrapper is there');
    expect($m[0])->not->toContain('x-cloak');
    expect($html)->toContain('corpo que precisa aparecer sem js');
});

/** The load-more control disappears once the last page of roots is reached. */
it('load more is offered until the last page of roots', function () {
    config(['orbita.comments.per_page' => 5]);
    $post = threadPost();

    foreach (range(1, 8) as $i) {
        threadComment($post, 'comentario '.$i);
    }

    $component = Livewire::test('comment-tree', ['postId' => $post->id, 'allowComments' => true]);
    $component->assertSee('Carregar mais');

    $component->call('loadMore', 'comentarios');

    expect($component->instance()->roots->hasMorePages())
        ->toBeFalse('page 2 holds the last 3 of the 8 roots');
});

/** Each comment page is its own URL and serves only that page's roots. */
it('each comment page is addressable and serves only its own roots', function () {
    config(['orbita.comments.per_page' => 5]);
    $post = threadPost();

    foreach (range(1, 8) as $i) {
        threadComment($post, 'comentario '.$i);
    }

    $ids = fn (array $tree) => array_column($tree, 'id');

    $first = $ids(Livewire::test('comment-tree', ['postId' => $post->id, 'allowComments' => true])->instance()->tree);
    $second = $ids(Livewire::withQueryParams(['comentarios' => 2])
        ->test('comment-tree', ['postId' => $post->id, 'allowComments' => true])->instance()->tree);

    expect($first)->toHaveCount(5)
        ->and($second)->toHaveCount(3)
        ->and(array_intersect($first, $second))->toBe([], 'no root appears on two pages')
        ->and(array_unique([...$first, ...$second]))->toHaveCount(8, 'the two pages cover every root');
});

/** A new root comment arrives as a prepended island fragment. */
it('a new root comment arrives as a prepended island fragment', function () {
    config(['orbita.comments.per_page' => 5]);
    $post = threadPost();

    foreach (range(1, 8) as $i) {
        threadComment($post, 'comentario '.$i);
    }

    $reader = User::factory()->createOne(['username' => 'leitor_thread', 'email_verified_at' => now()]);
    actingAs($reader);

    $fresh = threadComment($post, 'comentario recem postado');

    $tree = Livewire::test('comment-tree', ['postId' => $post->id, 'allowComments' => true])
        ->dispatch('comment-created', postId: $post->id, parentId: null);

    $fragments = $tree->effects['islandFragments'] ?? [];

    expect($fragments)->not->toBeEmpty('the new comment comes back as island fragments');
    expect(implode('', $fragments))->toContain('mode=prepend')
        ->toContain('comentario recem postado')
        ->toContain('comment-'.$fresh->hashid);
});

it('a reply arrives as a morphed island fragment', function () {
    config(['orbita.comments.per_page' => 5]);
    $post = threadPost();

    $root = threadComment($post, 'comentario raiz');

    foreach (range(1, 3) as $i) {
        threadComment($post, 'comentario '.$i);
    }

    $reply = threadComment($post, 'resposta que precisa aparecer', $root);

    $tree = Livewire::test('comment-tree', ['postId' => $post->id, 'allowComments' => true])
        ->dispatch('comment-created', postId: $post->id, parentId: $root->id, commentId: $reply->id);

    $html = implode('', $tree->effects['islandFragments'] ?? []);

    expect($html)->toContain('name=comment-roots')
        ->toContain('mode=morph')
        ->toContain('resposta que precisa aparecer')
        ->toContain('comment-'.$reply->hashid)
        ->toContain('comment-'.$root->hashid);
});

/** The morph rebuilds every page the reader already pulled in, or it would erase them. */
it('a reply keeps the roots from earlier infinite-scroll pages', function () {
    config(['orbita.comments.per_page' => 5]);
    $post = threadPost();

    $first = threadComment($post, 'raiz da primeira pagina');

    foreach (range(1, 7) as $i) {
        threadComment($post, 'comentario '.$i);
    }

    $reply = threadComment($post, 'resposta na pagina dois', $first);

    $tree = Livewire::test('comment-tree', ['postId' => $post->id, 'allowComments' => true]);
    $tree->call('loadMore', 'comentarios');

    $tree->dispatch('comment-created', postId: $post->id, parentId: $first->id, commentId: $reply->id);

    $html = implode('', $tree->effects['islandFragments'] ?? []);

    expect($html)->toContain('resposta na pagina dois')
        ->toContain('comment-'.$first->hashid)
        ->toContain('data-page-marker="1"')
        ->toContain('data-page-marker="2"');
});

/** Prepending only makes sense when the sort actually puts the new root first. */
it('a new root under a non-default sort is morphed instead of prepended', function () {
    config(['orbita.comments.per_page' => 5]);
    $post = threadPost();

    foreach (range(1, 3) as $i) {
        threadComment($post, 'comentario '.$i);
    }

    $fresh = threadComment($post, 'raiz mais recente');

    $tree = Livewire::withQueryParams(['sort' => 'oldest'])
        ->test('comment-tree', ['postId' => $post->id, 'allowComments' => true])
        ->dispatch('comment-created', postId: $post->id, parentId: null, commentId: $fresh->id);

    $html = implode('', $tree->effects['islandFragments'] ?? []);

    expect($html)->toContain('name=comment-roots')
        ->toContain('mode=morph');

    expect($html)->not->toContain('mode=prepend');
});

/** An event for another post must not cost this component a render. */
it('ignores comment-created from another post', function () {
    $post = threadPost();
    threadComment($post, 'comentario 1');

    $tree = Livewire::test('comment-tree', ['postId' => $post->id, 'allowComments' => true])
        ->dispatch('comment-created', postId: $post->id + 999, parentId: null);

    expect($tree->effects['islandFragments'] ?? [])->toBeEmpty();
});

/** The reply form breaks out of the thread indent on phones. */
it('the reply form breaks out of the thread indent on phones', function () {
    $post = threadPost();
    $parent = null;
    foreach (range(0, 5) as $level) {
        $parent = threadComment($post, 'nivel '.$level, $parent);
    }

    $reader = User::factory()->createOne(['username' => 'leitor_recuo', 'email_verified_at' => now()]);

    $html = (string) actingAs($reader)
        ->get(route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]))
        ->assertOk()->getContent();

    // Expected --reply-pad/--reply-gaps: 22/44/45/46/47px + gap-x-2 gaps at a 16px root.
    $expected = ['22px' => 1, '44px' => 2, '45px' => 3, '46px' => 4, '47px' => 5];
    foreach ($expected as $pad => $gaps) {
        expect(substr_count($html, '--reply-pad: '.$pad.'; --reply-gaps: '.$gaps))
            ->toBe(1, "level {$gaps} carries its own accumulated indent");
    }

    // A root emits no breakout (guards the range(1, 0) === [1, 0] trap).
    expect(substr_count($html, '--reply-pad'))->toBe(5, 'only the five nested levels are offset');
    expect(substr_count($html, 'x-data="{ replying: false, collapsed: false }"'))->toBe(6);

    // Match the full string: 'pl-[21px]' alone is a substring of 'pl-0 sm:pl-[21px]'.
    expect(substr_count($html, 'gap-x-2 sm:gap-x-5 pl-[21px]'))->toBe(2, 'levels 1-2 keep the inset');
    expect(substr_count($html, 'gap-x-2 sm:gap-x-5 pl-0 sm:pl-[21px]'))->toBe(3, 'levels 3-5 drop it on mobile');

    // One reply form per node that can still be replied to (level 5 is at max nesting).
    expect(substr_count($html, 'class="reply-breakout"'))->toBe(5);

    // Each "Responder" button focuses its own reply textarea once revealed.
    expect(substr_count($html, 'x-ref="replyForm"'))->toBe(5);
    expect(substr_count($html, "replying && \$nextTick(() => \$refs.replyForm.querySelector('textarea')?.focus())"))->toBe(5);
});

it('reply editors include the shared markdown and mention behaviors', function () {
    $post = threadPost();
    $parent = threadComment($post, 'comentario com resposta');
    threadComment($post, 'resposta existente', $parent);

    $reader = User::factory()->createOne(['username' => 'leitor_editor', 'email_verified_at' => now()]);

    $html = (string) actingAs($reader)
        ->get(route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]))
        ->assertOk()->getContent();

    expect($html)->toContain('placeholder="Escreva uma resposta..."')
        ->toContain('@input="onInput()"')
        ->toContain('@drop="onDrop($event)"')
        ->toContain('x-data="markdownEditor');
});

/** An orphan promoted to a root gets no breakout offset. */
it('an orphan promoted to a root gets no breakout offset', function () {
    $post = threadPost();
    $parent = threadComment($post, 'pai que sera removido');
    $orphan = threadComment($post, 'filho orfao', $parent);

    expect((int) $orphan->nesting_level)->toBe(1);
    $parent->update(['status' => 'revision']);

    $reader = User::factory()->createOne(['username' => 'leitor_orfao', 'email_verified_at' => now()]);

    $html = (string) actingAs($reader)
        ->get(route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]))
        ->assertOk()->getContent();

    expect($html)->toContain('id="comment-'.$orphan->hashid.'"');
    // A promoted root is not indented.
    expect($html)->not->toContain('--reply-pad');
});
