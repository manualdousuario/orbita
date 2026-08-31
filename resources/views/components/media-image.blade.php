@props([
    'media',
    'width' => 1200,
    'height' => null,
    'fit' => 'contain',
    'alt' => '',
])

@php
    $opts = array_filter([
        'width' => $width,
        'height' => $height,
        'fit' => $fit,
    ], fn ($v) => $v !== null);

    $src = \App\Support\ImageUrl::media($media, $opts);
@endphp

<img src="{{ $src }}" alt="{{ $alt }}" loading="lazy" decoding="async"
     @if (($media->metadata['width'] ?? null) && ($media->metadata['height'] ?? null))
          width="{{ $media->metadata['width'] }}" height="{{ $media->metadata['height'] }}"
     @endif
     {{ $attributes }}>
