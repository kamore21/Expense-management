@props(['value', 'dateOnly' => false])

@php
    $regionalUser = auth()->user();
    $timeZone = $regionalUser?->timezone ?? config('app.timezone');
    $formatLocale = $regionalUser?->locale ?? app()->getLocale();
    $date = $value instanceof \DateTimeInterface
        ? \Carbon\CarbonImmutable::instance($value)
        : \Carbon\CarbonImmutable::parse($value, $timeZone);
    $dateValue = $dateOnly ? $date->format('Y-m-d') : $date->toIso8601String();
    $fallback = $dateOnly ? $date->format('Y-m-d') : $date->format('Y-m-d H:i');
@endphp

<time {{ $attributes }} data-regional-date data-value="{{ $dateValue }}" data-date-only="{{ $dateOnly ? 'true' : 'false' }}" data-locale="{{ $formatLocale }}" data-time-zone="{{ $timeZone }}" datetime="{{ $dateOnly ? $date->format('Y-m-d') : $date->toIso8601String() }}">{{ $fallback }}</time>