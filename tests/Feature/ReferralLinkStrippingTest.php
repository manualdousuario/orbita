<?php

declare(strict_types=1);

/**
 * Referral-link stripping across posts, comments, and profiles.
 */

namespace Tests\Feature;

use App\Models\Moderation;
use App\Models\Post;
use App\Models\User;
use App\Services\CommentService;
use App\Services\PostService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'orbita.moderation.strip_referral_params' => true,
        'orbita.moderation.hide_referral_for_review' => false,
        'orbita.moderation.forbidden_url_params' => "ref\nreferral\nreferral-code",
        // Nothing here is about rate limiting.
        'orbita.antispam.post_cooldown' => 0,
        'orbita.antispam.comment_cooldown' => 0,
    ]);
});

function referralUser(string $username): User
{
    return User::factory()->createOne(['username' => $username, 'email_verified_at' => now()]);
}

function referralLogFor(string $type, int $id): ?Moderation
{
    return Moderation::query()
        ->where('action', 'referral_stripped')
        ->where('target_type', $type)
        ->where('target_id', $id)
        ->first();
}

// ── posts ────────────────────────────────────────────────────────────────

it('post url and body are saved clean and audited', function () {
    $post = app(PostService::class)->createPost([
        'user_id' => referralUser('autor_ref')->id,
        'title' => 'Oferta',
        'url' => 'https://loja.com/p?referral=abc&cor=azul',
        'content' => 'Também em [outra loja](https://outra.com/x?ref=zzz).',
        'status' => 'published',
    ]);

    expect($post->url)->toBe('https://loja.com/p?cor=azul');
    expect($post->content)->toContain('(https://outra.com/x)');

    $log = referralLogFor('post', (int) $post->id);
    expect($log)->not->toBeNull();
    expect($log?->metadata['params'])->toEqualCanonicalizing(['referral', 'ref']);
    expect($log?->metadata['hidden'])->toBeFalse();
    expect($post->fresh()?->status)->toBe('published');
});

it('editing only the title does not rewrite a legacy body', function () {
    $author = referralUser('autor_legado');

    // A row written before the rule existed, straight through Eloquent.
    $post = Post::create([
        'user_id' => $author->id,
        'hashid' => 'lg1',
        'title' => 'Antigo',
        'slug' => 'antigo',
        'content' => 'Veja https://loja.com/p?referral=abc',
        'url' => 'https://loja.com/p?referral=abc',
        'status' => 'published',
        'published_at' => now(),
    ]);

    app(PostService::class)->updatePost($post, [
        'title' => 'Antigo (corrigido)',
        'content' => $post->content,
        'url' => $post->url,
    ]);

    $post->refresh();
    expect($post->content)->toContain('?referral=abc');
    expect($post->url)->toBe('https://loja.com/p?referral=abc');
    expect(referralLogFor('post', (int) $post->id))->toBeNull();
});

// ── comments ─────────────────────────────────────────────────────────────

it('comment body is saved clean and audited', function () {
    $author = referralUser('autor_post');
    $post = app(PostService::class)->createPost([
        'user_id' => $author->id, 'title' => 'Assunto', 'content' => 'corpo', 'status' => 'published',
    ]);

    $comment = app(CommentService::class)->createComment([
        'post_id' => $post->id,
        'user_id' => referralUser('comentarista')->id,
        'content' => 'Comprei aqui: https://loja.com/p?referral=abc',
    ]);

    expect($comment->content)->toBe('Comprei aqui: https://loja.com/p');

    $log = referralLogFor('comment', (int) $comment->id);
    expect($log)->not->toBeNull();
    expect($log?->metadata['params'])->toBe(['referral']);
});

it('comment is hidden for review when configured', function () {
    config(['orbita.moderation.hide_referral_for_review' => true]);

    $author = referralUser('autor_post2');
    $post = app(PostService::class)->createPost([
        'user_id' => $author->id, 'title' => 'Assunto', 'content' => 'corpo', 'status' => 'published',
    ]);

    $comment = app(CommentService::class)->createComment([
        'post_id' => $post->id,
        'user_id' => referralUser('comentarista2')->id,
        'content' => 'Use meu link https://loja.com/p?ref=abc',
    ]);

    expect($comment->fresh()?->status)->toBe('hidden')
        ->and(referralLogFor('comment', (int) $comment->id)?->metadata['hidden'])->toBeTrue();
});

it('a clean comment is never flagged', function () {
    config(['orbita.moderation.hide_referral_for_review' => true]);

    $author = referralUser('autor_post3');
    $post = app(PostService::class)->createPost([
        'user_id' => $author->id, 'title' => 'Assunto', 'content' => 'corpo', 'status' => 'published',
    ]);

    $comment = app(CommentService::class)->createComment([
        'post_id' => $post->id,
        'user_id' => referralUser('comentarista3')->id,
        'content' => 'Sem link de referência: https://loja.com/p?cor=azul',
    ]);

    expect($comment->fresh()?->status)->toBe('visible')
        ->and(Moderation::where('action', 'referral_stripped')->count())->toBe(0);
});

// ── duplicate detection ──────────────────────────────────────────────────

it('the same link under a new referral code is still a duplicate', function () {
    config(['orbita.antispam.duplicate_detection' => true]);

    $author = referralUser('reposter');

    app(PostService::class)->createPost([
        'user_id' => $author->id,
        'title' => 'Oferta',
        'url' => 'https://loja.com/p?referral=aaa',
        'content' => '',
        'status' => 'published',
    ]);

    $check = app(PostService::class)->checkAntispam(
        (int) $author->id,
        'Outro título',
        'https://loja.com/p?referral=bbb',
        '',
    );

    expect($check['allowed'])->toBeFalse();
});

// ── profile ──────────────────────────────────────────────────────────────

it('profile website and bio are saved clean', function () {
    $user = referralUser('perfilado');

    actingAs($user)
        ->put("/u/{$user->username}", [
            'username' => $user->username,
            'display_name' => 'Perfilado',
            'bio' => 'Meu blog: https://blog.com/x?ref=abc',
            'website' => 'https://site.com/?referral=abc',
        ])
        ->assertRedirect();

    $user->refresh();
    expect($user->bio)->toBe('Meu blog: https://blog.com/x')
        ->and($user->website)->toBe('https://site.com/');
    expect(referralLogFor('user', (int) $user->id))->not->toBeNull();
});

// ── the feature can be switched off ──────────────────────────────────────

it('nothing is touched when the rule is disabled', function () {
    config(['orbita.moderation.strip_referral_params' => false]);

    $post = app(PostService::class)->createPost([
        'user_id' => referralUser('autor_off')->id,
        'title' => 'Oferta',
        'url' => 'https://loja.com/p?referral=abc',
        'content' => 'https://loja.com/q?ref=xyz',
        'status' => 'published',
    ]);

    expect($post->url)->toBe('https://loja.com/p?referral=abc')
        ->and($post->content)->toBe('https://loja.com/q?ref=xyz');
    expect(referralLogFor('post', (int) $post->id))->toBeNull();
});
