<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\CommentCreated;
use App\Events\UsersMentioned;
use App\Models\Comment;
use App\Models\Moderation;
use App\Models\Post;
use App\Models\User;
use App\Services\CommentService;
use App\Services\PostService;
use App\Support\HashId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'orbita.moderation.link_guard_enabled' => true,
        'orbita.moderation.link_domain_blocklist' => 'spam.com',
        'orbita.moderation.link_domain_allowlist' => 'catracalivre.com.br',
        'orbita.moderation.link_trust_account_age_hours' => 72,
        'orbita.moderation.link_trust_min_comments' => 3,
        'orbita.moderation.hide_referral_for_review' => false,
        'orbita.antispam.comment_cooldown' => 0,
        'orbita.antispam.post_cooldown' => 0,
        'orbita.comments.edit_time_limit' => 0,
        'app.url' => 'https://orbita.test',
    ]);
});

function guardNewcomer(): User
{
    return User::factory()->createOne([
        'email_verified_at' => now(),
        'created_at' => now(),
    ]);
}

function guardRegular(): User
{
    $user = User::factory()->createOne([
        'email_verified_at' => now(),
        'created_at' => now()->subMonths(6),
    ]);

    $post = guardPost($user);

    for ($i = 0; $i < 3; $i++) {
        guardComment($user, $post, 'historico '.$i);
    }

    return $user;
}

/**
 * @param  array<string, mixed>  $attrs
 */
function guardPost(User $user, array $attrs = []): Post
{
    static $n = 0;
    $n++;

    return Post::create(array_merge([
        'user_id' => $user->id,
        'hashid' => HashId::encode(5000 + $n),
        'title' => 'Titulo '.$n,
        'slug' => 'titulo-'.$n,
        'content' => 'Corpo '.$n,
        'status' => 'published',
        'published_at' => now(),
    ], $attrs));
}

function guardComment(User $user, Post $post, string $body): Comment
{
    static $n = 0;
    $n++;

    return Comment::create([
        'user_id' => $user->id,
        'post_id' => $post->id,
        'hashid' => HashId::encode(6000 + $n),
        'content' => $body,
        'status' => 'visible',
        'nesting_level' => 0,
    ]);
}

function guardFlags(string $type, int $id): int
{
    return Moderation::query()
        ->where('action', 'link_flagged')
        ->where('target_type', $type)
        ->where('target_id', $id)
        ->count();
}

it('hides a newcomer comment that ends in an external link', function () {
    $user = guardNewcomer();
    $post = guardPost(guardRegular());

    $comment = app(CommentService::class)->createComment([
        'post_id' => $post->id,
        'user_id' => $user->id,
        'content' => "Texto perfeitamente razoavel sobre o assunto.\n\nhttps://qualquer-coisa.com/promo",
    ]);

    expect($comment->fresh()->status)->toBe('hidden')
        ->and(guardFlags('comment', (int) $comment->id))->toBe(1);

    $log = Moderation::query()->where('action', 'link_flagged')->latest('id')->firstOrFail();

    expect($log->metadata['reason'])->toBe('untrusted_author')
        ->and($log->metadata['hosts'])->toBe(['qualquer-coisa.com'])
        ->and($log->metadata['hidden'])->toBeTrue()
        ->and($log->moderator_id)->toBeNull();
});

it('leaves a comment from an established account visible', function () {
    $user = guardRegular();
    $post = guardPost($user);

    $comment = app(CommentService::class)->createComment([
        'post_id' => $post->id,
        'user_id' => $user->id,
        'content' => 'Olha isso https://qualquer-coisa.com/promo',
    ]);

    expect($comment->fresh()->status)->toBe('visible')
        ->and(guardFlags('comment', (int) $comment->id))->toBe(0);
});

it('leaves an allowlisted link from a newcomer visible', function () {
    $user = guardNewcomer();
    $post = guardPost(guardRegular());

    $comment = app(CommentService::class)->createComment([
        'post_id' => $post->id,
        'user_id' => $user->id,
        'content' => 'A materia esta em https://catracalivre.com.br/materia',
    ]);

    expect($comment->fresh()->status)->toBe('visible')
        ->and(guardFlags('comment', (int) $comment->id))->toBe(0);
});

it('leaves a newcomer comment without links visible', function () {
    $user = guardNewcomer();
    $post = guardPost(guardRegular());

    $comment = app(CommentService::class)->createComment([
        'post_id' => $post->id,
        'user_id' => $user->id,
        'content' => 'So um comentario comum, sem nenhum link.',
    ]);

    expect($comment->fresh()->status)->toBe('visible')
        ->and(guardFlags('comment', (int) $comment->id))->toBe(0);
});

