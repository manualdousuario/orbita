<?php

declare(strict_types=1);

/**
 * Markdown rendering security posture and mention/caching behaviour.
 */

namespace Tests\Feature;

use App\Models\Term;
use App\Models\User;
use App\Support\Markdown;
use App\Support\MentionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** Fresh MentionResolver (drop the per-request memo), i.e. a new request. */
function freshRequest(): void
{
    app()->forgetInstance(MentionResolver::class);
}

/**
 * @return list<string> SQL statements executed while running $fn
 */
function captureSql(callable $fn): array
{
    $sql = [];
    DB::listen(function ($q) use (&$sql) {
        $sql[] = $q->sql;
    });
    $fn();

    return $sql;
}

// --- Security (gate: these must never regress) -------------------------------------------

it('raw html tags are stripped', function () {
    // html_input=strip removes the tags; leftover text is inert (escaped as text in <p>).
    expect(Markdown::toHtml('hello <script>alert(1)</script> world'))->not->toContain('<script');

    $img = Markdown::toHtml('oops <img src=x onerror=alert(1)> done');
    expect($img)->not->toContain('<img');
    expect($img)->not->toContain('onerror');
});

it('unsafe links are not rendered as anchors', function () {
    $html = Markdown::toHtml('click [here](javascript:alert(1))');

    expect($html)->not->toContain('javascript:');
    expect($html)->not->toContain('<a href="javascript');
});

it('safe link gets target and rel attributes', function () {
    expect(Markdown::toHtml('[site](https://example.com)'))
        ->toContain('href="https://example.com"')
        ->toContain('target="_blank"')
        ->toContain('rel="noopener noreferrer"');
});

it('user mention links to profile', function () {
    User::factory()->createOne(['username' => 'alice']);

    expect(Markdown::toHtml('hi @alice'))
        ->toContain('href="/u/alice"')
        ->toContain('@alice');
});

it('hashtag links to the active tag page', function () {
    Term::create(['taxonomy' => 'tag', 'name' => 'Laravel', 'slug' => 'laravel', 'is_active' => true]);

    expect(Markdown::toHtml('about #laravel'))
        ->toContain('href="'.route('tags.show', ['slug' => 'laravel']).'"');
});

it('hashtag for an inactive tag is plain text', function () {
    Term::create(['taxonomy' => 'tag', 'name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);

    $html = Markdown::toHtml('about #hidden');

    // A deactivated tag never links (its /t/ page 404s); the hashtag stays plain text.
    expect($html)->not->toContain('/t/hidden');
    expect($html)->not->toContain('<a ');
});

it('repeated mention of the same handle is a single query', function () {
    User::factory()->createOne(['username' => 'bob']);
    freshRequest();

    $sql = captureSql(fn () => Markdown::toHtml('@bob then @bob and @bob again'));

    $userLookups = array_filter($sql, fn ($s) => str_contains($s, 'from `users`'));
    expect($userLookups)->toHaveCount(1, 'the per-request memo must collapse repeats to one lookup');
});

it('second render of the same content hits cache and touches no database', function () {
    User::factory()->createOne(['username' => 'carol']);
    $md = 'ping @carol';

    // First render (fresh request) resolves the mention against the database.
    freshRequest();
    $first = captureSql(fn () => Markdown::toHtml($md));
    expect(array_filter($first, fn ($s) => str_contains($s, 'from `users`')))->not->toBeEmpty();

    // Second render: HTML served from cache, so the mention generator never runs.
    freshRequest();
    $second = captureSql(fn () => Markdown::toHtml($md));
    expect($second)->toBeEmpty('a cached render must not query the database');
});

it('cached html is stale after a username change until the store is cleared', function () {
    $user = User::factory()->createOne(['username' => 'dave']);
    $md = 'yo @dave';

    freshRequest();
    expect(Markdown::toHtml($md))->toContain('href="/u/dave"');

    // Rename the user. The old handle no longer resolves to anyone.
    $user->update(['username' => 'david']);

    // Cache still serves the old link — documented, accepted staleness.
    freshRequest();
    expect(Markdown::toHtml($md))->toContain('href="/u/dave"');

    // Clearing the dedicated store is the supported flush; the handle now renders as plain text.
    Cache::store(config('markdown.cache_store'))->clear();
    freshRequest();
    expect(Markdown::toHtml($md))->not->toContain('href="/u/dave"');
});
