<?php

declare(strict_types=1);

/**
 * Focused, DB-agnostic coverage for MetaTagsService.
 */

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Post;
use App\Models\Term;
use App\Models\User;
use App\Services\MetaTagsService;
use RuntimeException;

beforeEach(function () {
    // Deterministic site identity for assertions.
    config(['orbita.name' => 'TestSite']);
    config(['app.description' => 'A test community']);
    config(['app.url' => 'http://localhost']);
});

/**
 * Pulls one node of the given type out of the schema @graph.
 *
 * Missing nodes throw rather than assert: the return value feeds the assertions
 * below, so it has to be narrowed for the analyser.
 *
 * @return array<string, mixed>
 */
function schemaNode(string $json, string $type): array
{
    $decoded = json_decode($json, true);

    expect($decoded['@context'] ?? null)->toBe('https://schema.org');
    expect($decoded)->toHaveKey('@graph');

    foreach ($decoded['@graph'] as $node) {
        if (($node['@type'] ?? null) === $type) {
            return $node;
        }
    }

    throw new RuntimeException("no {$type} node found in the graph");
}

it('for post produces expected og twitter and title tags', function () {
    // "Given a Post" — built in memory (never persisted) as the array shape forPost() consumes.
    $post = new Post([
        'title' => 'My Great Post',
        'slug' => 'my-great-post',
        'content' => 'Body content.',
        'status' => 'published',
    ]);

    $meta = app(MetaTagsService::class)->forPost([
        'title' => $post->title,
        'excerpt' => "  A short   excerpt.\n",
        'created_at' => '2026-07-01 12:00:00',
        'updated_at' => '2026-07-02 12:00:00',
        'author' => ['display_name' => 'Jane Doe', 'username' => 'jane'],
    ]);

    expect($meta['title'])->toBe('My Great Post | TestSite')
        // og:description / twitter:description source — whitespace collapsed by processDescription.
        ->and($meta['description'])->toBe('A short excerpt.')
        ->and($meta['type'])->toBe('article')
        ->and($meta['robots'])->toBe('index, follow')
        // og:image / twitter:image — falls back to the default image (no hashid, so no render URL).
        ->and($meta['image'])->toBe(url('/images/ogimage.jpg'));

    // The old value was /assets/images/ogimage.jpg, a path the Laravel app never had.
    expect(public_path('images/ogimage.jpg'))->toBeFile();

    expect($meta['published_time'])->toBe(date('c', strtotime('2026-07-01 12:00:00')))
        ->and($meta['modified_time'])->toBe(date('c', strtotime('2026-07-02 12:00:00')))
        ->and($meta['author'])->toBe('Jane Doe');

    // DiscussionForumPosting, not Article: an Article has nowhere to put the discussion.
    $schema = schemaNode($meta['schema'], 'DiscussionForumPosting');
    expect($schema['headline'])->toBe('My Great Post')
        ->and($schema['description'])->toBe('A short excerpt.')
        ->and($schema['author']['name'])->toBe('Jane Doe')
        ->and($schema['publisher']['name'])->toBe('TestSite');

    // Author URL resolves through the router (/u/jane), not the legacy /users/jane 404.
    expect($schema['author']['url'])->toBe(route('users.profile', ['username' => 'jane']));
    expect($schema['author']['url'])->not->toContain('/users/');

    // publisher.logo must be a logo, with dimensions — it used to point at the 1280x720 OG card.
    $logo = $schema['publisher']['logo'];
    expect($logo['url'])->toBe(url('/images/orbita-wordmark.png'))
        ->and($logo['width'])->toBe(784)
        ->and($logo['height'])->toBe(225);
    expect(public_path('images/orbita-wordmark.png'))->toBeFile();

    // Every page must carry a breadcrumb; there were none anywhere in the project.
    $crumbs = schemaNode($meta['schema'], 'BreadcrumbList');
    expect($crumbs['itemListElement'][0]['name'])->toBe('Início')
        ->and($crumbs['itemListElement'][0]['position'])->toBe(1);
});

/**
 * The discussion (comments, authors, reaction counts) is in the structured data.
 */
