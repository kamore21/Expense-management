@props(['value', 'dateOnly' => false, 'prefix' => ''])

@php
    $regionalUser = auth()->user();
    $timeZone = $regionalUser?->timezone ?? config('app.timezone');
    $formatLocale = $regionalUser?->locale ?? app()->getLocale();
    $date = $value instanceof \DateTimeInterface
        ? \Carbon\CarbonImmutable::instance($value)
        : \Carbon\CarbonImmutable::parse($value, $timeZone);
    $dateValue = $dateOnly ? $date->format('Y-m-d') : $date->toIso8601String();
    $fallback = $dateOnly ? $date->format('M j, Y') : $date->format('M j, Y, g:i A');
@endphp

<time {{ $attributes }} data-regional-date data-value="{{ $dateValue }}" data-date-only="{{ $dateOnly ? 'true' : 'false' }}" data-prefix="{{ $prefix }}" data-locale="{{ $formatLocale }}" data-time-zone="{{ $timeZone }}" datetime="{{ $dateOnly ? $date->format('Y-m-d') : $date->toIso8601String() }}">{{ $prefix }}{{ $fallback }}</time>