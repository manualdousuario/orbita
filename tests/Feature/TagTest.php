<?php

declare(strict_types=1);

/**
 * Hashtags create and link tag terms, and /t/{slug} lists the tag's posts.
 */

namespace Tests\Feature;

use App\Models\Term;
use App\Models\User;
use App\Services\PostService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function postService(): PostService
{
    return app(PostService::class);
}

function tagAuthor(): User
{
    return User::factory()->createOne(['username' => 'author', 'email_verified_at' => now()]);
}

it('publishing a post creates and links active tags from hashtags', function () {
    $user = tagAuthor();

    $post = postService()->createPost([
        'user_id' => $user->id,
        'title' => 'Sobre #Laravel',
        'content' => 'falando de #php e #laravel',
        'status' => 'published',
    ]);

    // #Laravel appears in title and content but must dedupe to one tag.
    $tags = Term::query()->where('taxonomy', 'tag')->orderBy('slug')->get();
    expect($tags->pluck('slug')->all())->toBe(['laravel', 'php'])
        ->and($tags->every(fn (Term $t) => $t->is_active))->toBeTrue();

    expect($post->terms()->where('taxonomy', 'tag')->pluck('slug')->all())
        ->toEqualCanonicalizing(['laravel', 'php']);

    // usage_count mirrors term_relationships.
    expect(Term::where('slug', 'laravel')->first()->usage_count)->toBe(1);
});

it('the per post tag limit is enforced', function () {
    config(['orbita.posts.limit_tags' => 2]);
    $user = tagAuthor();

    postService()->createPost([
        'user_id' => $user->id,
        'title' => 'muitas',
        'content' => '#a #b #c #d',
        'status' => 'published',
    ]);

    expect(Term::where('taxonomy', 'tag')->count())->toBe(2);
});

it('editing a post removes tags that are no longer present', function () {
    $user = tagAuthor();

    $post = postService()->createPost([
        'user_id' => $user->id,
        'title' => 'inicial',
        'content' => '#keep #drop',
        'status' => 'published',
    ]);
    expect(Term::where('slug', 'drop')->first()->usage_count)->toBe(1);

    postService()->updatePost($post, ['content' => 'agora só #keep']);

    expect($post->fresh()->terms()->where('taxonomy', 'tag')->pluck('slug')->all())
        ->toEqualCanonicalizing(['keep']);

    // The dropped tag's row stays (an admin may curate it) but its usage_count drops to 0.
    expect(Term::where('slug', 'drop')->first()->usage_count)->toBe(0);
});

it('a draft creates no tags', function () {
    $user = tagAuthor();

    $post = postService()->createPost([
        'user_id' => $user->id,
        'title' => 'rascunho com #oculta',
        'content' => 'nada de #tags ainda',
        'status' => 'draft',
    ]);

    expect(Term::where('taxonomy', 'tag')->count())->toBe(0, 'drafts never create tags')
        ->and($post->terms()->where('taxonomy', 'tag')->count())->toBe(0);
});

it('publishing a draft creates its tags', function () {
    $user = tagAuthor();

    $post = postService()->createPost([
        'user_id' => $user->id,
        'title' => 't',
        'content' => 'sobre #cinema',
        'status' => 'draft',
    ]);
    expect(Term::where('taxonomy', 'tag')->count())->toBe(0);

    // Publishing changes only the status (no text change) -- tags must still be created.
    postService()->updatePost($post, ['status' => 'published']);

    expect(Term::where('slug', 'cinema')->where('is_active', true)->count())->toBe(1);

    expect($post->fresh()->terms()->where('taxonomy', 'tag')->pluck('slug')->all())
        ->toEqualCanonicalizing(['cinema']);
});

it('an existing deactivated tag is linked but not reactivated', function () {
    Term::create(['taxonomy' => 'tag', 'name' => 'spam', 'slug' => 'spam', 'is_active' => false]);
    $user = tagAuthor();

    postService()->createPost([
        'user_id' => $user->id,
        'title' => 't',
        'content' => 'usando #spam',
        'status' => 'published',
    ]);

    expect(Term::where('slug', 'spam')->first()->is_active)->toBeFalse('must not be reactivated');
});

it('tag page lists published posts and hides drafts', function () {
    $user = tagAuthor();

    postService()->createPost([
        'user_id' => $user->id, 'title' => 'Publicado', 'content' => '#tema', 'status' => 'published',
    ]);
    postService()->createPost([
        'user_id' => $user->id, 'title' => 'Rascunho', 'content' => '#tema', 'status' => 'draft',
    ]);

    $response = get(route('tags.show', ['slug' => 'tema']));

    $response->assertOk();
    $response->assertSee('Publicado');
    $response->assertDontSee('Rascunho');
});

it('missing or inactive tag is 404', function () {
    get(route('tags.show', ['slug' => 'inexistente']))->assertNotFound();

    Term::create(['taxonomy' => 'tag', 'name' => 'oculta', 'slug' => 'oculta', 'is_active' => false]);
    get(route('tags.show', ['slug' => 'oculta']))->assertNotFound();
});
