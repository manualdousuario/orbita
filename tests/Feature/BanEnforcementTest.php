<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Moderation;
use App\Models\Post;
use App\Models\User;
use App\Support\HashId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function banSeq(): int
{
    static $n = 0;

    return ++$n;
}

function banPost(User $user, string $status = 'published'): Post
{
    $n = banSeq();

    return Post::create([
        'user_id' => $user->id,
        'hashid' => HashId::encode(8000 + $n),
        'title' => 'Titulo '.$n,
        'slug' => 'titulo-ban-'.$n,
        'content' => 'Corpo',
        'status' => $status,
        'published_at' => now(),
    ]);
}

function banComment(User $user, Post $post, string $status = 'visible'): Comment
{
    return Comment::create([
        'user_id' => $user->id,
        'post_id' => $post->id,
        'hashid' => HashId::encode(9000 + banSeq()),
        'content' => 'spam [jogo](https://jogo.io)',
        'status' => $status,
        'nesting_level' => 0,
    ]);
}

it('banning hides the user posts and comments and logs what it hid', function () {
    $host = banPost(User::factory()->createOne(['email_verified_at' => now()]));
    $spammer = User::factory()->createOne(['email_verified_at' => now(), 'remember_token' => 'antigo']);
    $post = banPost($spammer);
    $draft = banPost($spammer, 'draft');
    $comment = banComment($spammer, $host);
    $alreadyHidden = banComment($spammer, $host, 'hidden');

    $spammer->forceFill(['is_banned' => true])->save();

    expect($post->fresh()->status)->toBe('hidden')
        ->and($draft->fresh()->status)->toBe('draft')
        ->and($comment->fresh()->status)->toBe('hidden')
        ->and($spammer->fresh()->remember_token)->not->toBe('antigo')
        ->and($host->fresh()->comment_count)->toBe(0);

    $log = Moderation::query()->where('action', 'hide')->where('target_type', 'user')->sole();

    expect((int) $log->target_id)->toBe((int) $spammer->id)
        ->and($log->metadata)->toBe(['posts' => [(int) $post->id], 'comments' => [(int) $comment->id]])
        ->and($alreadyHidden->fresh()->status)->toBe('hidden');
});

it('unrelated user updates leave content alone', function () {
    $user = User::factory()->createOne(['email_verified_at' => now()]);
    $post = banPost($user);

    $user->update(['bio' => 'nova bio']);

    expect($post->fresh()->status)->toBe('published')
        ->and(Moderation::query()->count())->toBe(0);
});

it('a banned user still logged in is logged out on the next request', function () {
    $user = User::factory()->createOne(['email_verified_at' => now()]);

    actingAs($user);
    $user->forceFill(['is_banned' => true])->save();

    get(route('home'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'Sua conta foi banida']);

    assertGuest();
});

it('a banned user cannot comment through livewire', function () {
    $post = banPost(User::factory()->createOne(['email_verified_at' => now()]));
    $user = User::factory()->createOne(['email_verified_at' => now(), 'is_banned' => true]);

    Livewire::actingAs($user)
        ->test('comment-form', ['postId' => $post->id])
        ->set('content', 'Um comentario que nao deve existir.')
        ->call('save');

    expect(Comment::query()->count())->toBe(0);
});
