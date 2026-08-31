<?php

declare(strict_types=1);

/**
 * Regressions found by the code review; each fails against the pre-fix code.
 */

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\ReactionType;
use App\Models\User;
use App\Services\CommentService;
use App\Services\PostService;
use App\Services\ReactionService;
use App\Support\Markdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function regressionUser(string $username, string $role = 'user'): User
{
    return User::factory()->createOne(['username' => $username, 'role' => $role, 'email_verified_at' => now()]);
}

/**
 * @param  array<string, mixed>  $attrs
 */
function regressionPost(User $author, array $attrs = []): Post
{
    static $n = 0;
    $n++;

    return Post::create(array_merge([
        'user_id' => $author->id, 'hashid' => 'p'.$n, 'title' => 'T'.$n, 'slug' => 't'.$n,
        'content' => 'corpo', 'status' => 'published', 'published_at' => now(),
    ], $attrs));
}

/**
 * @param  array<string, mixed>  $attrs
 */
function regressionComment(User $u, Post $p, array $attrs = []): Comment
{
    static $n = 0;
    $n++;

    return Comment::create(array_merge([
        'user_id' => $u->id, 'post_id' => $p->id, 'hashid' => 'c'.$n,
        'content' => 'texto', 'status' => 'visible', 'nesting_level' => 0,
    ], $attrs));
}

// ── reactions must drive posts.score (the "Populares" feed orders by it) ──────

it('reacting updates both score and reaction count', function () {
    $author = regressionUser('autor');
    $reader = regressionUser('leitor');
    $post = regressionPost($author);

    $insightful = ReactionType::create(['slug' => 'insightful', 'name' => 'Esclarecedor', 'emoji' => '💡', 'score' => 3, 'allowed_roles' => ['user'], 'display_order' => 1]);

    app(ReactionService::class)->toggle($reader->id, 'post', $post->id, $insightful, $post);

    $post->refresh();
    expect((int) $post->score)->toBe(3, 'posts.score must reflect the reaction weight')
        ->and((int) $post->reaction_count)->toBe(1);
});

it('removing a reaction rolls the score back', function () {
    $author = regressionUser('autor2');
    $reader = regressionUser('leitor2');
    $post = regressionPost($author);
    $like = ReactionType::create(['slug' => 'like', 'name' => 'Curtir', 'emoji' => '👍', 'score' => 1, 'allowed_roles' => ['user'], 'display_order' => 1]);

    $svc = app(ReactionService::class);
    $svc->toggle($reader->id, 'post', $post->id, $like, $post);
    $svc->remove($reader->id, 'post', $post->id);

    $post->refresh();
    expect((int) $post->score)->toBe(0)
        ->and((int) $post->reaction_count)->toBe(0);
});

// ── posts.comment_count must move with the comments ──────────────────────────

it('comment count is updated on create and delete', function () {
    $author = regressionUser('a3');
    $post = regressionPost($author);

    $c = regressionComment(regressionUser('b3'), $post);
    expect((int) $post->fresh()?->comment_count)->toBe(1);

    $c->delete();
    expect((int) $post->fresh()?->comment_count)->toBe(0);
});

it('hiding a comment from the admin updates the count', function () {
    $post = regressionPost(regressionUser('a4'));
    $c = regressionComment(regressionUser('b4'), $post);

    $c->update(['status' => 'hidden']);

    expect((int) $post->fresh()?->comment_count)->toBe(0);
});

// ── edit window: 0 means unlimited, staff bypass ─────────────────────────────

it('zero edit time limit means unlimited', function () {
    config(['orbita.comments.edit_time_limit' => 0]);

    $u = regressionUser('editor');
    $post = regressionPost(regressionUser('a5'), ['allow_comments' => true]);
    $c = regressionComment($u, $post);
    $c->forceFill(['created_at' => now()->subYear()])->save();

    actingAs($u);
    $updated = app(CommentService::class)->updateComment($c, ['content' => 'novo texto']);

    expect($updated->content)->toBe('novo texto');
});

it('staff can edit a comment past the window', function () {
    config(['orbita.comments.edit_time_limit' => 60]);

    $admin = regressionUser('chefe', 'admin');
    $post = regressionPost(regressionUser('a6'));
    $c = regressionComment(regressionUser('b6'), $post);
    $c->forceFill(['created_at' => now()->subDay()])->save();

    actingAs($admin);
    $updated = app(CommentService::class)->updateComment($c, ['content' => 'moderado']);

    expect($updated->content)->toBe('moderado');
});

it('author cannot edit after the window', function () {
    config(['orbita.comments.edit_time_limit' => 60]);

    $u = regressionUser('lento');
    $post = regressionPost(regressionUser('a7'));
    $c = regressionComment($u, $post);
    $c->forceFill(['created_at' => now()->subDay()])->save();

    actingAs($u);
    app(CommentService::class)->updateComment($c, ['content' => 'tarde demais']);
})->throws(RuntimeException::class);

// ── server-side allow_comments gate ──────────────────────────────────────────

it('comments cannot be created when the post has them closed', function () {
    $post = regressionPost(regressionUser('a8'), ['allow_comments' => false]);
    $u = regressionUser('b8');

    app(CommentService::class)->createComment([
        'post_id' => $post->id, 'user_id' => $u->id, 'content' => 'passei pelo Blade',
    ]);
})->throws(RuntimeException::class, 'Os comentários estão fechados para este post.');

// ── markdown content rules (Markdown::validate had no caller) ─────────────────

it('external markdown images are rejected in comments', function () {
    $post = regressionPost(regressionUser('a9'));
    $u = regressionUser('b9');

    app(CommentService::class)->createComment([
        'post_id' => $post->id, 'user_id' => $u->id,
        'content' => 'olha ![x](https://tracker.example/pixel.png)',
    ]);
})->throws(RuntimeException::class, 'Imagens externas não são permitidas. Envie a imagem pelo editor.');

it('own endpoint markdown images are allowed', function () {
    // Inline images that point at our /s/{path} image endpoint pass the content gate.
    expect(Markdown::validate('veja ![foto](/s/posts/2026/01/foto.jpg)'))->toBe([]);
});

it('raw html is rejected in posts', function () {
    $u = regressionUser('a10');

    app(PostService::class)->createPost([
        'user_id' => $u->id, 'title' => 'T', 'content' => '<script>alert(1)</script>',
    ]);
})->throws(RuntimeException::class, 'Tags HTML não são permitidas.');

it('plain markdown is still accepted', function () {
    $u = regressionUser('a11');

    $post = app(PostService::class)->createPost([
        'user_id' => $u->id, 'title' => 'Ok', 'content' => 'texto com **negrito** e [link](https://x.com)',
    ]);

    expect($post->id)->not->toBeNull();
});

// ── comment tree: orphaned replies must not disappear ────────────────────────

it('replies survive when their parent is hidden', function () {
    $post = regressionPost(regressionUser('a12'), ['allow_comments' => true]);
    $u = regressionUser('b12');

    $parent = regressionComment($u, $post);
    regressionComment($u, $post, ['parent_id' => $parent->id, 'nesting_level' => 1, 'hashid' => 'reply1']);

    $parent->update(['status' => 'hidden']);

    $rendered = Livewire::test('comment-tree', ['postId' => $post->id, 'allowComments' => true]);

    // The orphaned reply is re-rooted, so the visible total equals what is rendered.
    $rendered->assertSee('texto');

    expect((int) $post->fresh()?->comment_count)->toBe(1, 'only the reply remains visible');
});
