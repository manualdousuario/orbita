<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function replytocomPostUrl(): string
{
    $author = User::factory()->createOne(['email_verified_at' => now()]);
    $post = Post::create([
        'user_id' => $author->id,
        'hashid' => 'replyto1',
        'title' => 'Um post',
        'slug' => 'um-post',
        'content' => 'corpo',
        'status' => 'published',
        'allow_comments' => true,
        'published_at' => now(),
    ]);

    return route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]);
}

it('WordPress replytocom links are permanently redirected to the post', function () {
    $url = replytocomPostUrl();

    get($url.'?replytocom=286256')->assertStatus(301)->assertRedirect($url);
});

it('the redirect keeps the other query parameters', function () {
    $url = replytocomPostUrl();

    get($url.'?replytocom=286256&comentarios=2')
        ->assertStatus(301)
        ->assertRedirect($url.'?comentarios=2');
});

it('a post without replytocom still renders', function () {
    get(replytocomPostUrl())->assertOk();
});
