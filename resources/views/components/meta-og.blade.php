@props(['meta' => [], 'url' => null])

<meta property="og:title" content="{{ $meta['title'] ?? config('orbita.name') }}">
<meta property="og:description" content="{{ $meta['description'] ?? '' }}">
<meta property="og:type" content="{{ $meta['type'] ?? 'website' }}">
<meta property="og:url" content="{{ $url ?? url()->current() }}">
<meta property="og:site_name" content="{{ config('orbita.name') }}">
<meta property="og:locale" content="{{ config('orbita.meta.og_locale') }}">

@if (! empty($meta['image']))
    <meta property="og:image" content="{{ $meta['image'] }}">
    @if (! empty($meta['image_width']) && ! empty($meta['image_height']))
        <meta property="og:image:width" content="{{ $meta['image_width'] }}">
        <meta property="og:image:height" content="{{ $meta['image_height'] }}">
    @endif
    <meta property="og:image:alt" content="{{ $meta['image_alt'] ?? ($meta['title'] ?? config('orbita.name')) }}">
@endif

@if (($meta['type'] ?? null) === 'article')
    @if (! empty($meta['published_time']))
        <meta property="article:published_time" content="{{ $meta['published_time'] }}">
    @endif
    @if (! empty($meta['modified_time']))
        <meta property="article:modified_time" content="{{ $meta['modified_time'] }}">
    @endif
    @if (! empty($meta['author']))
        <meta property="article:author" content="{{ $meta['author'] }}">
    @endif
@endif

<meta name="twitter:card" content="summary_large_image">
@if (filled(config('orbita.meta.twitter_site')))
    <meta name="twitter:site" content="{{ config('orbita.meta.twitter_site') }}">
@endif

<meta name="robots" content="{{ $meta['robots'] ?? 'index, follow' }}">

@if (! empty($meta['schema']))
    <script type="application/ld+json">{!! $meta['schema'] !!}</script>
@endif
