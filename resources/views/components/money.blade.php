@props(['amount', 'currency' => null, 'locale' => null])

@php
    $regionalUser = auth()->user();
    $currencyCode = $currency ?? $regionalUser?->currency_code ?? 'USD';
    $formatLocale = $locale ?? $regionalUser?->locale ?? app()->getLocale();
    $fractionDigits = \Symfony\Component\Intl\Currencies::getFractionDigits($currencyCode);
@endphp

<span {{ $attributes->merge(['class' => 'regional-money']) }} data-regional-money data-amount="{{ number_format((float) $amount, 8, '.', '') }}" data-currency="{{ $currencyCode }}" data-locale="{{ $formatLocale }}">{{ $currencyCode }} {{ number_format((float) $amount, $fractionDigits, '.', ',') }}</span>