it('the discussion itself is in the structured data', function () {
    $meta = app(MetaTagsService::class)->forPost([
        'title' => 'Thread',
        'excerpt' => 'x',
        'hash_id' => 'abc123',
        'created_at' => '2026-07-01 12:00:00',
        'updated_at' => '2026-07-01 12:00:00',
        'comment_count' => 7,
        'reaction_count' => 12,
        'author' => ['username' => 'jane', 'display_name' => 'Jane'],
        'comments' => [
            [
                'text' => 'Primeiro comentário.',
                'created_at' => '2026-07-01 13:00:00',
                'url' => 'http://localhost/p/abc123#comment-c1',
                'reaction_count' => 3,
                'author' => ['username' => 'bob', 'display_name' => 'Bob'],
            ],
        ],
    ]);

    $schema = schemaNode($meta['schema'], 'DiscussionForumPosting');

    expect($schema['commentCount'])->toBe(7)
        ->and($schema['comment'][0]['text'])->toBe('Primeiro comentário.')
        ->and($schema['comment'][0]['author']['name'])->toBe('Bob');

    $counts = collect($schema['interactionStatistic'])
        ->mapWithKeys(fn (array $c): array => [$c['interactionType']['@type'] => $c['userInteractionCount']]);

    expect($counts['CommentAction'])->toBe(7)
        ->and($counts['LikeAction'])->toBe(12);
});

/**
 * An anonymised author has no profile page, so the Person carries no url.
 */
it('an author without a profile gets no url', function () {
    $meta = app(MetaTagsService::class)->forPost([
        'title' => 'Orphan',
        'excerpt' => 'x',
        'hash_id' => 'abc123',
        'created_at' => '2026-07-01 12:00:00',
        'updated_at' => '2026-07-01 12:00:00',
        'author' => ['username' => null, 'display_name' => 'Conta excluída'],
    ]);

    $author = schemaNode($meta['schema'], 'DiscussionForumPosting')['author'];

    expect($author['name'])->toBe('Conta excluída');
    expect($author)->not->toHaveKey('url');
});

/**
 * A link post with no body still gets a non-empty derived description.
 */
it('a link post without a body still gets a description', function () {
    $meta = app(MetaTagsService::class)->forPost([
        'title' => 'Só um link',
        'content_html' => '',
        'hash_id' => 'abc123',
        'url' => 'https://example.com/artigo',
        'created_at' => '2026-07-01 12:00:00',
        'updated_at' => '2026-07-01 12:00:00',
        'author' => ['username' => 'jane'],
    ]);

    expect($meta['description'])->toBe('Só um link');
    expect($meta['description'])->not->toBe('');
});

it('article main entity uses the real post route', function () {
    $meta = app(MetaTagsService::class)->forPost([
        'title' => 'Routed Post',
        'excerpt' => 'x',
        'hash_id' => 'abc123',
        'created_at' => '2026-07-01 12:00:00',
        'updated_at' => '2026-07-01 12:00:00',
        'author' => ['username' => 'jane'],
    ]);

    $schema = schemaNode($meta['schema'], 'DiscussionForumPosting');

    // mainEntityOfPage @id points at /p/{hashid}, not the legacy /posts/{hashid} 404.
    expect($schema['mainEntityOfPage']['@id'])->toBe(route('posts.show', ['hashid' => 'abc123']));
    expect($schema['mainEntityOfPage']['@id'])->not->toContain('/posts/');
});

it('for generic page marks non indexable pages', function () {
    $meta = app(MetaTagsService::class)->forGenericPage('About', 'About this site', false);

    expect($meta['title'])->toBe('About | TestSite')
        ->and($meta['description'])->toBe('About this site')
        ->and($meta['type'])->toBe('website')
        ->and($meta['robots'])->toBe('noindex, follow')
        ->and($meta['image'])->toBe(url('/images/ogimage.jpg'));

    expect($meta)->not->toHaveKey('schema');
});

it('for generic page accepts schema and image overrides', function () {
    $meta = app(MetaTagsService::class)->forGenericPage('About', 'About this site', true, '{"@type":"WebPage"}', 'https://example.test/custom.png');

    expect($meta['schema'])->toBe('{"@type":"WebPage"}')
        ->and($meta['image'])->toBe('https://example.test/custom.png');
});

it('for home produces website schema with search action', function () {
    $meta = app(MetaTagsService::class)->forHome([
        'label' => 'Populares',
        'description' => 'Posts dos últimos 7 dias, ordenados por score de reações.',
    ]);

    expect($meta['title'])->toBe('TestSite - Populares')
        ->and($meta['description'])->toBe('Posts dos últimos 7 dias, ordenados por score de reações.')
        ->and($meta['robots'])->toBe('index, follow')
        ->and($meta['image'])->toBe(url('/images/ogimage.jpg'));

    $schema = schemaNode($meta['schema'], 'WebSite');
    expect($schema['potentialAction']['target'])->toBe(url('/search').'?q={search_term_string}')
        ->and($schema['potentialAction']['query-input'])->toBe('required name=search_term_string');
});

