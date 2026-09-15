@props(['value'])

{{--
    Locale-aware date display. Carbon follows APP_LOCALE, and the L/LT/LLL
    presets come from CLDR, so a new language needs no translation strings.
--}}
@php
    $date = filled($value) ? \Illuminate\Support\Carbon::parse($value) : null;
@endphp

@if ($date)
    <span {{ $attributes->merge(['title' => $date->isoFormat('LLL')]) }}>{{ $date->isoFormat('L LT') }}</span>
@endif
