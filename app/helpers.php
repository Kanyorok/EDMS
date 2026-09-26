<?php

use App\Services\Core\BaseCurrencyService;

if (! function_exists('appDate')) {
    function appDate($date, ?string $format = null): string
    {
        if ($date === null) {
            return '';
        }
        if (is_string($date)) {
            try {
                $date = \Carbon\Carbon::parse($date);
            } catch (\Exception) {
                return $date;
            }
        }
        return $date instanceof \DateTimeInterface
            ? $date->format($format ?? config('app.date_format', 'M d, Y H:i'))
            : (string) $date;
    }
}

if (! function_exists('base_currency')) {
    function base_currency(): array
    {
        return app(BaseCurrencyService::class)->get();
    }
}

if (! function_exists('base_currency_or_fail')) {
    function base_currency_or_fail(): array
    {
        return app(BaseCurrencyService::class)->getOrFail();
    }
}
