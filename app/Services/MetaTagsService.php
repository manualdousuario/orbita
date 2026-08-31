<?php

namespace App\Services;

use App\Models\Page;
use App\Models\Term;
use App\Models\User;
use App\Support\Avatar;
use Spatie\SchemaOrg\BaseType;
use Spatie\SchemaOrg\BreadcrumbList;
use Spatie\SchemaOrg\Organization;
use Spatie\SchemaOrg\Person;
use Spatie\SchemaOrg\Schema;

/**
 * Builds Open Graph, Twitter Card, and JSON-LD schema meta tags for public pages.
 */
class MetaTagsService
{
    private string $defaultImage;

    /** Intrinsic size of $defaultImage, emitted as og:image:width/height. */
    private const DEFAULT_IMAGE_SIZE = [1280, 720];

    /** The publisher logo. A wordmark, NOT the OG card — Google rejects a 1280x720 "logo". */
    private const LOGO = ['path' => '/images/orbita-wordmark.png', 'width' => 784, 'height' => 225];

    /** Upper bound on how many comments go into the JSON-LD of a post. */
    private const SCHEMA_COMMENT_LIMIT = 10;

    /** @var array<string, mixed> */
    private array $defaultMeta;

    public function __construct()
    {
        // public/images/ — the legacy assets/images/ path does not exist here.
        $this->defaultImage = url('/images/ogimage.jpg');
        $this->defaultMeta = [
            'title' => config('orbita.name'),
            'description' => $this->processDescription((string) config('orbita.meta.default_description')),
            'image' => $this->defaultImage,
            'image_width' => self::DEFAULT_IMAGE_SIZE[0],
            'image_height' => self::DEFAULT_IMAGE_SIZE[1],
            'type' => 'website',
            'robots' => 'index, follow',
        ];
    }

    /**
     * @param  array<string, mixed>  $post
     * @return array<string, mixed>
     */
    public function forPost(array $post): array
    {
        if (empty($post['title'])) {
            return $this->forGenericPage('Post', 'Post details', true);
        }

        $title = $post['title'].' | '.config('orbita.name');
        $description = $post['excerpt']
            ?? mb_substr(
                strip_tags(html_entity_decode($post['content_html'] ?? '')),
                0,
                (int) config('orbita.ogimage.description_length'),
            );
        $description = $this->processDescription($description);

        if ($description === '') {
            $description = $this->processDescription((string) $post['title']);
        }

        $image = $this->getPostOgImage($post);

        $meta = [
            'title' => $title,
            'description' => $description,
            'image' => $image,
            'image_width' => (int) config('orbita.ogimage.width', 1200),
            'image_height' => (int) config('orbita.ogimage.height', 630),
            'image_alt' => (string) $post['title'],
            'type' => 'article',
            'robots' => 'index, follow',
            'published_time' => date('c', strtotime($post['published_at'] ?? $post['created_at'])),
            'modified_time' => date('c', strtotime($post['updated_at'])),
            'author' => isset($post['author']) ? ($post['author']['display_name'] ?? $post['author']['username'] ?? 'Anonymous') : 'Anonymous',
        ];

        $meta['schema'] = $this->generateDiscussionSchema($post, $meta);

        return $meta;
    }

    /**
     * @return array<string, mixed>
     */
    public function forGenericPage(string $title, string $description = '', bool $indexable = true, ?string $schema = null, ?string $image = null): array
    {
        $title = $title.' | '.config('orbita.name');
        $description = $description ? $this->processDescription($description) : $this->defaultMeta['description'];

        $meta = [
            'title' => $title,
            'description' => $description,
            'image' => $image ?? $this->defaultImage,
            'type' => 'website',
            'robots' => $indexable ? 'index, follow' : 'noindex, follow',
        ];

        if ($schema !== null) {
            $meta['schema'] = $schema;
        }

        return $meta;
    }

