<?php

declare(strict_types=1);

/**
 * Antispam cooldown and duplicate-detection rules for posts and comments.
 */

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Services\CommentService;
use App\Services\PostService;
use App\Support\HashId;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function spamUser(string $username = 'spammer'): User
{
    return User::factory()->createOne([
        'username' => $username,
        'email_verified_at' => now(),
    ]);
}

/**
 * @param  array<string, mixed>  $attrs
 */
function spamPost(User $u, array $attrs = []): Post
{
    static $n = 0;
    $n++;

    return Post::create(array_merge([
        'user_id' => $u->id,
        'hashid' => HashId::encode($n),
        'title' => 'Titulo '.$n,
        'slug' => 'titulo-'.$n,
        'content' => 'Corpo '.$n,
        'status' => 'published',
        'published_at' => now(),
    ], $attrs));
}

function spamComment(User $u, Post $p, string $body): Comment
{
    static $n = 0;
    $n++;

    return Comment::create([
        'user_id' => $u->id,
        'post_id' => $p->id,
        'hashid' => HashId::encode(1000 + $n),
        'content' => $body,
        'status' => 'visible',
        'nesting_level' => 0,
    ]);
}

it('post cooldown blocks and reports remaining time', function () {
    config(['orbita.antispam.post_cooldown' => 300, 'orbita.antispam.duplicate_detection' => false]);

    $u = spamUser();
    spamPost($u);

    $result = app(PostService::class)->checkAntispam($u->id, 'Outro titulo', 'https://a.com', 'outro corpo');

    expect($result['allowed'])->toBeFalse();
    expect($result['reason'])->toContain('Aguarde')->toContain('minutos');
});

it('post cooldown allows after window', function () {
    config(['orbita.antispam.post_cooldown' => 300, 'orbita.antispam.duplicate_detection' => false]);

    $u = spamUser();
    spamPost($u)->forceFill(['created_at' => now()->subMinutes(10)])->save();

    expect(app(PostService::class)->checkAntispam($u->id, 'Novo', null, 'corpo')['allowed'])->toBeTrue();
});

it('post duplicate detected by url even with new title', function () {
    config(['orbita.antispam.post_cooldown' => 0]);

    $u = spamUser();
    spamPost($u, ['url' => 'https://exemplo.com/artigo']);

    // Different title + different body, same URL => still a repost.
    $result = app(PostService::class)->checkAntispam($u->id, 'Titulo completamente novo', 'https://exemplo.com/artigo', 'corpo novo');

    expect($result['allowed'])->toBeFalse();
    expect($result['reason'])->toContain('já publicou');
});

it('post duplicate detected by title', function () {
    config(['orbita.antispam.post_cooldown' => 0]);

    $u = spamUser();
    spamPost($u, ['title' => 'Notícia repetida']);

    $result = app(PostService::class)->checkAntispam($u->id, 'Notícia repetida', 'https://outro.com', 'corpo diferente');

    expect($result['allowed'])->toBeFalse();
});

it('post duplicate allows genuinely new content', function () {
    config(['orbita.antispam.post_cooldown' => 0]);

    $u = spamUser();
    spamPost($u, ['title' => 'Antigo', 'url' => 'https://a.com', 'content' => 'corpo a']);

    expect(app(PostService::class)->checkAntispam($u->id, 'Novo', 'https://b.com', 'corpo b')['allowed'])->toBeTrue();
});

it('post duplicate detection can be disabled', function () {
    config(['orbita.antispam.post_cooldown' => 0, 'orbita.antispam.duplicate_detection' => false]);

    $u = spamUser();
    spamPost($u, ['url' => 'https://exemplo.com/artigo']);

    expect(app(PostService::class)->checkAntispam($u->id, 'X', 'https://exemplo.com/artigo', 'y')['allowed'])->toBeTrue();
});

it('post per hour ceiling still enforced', function () {
    config([
        'orbita.antispam.post_cooldown' => 0,
        'orbita.antispam.duplicate_detection' => false,
        'orbita.antispam.max_posts_per_hour' => 2,
    ]);

    $u = spamUser();
    spamPost($u);
    spamPost($u);

    $result = app(PostService::class)->checkAntispam($u->id, 'Terceiro', null, 'corpo');

    expect($result['allowed'])->toBeFalse();
    expect($result['reason'])->toContain('limite de posts por hora');
});

it('comment cooldown blocks and reports seconds', function () {
    config(['orbita.antispam.comment_cooldown' => 30, 'orbita.antispam.duplicate_detection' => false]);

    $u = spamUser();
    $p = spamPost(spamUser('autor'));
    spamComment($u, $p, 'primeiro');

    $result = app(CommentService::class)->checkAntispam($u->id, $p->id, 'segundo');

    expect($result['allowed'])->toBeFalse();
    expect($result['reason'])->toContain('segundos');
});

it('comment duplicate on same post is blocked', function () {
    config(['orbita.antispam.comment_cooldown' => 0]);

    $u = spamUser();
    $p = spamPost(spamUser('autor'));
    spamComment($u, $p, 'mesmo texto');

    $result = app(CommentService::class)->checkAntispam($u->id, $p->id, 'mesmo texto');

    expect($result['allowed'])->toBeFalse();
    expect($result['reason'])->toContain('já publicou este comentário');
});

it('comment duplicate scoped to post allows same text elsewhere', function () {
    config(['orbita.antispam.comment_cooldown' => 0]);

    $u = spamUser();
    $author = spamUser('autor');
    $p1 = spamPost($author);
    $p2 = spamPost($author);
    spamComment($u, $p1, 'mesmo texto');

    expect(app(CommentService::class)->checkAntispam($u->id, $p2->id, 'mesmo texto')['allowed'])->toBeTrue();
});
