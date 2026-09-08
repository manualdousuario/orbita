<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Post;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

const LOCKED = 'Comentários fechados';

const LOCKED_TITLE = 'Assunto encerrado pela moderacao';

const OPEN_TITLE = 'Assunto ainda em discussao';

/**
 * @param  array<string, mixed>  $overrides
 */
function badgePost(User $author, string $hashid, string $title, array $overrides = []): Post
{
    return Post::create([
        'user_id' => $author->id,
        'hashid' => $hashid,
        'title' => $title,
        'slug' => $hashid.'-slug',
        'content' => 'Conteudo',
        'status' => 'published',
        'score' => 0,
        'comment_count' => 0,
        'allow_comments' => true,
        'is_pinned' => false,
        'published_at' => now()->subHour(),
        ...$overrides,
    ]);
}

function badgeAuthor(): User
{
    return User::factory()->createOne(['email_verified_at' => now()]);
}

it('feed marks the post locked by moderation and leaves the open one alone', function () {
    config(['orbita.home.default_tab' => 'all']);
    $author = badgeAuthor();
    badgePost($author, 'lk1', LOCKED_TITLE, ['allow_comments' => false]);
    badgePost($author, 'op1', OPEN_TITLE);

    $html = (string) get('/all')->assertOk()->getContent();

    expect($html)->toContain(LOCKED)
        ->and(substr_count($html, LOCKED))->toBe(2); // title attribute + sr-only
    expect(strpos($html, LOCKED))->toBeLessThan(strpos($html, LOCKED_TITLE))
        ->and(strpos($html, LOCKED))->toBeGreaterThan(strpos($html, OPEN_TITLE) ?: 0);
});

it('feed shows no badge when every post accepts comments', function () {
    config(['orbita.home.default_tab' => 'all']);
    badgePost(badgeAuthor(), 'op2', OPEN_TITLE);

    get('/all')->assertOk()->assertDontSee(LOCKED);
});

it('feed marks a post auto-closed by inactivity', function () {
    config(['orbita.home.default_tab' => 'all']);
    badgePost(badgeAuthor(), 'ac1', LOCKED_TITLE, ['status' => 'closed', 'allow_comments' => false]);

    get('/all')->assertOk()->assertSee(LOCKED);
});

it('a pinned and locked post shows both markers, pin first', function () {
    config(['orbita.home.default_tab' => 'all']);
    badgePost(badgeAuthor(), 'pl1', LOCKED_TITLE, ['is_pinned' => true, 'allow_comments' => false]);

    get('/all')->assertOk()->assertSeeInOrder(['Post fixado', LOCKED, LOCKED_TITLE]);
});

it('tabs restricted to open posts never render the badge', function () {
    config(['orbita.home.default_tab' => 'all']);
    badgePost(badgeAuthor(), 'lk2', LOCKED_TITLE, ['allow_comments' => false, 'comment_count' => 5]);

    get('/more-comments')->assertOk()->assertDontSee(LOCKED)->assertDontSee(LOCKED_TITLE);
});

it('tag page marks the locked post', function () {
    $tag = Term::create(['taxonomy' => 'tag', 'name' => 'Ciencia', 'slug' => 'ciencia', 'is_active' => true]);
    $post = badgePost(badgeAuthor(), 'tg1', LOCKED_TITLE, ['allow_comments' => false]);
    $post->terms()->attach($tag->id);

    get(route('tags.show', ['slug' => 'ciencia']))->assertOk()->assertSee(LOCKED);
});

it('profile page marks the locked post', function () {
    $author = badgeAuthor();
    badgePost($author, 'pf1', LOCKED_TITLE, ['allow_comments' => false]);

    get(route('users.profile', ['username' => $author->username]))->assertOk()->assertSee(LOCKED);
});

it('bookmarks page marks the locked post', function () {
    $author = badgeAuthor();
    $reader = badgeAuthor();
    $post = badgePost($author, 'bm1', LOCKED_TITLE, ['allow_comments' => false]);

    DB::table('bookmarks')->insert([
        'user_id' => $reader->id,
        'post_id' => $post->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($reader)->get(route('bookmarks.index'))->assertOk()->assertSee(LOCKED);
});