it('hides a blocklisted domain even from an established account', function () {
    $user = guardRegular();
    $post = guardPost($user);

    $comment = app(CommentService::class)->createComment([
        'post_id' => $post->id,
        'user_id' => $user->id,
        'content' => 'Confia em mim: https://go.spam.com/oferta',
    ]);

    expect($comment->fresh()->status)->toBe('hidden');

    $log = Moderation::query()->where('action', 'link_flagged')->latest('id')->firstOrFail();

    expect($log->metadata['reason'])->toBe('blocked_domain')
        ->and($log->metadata['hosts'])->toBe(['go.spam.com']);
});

it('does not notify anyone about a comment it hid', function () {
    Event::fake([CommentCreated::class, UsersMentioned::class]);

    $author = guardRegular();
    $post = guardPost($author);
    $user = guardNewcomer();

    app(CommentService::class)->createComment([
        'post_id' => $post->id,
        'user_id' => $user->id,
        'content' => 'Oi @'.$author->username.', veja https://qualquer-coisa.com/promo',
    ]);

    Event::assertNotDispatched(CommentCreated::class);
    Event::assertNotDispatched(UsersMentioned::class);
});

it('hides a comment edited to add an external link', function () {
    $user = guardNewcomer();
    $post = guardPost(guardRegular());

    $comment = app(CommentService::class)->createComment([
        'post_id' => $post->id,
        'user_id' => $user->id,
        'content' => 'Texto limpo, sem link nenhum.',
    ]);

    expect($comment->fresh()->status)->toBe('visible');

    actingAs($user);

    app(CommentService::class)->updateComment($comment, [
        'content' => 'Texto limpo, sem link nenhum. https://qualquer-coisa.com/promo',
    ]);

    expect($comment->fresh()->status)->toBe('hidden')
        ->and(guardFlags('comment', (int) $comment->id))->toBe(1);
});

it('leaves a newcomer link post published', function () {
    $user = guardNewcomer();

    $post = app(PostService::class)->createPost([
        'user_id' => $user->id,
        'title' => 'Uma noticia interessante',
        'url' => 'https://veiculo-de-noticias.com/materia',
        'content' => '',
        'status' => 'published',
    ]);

    expect($post->fresh()->status)->toBe('published')
        ->and(guardFlags('post', (int) $post->id))->toBe(0);
});

it('hides a post whose url is blocklisted', function () {
    $user = guardRegular();

    $post = app(PostService::class)->createPost([
        'user_id' => $user->id,
        'title' => 'Oferta imperdivel',
        'url' => 'https://spam.com/oferta',
        'content' => '',
        'status' => 'published',
    ]);

    expect($post->fresh()->status)->toBe('hidden')
        ->and(guardFlags('post', (int) $post->id))->toBe(1);
});

it('hides a newcomer post whose body carries an external link', function () {
    $user = guardNewcomer();

    $post = app(PostService::class)->createPost([
        'user_id' => $user->id,
        'title' => 'Pergunta para a comunidade',
        'content' => "Texto legitimo da pergunta.\n\nhttps://qualquer-coisa.com/promo",
    ]);

    expect($post->fresh()->status)->toBe('hidden')
        ->and(guardFlags('post', (int) $post->id))->toBe(1);
});

it('never flags staff', function () {
    $staff = User::factory()->createOne([
        'email_verified_at' => now(),
        'created_at' => now(),
        'role' => 'moderator',
    ]);
    $post = guardPost(guardRegular());

    $comment = app(CommentService::class)->createComment([
        'post_id' => $post->id,
        'user_id' => $staff->id,
        'content' => 'Aviso oficial: https://qualquer-coisa.com/promo',
    ]);

    expect($comment->fresh()->status)->toBe('visible');
});

it('does nothing at all while disabled', function () {
    config(['orbita.moderation.link_guard_enabled' => false]);

    $user = guardNewcomer();
    $post = guardPost(guardRegular());

    $comment = app(CommentService::class)->createComment([
        'post_id' => $post->id,
        'user_id' => $user->id,
        'content' => 'Texto e link bloqueado https://spam.com/oferta',
    ]);

    expect($comment->fresh()->status)->toBe('visible')
        ->and(Moderation::query()->where('action', 'link_flagged')->count())->toBe(0);
});
