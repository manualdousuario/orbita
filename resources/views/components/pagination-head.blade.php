@props([
    'total',
    'perPage',
    'pageName' => 'page',
])

@php
    $lastPage = max(1, (int) ceil(((int) $total) / max(1, (int) $perPage)));
    $page = max(1, (int) request()->query($pageName, 1));

    $pageUrl = function (int $n) use ($pageName) {
        $query = request()->query();

        if ($n <= 1) {
            unset($query[$pageName]);
        } else {
            $query[$pageName] = $n;
        }

        return url()->current().($query === [] ? '' : '?'.http_build_query($query));
    };
@endphp

@if ($page > 1)
    <link rel="prev" href="{{ $pageUrl($page - 1) }}">
@endif

@if ($page < $lastPage)
    <link rel="next" href="{{ $pageUrl($page + 1) }}">
@endif