it('for profile uses bio and produces profile page schema', function () {
    $user = new User([
        'username' => 'jane',
        'display_name' => 'Jane Doe',
        'bio' => 'Astrophysicist and coffee enthusiast.',
    ]);

    $meta = app(MetaTagsService::class)->forProfile($user);

    expect($meta['title'])->toBe('Jane Doe | TestSite')
        ->and($meta['description'])->toBe('Astrophysicist and coffee enthusiast.')
        ->and($meta['image'])->toBe(url('/images/ogimage.jpg'));

    $schema = schemaNode($meta['schema'], 'ProfilePage');
    expect($schema['mainEntity']['@type'])->toBe('Person')
        ->and($schema['mainEntity']['name'])->toBe('Jane Doe')
        ->and($schema['url'])->toBe(route('users.profile', ['username' => 'jane']));
});

it('for profile falls back to a generic description without a bio', function () {
    $user = new User(['username' => 'jane', 'display_name' => 'Jane Doe']);

    $meta = app(MetaTagsService::class)->forProfile($user);

    expect($meta['description'])->toBe('Perfil de Jane Doe no TestSite.');
});

it('for static page produces web page schema', function () {
    $page = new Page(['title' => 'Guidelines', 'slug' => 'guidelines']);

    $meta = app(MetaTagsService::class)->forStaticPage($page, 'How to behave on this site.');

    expect($meta['title'])->toBe('Guidelines | TestSite')
        ->and($meta['description'])->toBe('How to behave on this site.');

    $schema = schemaNode($meta['schema'], 'WebPage');
    expect($schema['url'])->toBe(route('pages.show', ['slug' => 'guidelines']));
});

it('for tag produces collection page schema', function () {
    $tag = new Term(['name' => 'space', 'slug' => 'space']);

    $meta = app(MetaTagsService::class)->forTag($tag, 'Posts about space.');

    expect($meta['title'])->toBe('#space | TestSite');

    $schema = schemaNode($meta['schema'], 'CollectionPage');
    expect($schema['url'])->toBe(route('tags.show', ['slug' => 'space']));
});

/**
 * A title with </script> must not break out of the JSON-LD script tag (stored XSS).
 */
it('schema escapes angle brackets so a title cannot break out of the script tag', function () {
    $payload = 'Titulo</script><script>alert(document.domain)</script>';

    $meta = app(MetaTagsService::class)->forPost([
        'title' => $payload,
        'excerpt' => 'Harmless excerpt.',
        'created_at' => '2026-07-01 12:00:00',
        'updated_at' => '2026-07-01 12:00:00',
        'author' => ['display_name' => 'Jane Doe', 'username' => 'jane'],
    ]);

    // JSON_HEX_TAG escapes every < and >, so no literal angle bracket survives anywhere.
    expect($meta['schema'])->not->toContain('<');
    expect($meta['schema'])->not->toContain('>');
    // The payload is still there, just neutralised as < ... >.
    expect($meta['schema'])->toContain('u003C');

    // Escaped, not mangled: the title still round-trips to its original value.
    expect(schemaNode($meta['schema'], 'DiscussionForumPosting')['headline'])->toBe($payload);
});

/** The same escaping must hold for the other builders, which share encodeSchema(). */
it('profile schema escapes angle brackets in display name', function () {
    $user = new User([
        'username' => 'jane',
        'display_name' => 'Jane</script><script>alert(1)</script>',
        'bio' => 'Bio.',
    ]);

    $meta = app(MetaTagsService::class)->forProfile($user);

    expect($meta['schema'])->not->toContain('</script>');
});

/**
 * Imported posts date from published_at, not the migration-date created_at.
 */
it('published date prefers published at over created at', function () {
    $meta = app(MetaTagsService::class)->forPost([
        'title' => 'Imported Post',
        'excerpt' => 'Excerpt.',
        'created_at' => '2026-07-30 12:00:00',   // migration timestamp
        'published_at' => '2019-03-14 08:30:00', // original WordPress post_date
        'updated_at' => '2026-07-30 12:00:00',
        'author' => ['display_name' => 'Jane Doe', 'username' => 'jane'],
    ]);

    expect($meta['published_time'])->toBe(date('c', strtotime('2019-03-14 08:30:00')));

    $schema = schemaNode($meta['schema'], 'DiscussionForumPosting');
    expect($schema['datePublished'])->toStartWith('2019-03-14');
});

/** Posts created natively have no published_at until they leave draft; created_at must serve. */
it('published date falls back to created at when published at is absent', function () {
    $meta = app(MetaTagsService::class)->forPost([
        'title' => 'Native Post',
        'excerpt' => 'Excerpt.',
        'created_at' => '2026-07-30 12:00:00',
        'updated_at' => '2026-07-30 12:00:00',
        'author' => ['display_name' => 'Jane Doe', 'username' => 'jane'],
    ]);

    expect($meta['published_time'])->toBe(date('c', strtotime('2026-07-30 12:00:00')));

    $schema = schemaNode($meta['schema'], 'DiscussionForumPosting');
    expect($schema['datePublished'])->toStartWith('2026-07-30');
});
