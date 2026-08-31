<?php

namespace App\Support;

use App\Models\Term;
use App\Models\User;
use League\CommonMark\Extension\Mention\Mention;
use Spatie\LaravelMarkdown\MarkdownRenderer;

/**
 * Converts Markdown to HTML with GFM extensions, mentions, and validation.
 */
class Markdown
{
    /** Mention generators (see config/markdown.php); both delegate to the memoising resolver. */
    public static function handleUserMention(Mention $mention): ?Mention
    {
        return app(MentionResolver::class)->resolveUser($mention);
    }

    public static function handleHashtagMention(Mention $mention): ?Mention
    {
        return app(MentionResolver::class)->resolveTag($mention);
    }

    public static function toHtml(string $markdown): string
    {
        if ($markdown === '') {
            return '';
        }

        return app(MarkdownRenderer::class)->toHtml($markdown);
    }

    public static function toText(string $markdown): string
    {
        return strip_tags(self::toHtml($markdown));
    }

    public static function excerpt(string $markdown, int $length = 200): string
    {
        $text = self::toText($markdown);

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length).'...';
    }

    public static function extractMentions(string $markdown): array
    {
        preg_match_all('/@([a-zA-Z0-9_]{1,50})/', $markdown, $matches);

        return array_values(array_unique($matches[1]));
    }

    public static function extractHashtags(string $markdown): array
    {
        preg_match_all('/#([\p{L}\p{N}_]{1,50})/u', $markdown, $matches);

        return array_values(array_unique($matches[1]));
    }

    /** Resolve @mentions to existing User models. */
    public static function getValidMentions(string $markdown): array
    {
        $usernames = self::extractMentions($markdown);

        if ($usernames === []) {
            return [];
        }

        return User::query()->whereIn('username', $usernames)->get()->all();
    }

    /** Resolve #hashtags to existing tag Terms. */
    public static function getValidHashtags(string $markdown): array
    {
        $names = self::extractHashtags($markdown);

        if ($names === []) {
            return [];
        }

        $slugs = array_map([Slug::class, 'make'], $names);

        return Term::query()->where('taxonomy', 'tag')->whereIn('slug', $slugs)->get()->all();
    }

    private static array $htmlTagNames = [
        'a', 'abbr', 'address', 'area', 'article', 'aside', 'audio',
        'b', 'base', 'bdi', 'bdo', 'blockquote', 'body', 'br', 'button',
        'canvas', 'caption', 'cite', 'code', 'col', 'colgroup',
        'data', 'datalist', 'dd', 'del', 'details', 'dfn', 'dialog', 'div', 'dl', 'dt',
        'em', 'embed',
        'fieldset', 'figcaption', 'figure', 'footer', 'form',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'head', 'header', 'hgroup', 'hr', 'html',
        'i', 'iframe', 'img', 'input', 'ins',
        'kbd',
        'label', 'legend', 'li', 'link',
        'main', 'map', 'mark', 'menu', 'meta', 'meter',
        'nav', 'noscript',
        'object', 'ol', 'optgroup', 'option', 'output',
        'p', 'param', 'picture', 'pre', 'progress',
        'q',
        'rp', 'rt', 'ruby',
        's', 'samp', 'script', 'section', 'select', 'slot', 'small', 'source', 'span', 'strong', 'style', 'sub', 'summary', 'sup', 'svg',
        'table', 'tbody', 'td', 'template', 'textarea', 'tfoot', 'th', 'thead', 'time', 'title', 'tr', 'track',
        'u', 'ul',
        'var', 'video',
        'wbr',
    ];

    /** Returns a list of human-readable validation issues (empty = valid). */
    public static function validate(string $text): array
    {
        $issues = [];

        $htmlTagPattern = '/<\/?('.implode('|', self::$htmlTagNames).')(?:\s[^>]*)?\/?>/i';
        if (preg_match($htmlTagPattern, $text)) {
            $issues[] = 'Tags HTML não são permitidas.';
        }

        if (preg_match('/^#{1,6}\s/m', $text)) {
            $issues[] = 'Títulos (headings) não são permitidos.';
        }

        // Setext headings: "text\n---" must not smuggle an <h2> past the ATX check.
        if (preg_match('/(^|\n)[ \t]*(?![ \t]*>)(?![ \t]*(?:[-+*]|\d+[.)])[ \t])[^\n]*\p{L}[^\n]*\n[ \t]*(?:=+|-+)[ \t]*(?=\n|$)/u', $text)) {
            $issues[] = 'Títulos (headings) não são permitidos.';
        }

        // Only our own /s/ image endpoint is allowed, blocking hotlinks and tracking pixels.
        foreach (self::validateInlineImages($text) as $imageIssue) {
            $issues[] = $imageIssue;
        }

        return $issues;
    }

    /**
     * Inline `![alt](url)` images are valid only when the URL points at our image endpoint.
     *
     * @return list<string>
     */
    private static function validateInlineImages(string $text): array
    {
        // ![alt](url) optionally followed by a "title".
        if (! preg_match_all('/!\[[^\]]*\]\(([^)\s]+)(?:\s+"[^"]*")?\)/', $text, $matches)) {
            return [];
        }

        foreach ($matches[1] as $url) {
            if (! self::isOwnMediaUrl((string) $url)) {
                return ['Imagens externas não são permitidas. Envie a imagem pelo editor.'];
            }
        }

        return [];
    }

    /** True when the URL is served by our /s/{path} image endpoint (optionally on our host). */
    private static function isOwnMediaUrl(string $url): bool
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '';

        if (! str_starts_with($path, '/s/')) {
            return false;
        }

        if (isset($parsed['host'])) {
            $allowed = array_filter([
                (string) parse_url((string) config('app.url'), PHP_URL_HOST),
                (string) parse_url((string) config('orbita.url'), PHP_URL_HOST),
                (string) (request()?->getHost() ?? ''),
            ]);

            if ($allowed !== [] && ! in_array($parsed['host'], $allowed, true)) {
                return false;
            }
        }

        return true;
    }
}