    /**
     * Meta tags for the home feed, with WebSite + SearchAction schema.
     *
     * @param  array{label: string, description: string}  $tabMeta
     * @return array<string, mixed>
     */
    public function forHome(array $tabMeta): array
    {
        $siteName = (string) config('orbita.name');
        $description = $tabMeta['description'] ?? '';
        $description = $description ? $this->processDescription($description) : $this->defaultMeta['description'];

        return [
            'title' => $siteName.' - '.$tabMeta['label'],
            'description' => $description,
            'image' => $this->defaultImage,
            'type' => 'website',
            'robots' => 'index, follow',
            'schema' => $this->generateWebSiteSchema(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forProfile(User $user, ?int $postsTotal = null): array
    {
        $displayName = $user->display_name ?: $user->username;
        $description = $user->bio ?: 'Perfil de '.$displayName.' no '.config('orbita.name').'.';
        $url = route('users.profile', ['username' => $user->username]);

        return $this->forGenericPage(
            $displayName,
            $description,
            true,
            $this->generateProfilePageSchema($user, $url, $postsTotal),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function forStaticPage(Page $page, string $excerpt): array
    {
        $url = route('pages.show', ['slug' => $page->slug]);
        $description = $this->processDescription($excerpt);

        return $this->forGenericPage(
            $page->title,
            $description,
            true,
            $this->generateWebPageSchema(
                $page->title,
                $description,
                $url,
                $page->updated_at !== null ? (string) $page->updated_at : null,
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function forTag(Term $tag, string $description, array $items = []): array
    {
        $url = route('tags.show', ['slug' => $tag->slug]);
        $description = $this->processDescription($description);
        $title = '#'.$tag->name;

        return $this->forGenericPage(
            $title,
            $description,
            true,
            $this->generateCollectionPageSchema($title, $description, $url, $items),
        );
    }

    public function forRevisions(string $postTitle, string $postUrl, string $revisionsUrl): array
    {
        return $this->forGenericPage(
            'Revisões - '.$postTitle,
            'Histórico de edições da publicação.',
            false,
            $this->encodeGraph([
                $this->breadcrumb([
                    ['name' => 'Início', 'url' => url('/')],
                    ['name' => $postTitle, 'url' => $postUrl],
                    ['name' => 'Revisões', 'url' => $revisionsUrl],
                ]),
            ]),
        );
    }

    private function encodeSchema(array $graph): string
    {
        return json_encode(
            $graph,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG,
        ) ?: '{}';
    }

    /**
     * Builds DiscussionForumPosting schema so comments and reactions are indexed.
     *
     * @param  array<string, mixed>  $post
     * @param  array<string, mixed>  $meta
     */
    private function generateDiscussionSchema(array $post, array $meta): string
    {
        $hashId = $post['hash_id'] ?? null;

        $pageUrl = $hashId !== null
            ? route('posts.show', array_filter([
                'hashid' => $hashId,
                'slug' => $post['slug'] ?? null,
            ]))
            : url('/');

        $posting = Schema::discussionForumPosting()
            ->setProperty('@id', $pageUrl)
            ->headline($post['title'] ?? 'Untitled Post')
            ->name($post['title'] ?? 'Untitled Post')
            ->description($meta['description'] ?? '')
            ->url($pageUrl)
            ->image($meta['image'] ?? $this->defaultImage)
            ->datePublished(new \DateTimeImmutable($post['published_at'] ?? $post['created_at'] ?? 'now'))
            ->dateModified(new \DateTimeImmutable($post['updated_at'] ?? 'now'))
            ->author($this->authorNode($post['author'] ?? null))
            ->publisher($this->publisher())
            ->mainEntityOfPage(Schema::webPage()->setProperty('@id', $pageUrl));

        if (! empty($post['url'])) {
            $posting->sharedContent(Schema::webPage()->url((string) $post['url']));
        }

        if (! empty($post['tags'])) {
            $posting->keywords(implode(', ', array_column($post['tags'], 'name')));
        }

        $interactions = $this->interactionCounters($post);
        if ($interactions !== []) {
            $posting->interactionStatistic($interactions);
        }

        $comments = $this->commentNodes($post['comments'] ?? []);
        if ($comments !== []) {
            $posting->comment($comments);
        }

        if (isset($post['comment_count'])) {
            $posting->commentCount((int) $post['comment_count']);
        }

        return $this->encodeGraph([
            $posting,
            $this->breadcrumb([
                ['name' => 'Início', 'url' => url('/')],
                ['name' => (string) ($post['title'] ?? ''), 'url' => $pageUrl],
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $author
     */
    private function authorNode(?array $author): Person
    {
        $name = $author['display_name'] ?? $author['username'] ?? 'Anonymous';

        $person = Schema::person()->name($name);

        if (! empty($author['username'])) {
            $person->url(route('users.profile', ['username' => $author['username']]));
        }

        return $person;
    }

    private function interactionCounters(array $post): array
    {
        $counters = [];

        if (isset($post['comment_count'])) {
            $counters[] = Schema::interactionCounter()
                ->interactionType(Schema::commentAction())
                ->userInteractionCount((int) $post['comment_count']);
        }

        if (isset($post['reaction_count'])) {
            $counters[] = Schema::interactionCounter()
                ->interactionType(Schema::likeAction())
                ->userInteractionCount((int) $post['reaction_count']);
        }

        return $counters;
    }

    private function commentNodes(array $comments): array
    {
        $nodes = [];

        foreach (array_slice($comments, 0, self::SCHEMA_COMMENT_LIMIT) as $comment) {
            $node = Schema::comment()
                ->text($this->processDescription((string) ($comment['text'] ?? '')))
                ->author($this->authorNode($comment['author'] ?? null));

            if (! empty($comment['created_at'])) {
                $node->dateCreated(new \DateTimeImmutable((string) $comment['created_at']));
            }

            if (! empty($comment['url'])) {
                $node->url((string) $comment['url']);
            }

            if (isset($comment['reaction_count'])) {
                $node->interactionStatistic(
                    Schema::interactionCounter()
                        ->interactionType(Schema::likeAction())
                        ->userInteractionCount((int) $comment['reaction_count']),
                );
            }

            $nodes[] = $node;
        }

        return $nodes;
    }

    private function publisher(): Organization
    {
        return Schema::organization()
            ->name(config('orbita.name'))
            ->url(url('/'))
            ->logo(
                Schema::imageObject()
                    ->url(url(self::LOGO['path']))
                    ->width(self::LOGO['width'])
                    ->height(self::LOGO['height']),
            );
    }

    private function breadcrumb(array $items): BreadcrumbList
    {
        $elements = [];

        foreach (array_values($items) as $index => $item) {
            $elements[] = Schema::listItem()
                ->position($index + 1)
                ->name($item['name'])
                ->item($item['url']);
        }

        return Schema::breadcrumbList()->itemListElement($elements);
    }

    private function encodeGraph(array $nodes): string
    {
        $graph = [];

        foreach ($nodes as $node) {
            $array = $node instanceof BaseType ? $node->toArray() : $node;
            unset($array['@context']);
            $graph[] = $array;
        }

        return $this->encodeSchema([
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ]);
    }

    /**
     * Builds WebSite + SearchAction schema for the home feed.
     */
    private function generateWebSiteSchema(): string
    {
        $website = Schema::webSite()
            ->name(config('orbita.name'))
            ->url(url('/'))
            ->potentialAction(
                Schema::searchAction()
                    ->target(url('/search').'?q={search_term_string}')
                    ->setProperty('query-input', 'required name=search_term_string'),
            );

        return $this->encodeGraph([$website, $this->publisher()]);
    }

    /**
     * Builds CollectionPage schema for a tag listing, with posts as an ItemList.
     *
     * @param  array<int, array{title: string, url: string}>  $items
     */
    private function generateCollectionPageSchema(string $title, string $description, string $url, array $items = []): string
    {
        $page = Schema::collectionPage()
            ->name($title)
            ->description($description)
            ->url($url)
            ->isPartOf(Schema::webSite()->name(config('orbita.name'))->url(url('/')));

        if ($items !== []) {
            $elements = [];

            foreach (array_values($items) as $index => $item) {
                $elements[] = Schema::listItem()
                    ->position($index + 1)
                    ->name($item['title'])
                    ->url($item['url']);
            }

            $page->mainEntity(Schema::itemList()->itemListElement($elements));
        }

        return $this->encodeGraph([
            $page,
            $this->breadcrumb([
                ['name' => 'Início', 'url' => url('/')],
                ['name' => $title, 'url' => $url],
            ]),
        ]);
    }

    /**
     * Builds ProfilePage schema wrapping a Person for a user's profile.
     */
    private function generateProfilePageSchema(User $user, string $url, ?int $postsTotal = null): string
    {
        $displayName = $user->display_name ?: $user->username;
        $avatarUrl = filled($user->avatar_url) ? $user->avatar_url : Avatar::url((string) $user->username);

        $person = Schema::person()
            ->name($displayName)
            ->url($url)
            ->image($avatarUrl);

        // The @ handle is a real alternate name for the person on this site, and it is how
        // people refer to each other in comments.
        if (filled($user->username) && $displayName !== $user->username) {
            $person->alternateName('@'.$user->username);
        }

        if (filled($user->bio)) {
            $person->description($this->processDescription((string) $user->bio));
        }

        $page = Schema::profilePage()
            ->name($displayName)
            ->url($url)
            ->mainEntity($person);

        // dateCreated on a ProfilePage is the "member since" Google shows next to a profile.
        if ($user->created_at !== null) {
            $page->dateCreated(new \DateTimeImmutable((string) $user->created_at));
        }

        if ($postsTotal !== null) {
            $person->interactionStatistic(
                Schema::interactionCounter()
                    ->interactionType(Schema::writeAction())
                    ->userInteractionCount($postsTotal),
            );
        }

        return $this->encodeGraph([
            $page,
            $this->breadcrumb([
                ['name' => 'Início', 'url' => url('/')],
                ['name' => $displayName, 'url' => $url],
            ]),
        ]);
    }

    /**
     * Plain WebPage schema for a static DB-backed page (about, guidelines, ...).
     */
    private function generateWebPageSchema(string $title, string $description, string $url, ?string $modifiedAt = null): string
    {
        $page = Schema::webPage()
            ->name($title)
            ->description($description)
            ->url($url)
            ->isPartOf(Schema::webSite()->name(config('orbita.name'))->url(url('/')));

        if ($modifiedAt !== null) {
            $page->dateModified(new \DateTimeImmutable($modifiedAt));
        }

        return $this->encodeGraph([
            $page,
            $this->breadcrumb([
                ['name' => 'Início', 'url' => url('/')],
                ['name' => $title, 'url' => $url],
            ]),
        ]);
    }

    /**
     * Returns the on-demand OG image route for the post.
     *
     * @param  array<string, mixed>  $post
     */
    private function getPostOgImage(array $post): string
    {
        $hashId = $post['hash_id'] ?? null;

        if (! $hashId) {
            return $this->defaultImage;
        }

        return route('posts.og', ['hashid' => $hashId]);
    }

    private function processDescription(string $description): string
    {
        $description = trim($description);
        $description = strip_tags($description);
        $description = str_replace(["\r", "\n"], ' ', $description);
        $description = preg_replace('/\s+/', ' ', $description);

        if (mb_strlen($description) > (int) config('orbita.meta.max_description_length')) {
            $description = mb_substr($description, 0, (int) config('orbita.meta.max_description_length')).'...';
        }

        return $description;
    }
}
