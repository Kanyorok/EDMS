<?php

namespace App\Support\HR;

use Illuminate\Validation\Rule;

class PayrollGLAccountRules
{
    public static function expense(bool $required = true): array
    {
        return self::forTypes(['E'], $required);
    }

    public static function liability(bool $required = true): array
    {
        return self::forTypes(['L'], $required);
    }

    public static function asset(bool $required = true): array
    {
        return self::forTypes(['A'], $required);
    }

    public static function assetOrLiability(bool $required = true): array
    {
        return self::forTypes(['A', 'L'], $required);
    }

    private static function forTypes(array $types, bool $required): array
    {
        return [
            $required ? 'required' : 'nullable',
            'integer',
            Rule::exists('t_FinanceGLAccounts', 'Id')->where(function ($query) use ($types) {
                $query->where('IsActive', 1)
                    ->where('IsPostingAccount', 1)
                    ->whereNull('DeletedOn')
                    ->whereIn('GLAccountTypeID', $types);
            }),
        ];
    }
}
