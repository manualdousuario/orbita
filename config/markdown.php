<?php

use App\Support\Markdown;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\DefaultAttributes\DefaultAttributesExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\Mention\MentionExtension;
use League\CommonMark\Extension\SmartPunct\SmartPunctExtension;
use Spatie\LaravelMarkdown\MarkdownRenderer;

// Ported verbatim from legacy App\Support\Markdown; rendered HTML must stay identical.
// SECURITY: html_input=strip and allow_unsafe_links=false are load-bearing (stored XSS).
return [
    'code_highlighting' => [
        // Off by design: fenced code is rare and Shiki would add a Node 20+ runtime dependency.
        'enabled' => false,
        'theme' => 'github-light',
    ],

    // Headings are rejected by Markdown::validate(); off keeps output byte-identical to legacy.
    'add_anchors_to_headings' => false,
    'render_anchors_as_links' => false,

    // Passed straight into the CommonMark Environment; mentions/default_attributes blocks feed their extensions.
    'commonmark_options' => [
        'html_input' => 'strip',
        'allow_unsafe_links' => false,
        'max_nesting_level' => 5,
        'commonmark' => [
            'enable_em' => true,
            'enable_strong' => true,
            'use_asterisk' => true,
            'use_underscore' => true,
            'unordered_list_markers' => ['-', '*', '+'],
        ],
        'default_attributes' => [
            Link::class => [
                'target' => '_blank',
                'rel' => 'noopener noreferrer',
            ],
        ],
        'mentions' => [
            'user_mention' => [
                'prefix' => '@',
                'pattern' => '[a-zA-Z0-9_]{1,50}',
                'generator' => [Markdown::class, 'handleUserMention'],
            ],
            'hashtag_mention' => [
                'prefix' => '#',
                'pattern' => '[\p{L}\p{N}_]{1,50}',
                'generator' => [Markdown::class, 'handleHashtagMention'],
            ],
        ],
    ],

    // Content-addressed cache (md5 of markdown + options) in a dedicated store; overridden to `array` in tests.
    'cache_store' => env('MARKDOWN_CACHE_STORE', 'markdown'),
    'cache_duration' => (int) env('MARKDOWN_CACHE_DURATION', 7776000),

    'renderer_class' => MarkdownRenderer::class,

    // CommonMarkCoreExtension is added by the renderer itself; do not list it here.
    'extensions' => [
        GithubFlavoredMarkdownExtension::class,
        DefaultAttributesExtension::class,
        MentionExtension::class,
        SmartPunctExtension::class,
    ],

    'block_renderers' => [],
    'inline_renderers' => [],
    'inline_parsers' => [],
];
