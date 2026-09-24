<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function loginHead(string $url): string
{
    $html = (string) get($url)->assertOk()->getContent();

    return substr($html, 0, (int) strpos($html, '</head>'));
}

it('bare login is indexable and self canonical', function () {
    $head = loginHead('/login');

    expect($head)->not->toContain('noindex')
        ->and($head)->toContain('<link rel="canonical" href="'.url('/login').'">');
});

it('login with redirect_to is noindex and canonicalises to bare login', function () {
    $head = loginHead('/login?redirect_to='.urlencode(url('/p/abc/um-post')));

    expect($head)->toContain('<meta name="robots" content="noindex, follow">')
        ->and($head)->toContain('<link rel="canonical" href="'.url('/login').'">');
});

it('login links shown to guests are nofollow', function () {
    $author = User::factory()->createOne(['email_verified_at' => now()]);
    $post = Post::create([
        'user_id' => $author->id,
        'hashid' => 'loginseo1',
        'title' => 'Um post',
        'slug' => 'um-post',
        'content' => 'corpo',
        'status' => 'published',
        'allow_comments' => true,
        'published_at' => now(),
    ]);

    $html = (string) get(route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]))
        ->assertOk()->getContent();

    preg_match_all('/<a\b[^>]*href="[^"]*\/login\?redirect_to=[^"]*"[^>]*>/', $html, $links);

    expect($links[0])->not->toBeEmpty();

    foreach ($links[0] as $tag) {
        expect($tag)->toContain('rel="nofollow"');
    }
});
