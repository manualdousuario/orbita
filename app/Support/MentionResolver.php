<?php

namespace App\Support;

use App\Models\Term;
use App\Models\User;
use League\CommonMark\Extension\Mention\Mention;

/**
 * Resolves @user and #hashtag mentions to URLs during Markdown rendering.
 */
class MentionResolver
{
    /** @var array<string, array{0: ?string, 1: ?string}> typed handle => [profile path, canonical username] */
    private array $users = [];

    /** @var array<string, array{0: ?string, 1: ?string}> slug => [search URL, tag display name] */
    private array $tags = [];

    /** Resolve @username to a profile link, or null (mention left as plain text). */
    public function resolveUser(Mention $mention): ?Mention
    {
        [$url, $username] = $this->users[$mention->getIdentifier()] ??= $this->lookupUser($mention->getIdentifier());

        if ($url === null) {
            return null;
        }

        $mention->setUrl($url);
        $mention->setLabel('@'.$username);

        return $mention;
    }

    /** Resolve #hashtag to the tag's search results, or null (mention left as plain text). */
    public function resolveTag(Mention $mention): ?Mention
    {
        $slug = Slug::make($mention->getIdentifier());
        [$url, $name] = $this->tags[$slug] ??= $this->lookupTag($slug);

        if ($url === null) {
            return null;
        }

        $mention->setUrl($url);
        $mention->setLabel('#'.$name);

        return $mention;
    }

    /**
     * @return array{0: ?string, 1: ?string} [profile path, canonical username]
     */
    private function lookupUser(string $username): array
    {
        $user = User::query()->where('username', $username)->whereNull('anonymized_at')->first();

        if ($user === null) {
            return [null, null];
        }

        return ['/u/'.$user->username, $user->username];
    }

    /**
     * @return array{0: ?string, 1: ?string} [search URL, tag display name]
     */
    private function lookupTag(string $slug): array
    {
        $tag = Term::query()
            ->where('taxonomy', 'tag')
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if ($tag === null) {
            return [null, null];
        }

        // Only active tags link; deactivated ones render as plain text.
        return [route('tags.show', ['slug' => $tag->slug]), $tag->name];
    }
}
