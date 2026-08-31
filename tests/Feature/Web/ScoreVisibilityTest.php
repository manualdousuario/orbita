<?php

declare(strict_types=1);

/**
 * orbita.posts.show_score hides only the score badge, nothing else.
 */

namespace Tests\Feature\Web;

use App\Models\Post;
use App\Models\User;
use App\Support\HashId;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function scoreAuthor(): User
{
    return User::factory()->createOne([
        'username' => 'pontuador',
        'display_name' => 'Pontuador',
        'email_verified_at' => now(),
    ]);
}

function seedScoredPost(User $author): Post
{
    return Post::create([
        'user_id' => $author->id,
        'hashid' => HashId::encode(1),
        'title' => 'Um post com pontuação',
        'slug' => 'um-post-com-pontuacao',
        'content' => 'corpo',
        'status' => 'published',
        'score' => 7,
        'comment_count' => 0,
        'published_at' => now()->subHour(),
    ]);
}

it('score is visible by default on the home feed', function () {
    seedScoredPost(scoreAuthor());

    $html = (string) get(route('home'))->assertOk()->getContent();

    expect($html)->toContain('pontos"');
});

it('home feed hides the score when disabled', function () {
    config(['orbita.posts.show_score' => false]);
    $post = seedScoredPost(scoreAuthor());

    $html = (string) get(route('home'))->assertOk()->getContent();

    expect($html)->not->toContain('pontos"');
    // The row itself is still there.
    expect($html)->toContain($post->title);
});

it('post page hides the score when disabled', function () {
    config(['orbita.posts.show_score' => false]);
    $post = seedScoredPost(scoreAuthor());

    $html = (string) get('/p/'.$post->hashid)->assertOk()->getContent();

    expect($html)->not->toContain('pontos"');
    expect($html)->toContain($post->title);
});

it('profile listing hides the score when disabled', function () {
    config(['orbita.posts.show_score' => false]);
    $author = scoreAuthor();
    $post = seedScoredPost($author);

    $html = (string) get('/u/'.$author->username)->assertOk()->getContent();

    expect($html)->not->toContain('pontos');
    expect($html)->toContain($post->title);
});

it('disabling the score does not change the stored value', function () {
    config(['orbita.posts.show_score' => false]);
    $post = seedScoredPost(scoreAuthor());

    get(route('home'))->assertOk();

    expect((int) $post->fresh()?->score)->toBe(7);
});
