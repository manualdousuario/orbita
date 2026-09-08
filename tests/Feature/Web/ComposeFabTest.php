<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Support\HashId;
use Database\Seeders\ReactionTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

function fabAuthor(string $username): User
{
    return User::factory()->createOne([
        'username' => $username,
        'email_verified_at' => now(),
    ]);
}

function fabPost(User $author, int $hashSeed): Post
{
    return Post::create([
        'user_id' => $author->id,
        'hashid' => HashId::encode($hashSeed),
        'title' => 'Post com comentarios',
        'slug' => 'post-com-comentarios',
        'content' => 'Conteúdo',
        'status' => 'published',
        'allow_comments' => true,
        'published_at' => now()->subHour(),
    ]);
}

it('the floating button hides itself while the reader composes', function () {
    seed(ReactionTypeSeeder::class);

    $post = fabPost(fabAuthor('nave'), 1);

    $html = get('/p/'.$post->hashid)->assertOk()->getContent();

    expect($html)->toContain('x-data="composeFab"');

    expect($html)->toContain('x-bind:inert="! shown"');
    expect($html)->toContain("x-bind:class=\"shown ? 'opacity-100'");
    expect($html)->not->toContain('x-bind:inert="! visible"');
});

it('the floating button stays out of pages that never had it', function () {
    $author = fabAuthor('sonda');

    get('/u/'.$author->username)->assertOk()->assertDontSee('x-data="composeFab"', false);
});

it('every comment editor is marked as a composer', function () {
    seed(ReactionTypeSeeder::class);

    $author = fabAuthor('estrela');
    $post = fabPost($author, 2);

    Comment::create([
        'user_id' => $author->id,
        'post_id' => $post->id,
        'parent_id' => null,
        'hashid' => HashId::encode(1),
        'content' => 'Comentario raiz',
        'nesting_level' => 0,
        'status' => 'visible',
    ]);

    $guestHtml = get('/p/'.$post->hashid)->assertOk()->getContent();
    expect(substr_count($guestHtml, 'data-composer'))->toBe(1);

    $userHtml = actingAs($author)->get('/p/'.$post->hashid)->assertOk()->getContent();
    expect(substr_count($userHtml, 'data-composer'))->toBe(2);
});

it('every comment node tells the floating button when a reply opens', function () {
    seed(ReactionTypeSeeder::class);

    $author = fabAuthor('cometa');
    $post = fabPost($author, 3);

    Comment::create([
        'user_id' => $author->id,
        'post_id' => $post->id,
        'parent_id' => null,
        'hashid' => HashId::encode(1),
        'content' => 'Comentario raiz',
        'nesting_level' => 0,
        'status' => 'visible',
    ]);

    $html = actingAs($author)->get('/p/'.$post->hashid)->assertOk()->getContent();

    expect(substr_count($html, "\$watch('replying'"))->toBe(1);
    expect($html)->toContain("\$dispatch('reply-toggled', { open })");
});

it('the floating button script follows open replies and composer focus', function () {
    $js = (string) file_get_contents(base_path('resources/js/app.js'));

    expect($js)->toContain("window.addEventListener('reply-toggled', this.onReplyToggled)");
    expect($js)->toContain("window.addEventListener('comment-created', this.onCommentCreated)");
    expect($js)->toContain("document.addEventListener('focusin', this.onFocusIn)");
    expect($js)->toContain("document.addEventListener('focusout', this.onFocusOut)");
    expect($js)->toContain("closest?.('[data-composer]')");

    $init = (string) strstr((string) strstr($js, "Alpine.data('composeFab'"), 'const footer', true);
    expect($init)->toContain("document.addEventListener('focusin', this.onFocusIn)");

    $destroy = (string) strstr((string) strstr($js, "Alpine.data('composeFab'"), 'destroy()');
    foreach (['reply-toggled', 'comment-created', 'focusin', 'focusout'] as $event) {
        expect($destroy)->toContain("removeEventListener('".$event."'");
    }
});
