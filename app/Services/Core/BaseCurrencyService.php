<?php

namespace App\Services\Core;

use App\Models\Settings\OrganizationProfile;
use Illuminate\Validation\ValidationException;

class BaseCurrencyService
{
    public function get(): array
    {
        $profile = OrganizationProfile::query()
            ->with('baseCurrency')
            ->orderByDesc('Id')
            ->first();

        if (! $profile || ! $profile->baseCurrency) {
            return [
                'found' => false,
                'message' => 'Base currency has not been configured in Organization Profile.',
                'currency' => null,
            ];
        }

        $currency = $profile->baseCurrency;

        return [
            'found' => true,
            'message' => 'Base currency resolved successfully.',
            'currency' => [
                'name' => $currency->Name,
                'demonym' => $currency->Demonym,
                'code' => $currency->Code,
                'symbol' => $currency->Symbol,
                'symbol_native' => $currency->SymbolNative,
                'decimal_digits' => $currency->DecimalDigits,
                'rounding' => $currency->Rounding,
                'major_single' => $currency->MajorSingle,
                'major_plural' => $currency->MajorPlural,
                'iso_num' => $currency->ISOnum,
                'minor_single' => $currency->MinorSingle,
                'minor_plural' => $currency->MinorPlural,
                'iso_digits' => $currency->ISOdigits,
                'decimals' => $currency->Decimals,
                'num_to_basic' => $currency->NumToBasic,
            ],
        ];
    }

    public function getOrFail(): array
    {
        $result = $this->get();

        if (! $result['found']) {
            throw ValidationException::withMessages([
                'BaseCurrencyID' => $result['message'],
            ]);
        }

        return $result;
    }
}
