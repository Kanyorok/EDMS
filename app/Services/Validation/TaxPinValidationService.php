<?php

namespace App\Services\Validation;

use App\Models\System\CodeDetail;

/**
 * Service for validating KRA Tax PINs and business type alignment
 */
class TaxPinValidationService
{
    // Business types that require 'P' prefix (incorporated entities)
    protected const INCORPORATED_TYPES = [
        'Company',
        'NGO',
        'Church',
        'LLC',
        'Limited Liability Company',
        'Corporation',
        'Partnership',
        'Cooperative',
        'Joint Venture',
        'Limited',
        'PLC',
        'Public Limited Company',
        'Private Limited Company',
        'Trust',
        'Foundation',
        'Association',
    ];

    // Business types that require 'A' prefix (individuals)
    protected const INDIVIDUAL_TYPES = [
        'Individual',
        'Sole Proprietorship',
        'Sole Trader',
        'Self Employed',
        'Freelancer',
    ];

    /**
     * Validate KRA PIN format
     *
     * @param string $pin
     * @return array{valid: bool, message: string, prefix?: string}
     */
    public static function validateFormat(string $pin): array
    {
        if (empty($pin)) {
            return ['valid' => false, 'message' => 'Tax PIN is required'];
        }

        $pin = strtoupper(trim($pin));

        // Check overall format: must be exactly 11 characters
        if (strlen($pin) !== 11) {
            return [
                'valid' => false,
                'message' => 'Tax PIN must be exactly 11 characters (e.g., A123456789Z or P123456789X)',
            ];
        }

        // Check pattern: [A/P] + 9 digits + [A-Z]
        if (! preg_match('/^[AP]\d{9}[A-Z]$/', $pin)) {
            return [
                'valid' => false,
                'message' => 'Invalid PIN format. Must be: A or P, followed by 9 digits, followed by 1 letter (e.g., A123456789Z or P123456789X)',
            ];
        }

        return [
            'valid' => true,
            'message' => '',
            'prefix' => substr($pin, 0, 1),
        ];
    }

    /**
     * Check if a business type is an incorporated entity
     *
     * @param string|int $businessType
     * @return bool
     */
    public static function isIncorporatedEntity(string|int $businessType): bool
    {
        $businessTypeText = self::getBusinessTypeText($businessType);

        return collect(self::INCORPORATED_TYPES)
            ->contains(fn ($type) => stripos($businessTypeText, $type) !== false);
    }

    /**
     * Check if a business type is an individual entity
     *
     * @param string|int $businessType
     * @return bool
     */
    public static function isIndividualEntity(string|int $businessType): bool
    {
        $businessTypeText = self::getBusinessTypeText($businessType);

        return collect(self::INDIVIDUAL_TYPES)
            ->contains(fn ($type) => stripos($businessTypeText, $type) !== false);
    }

    /**
     * Validate PIN alignment with business type
     *
     * @param string $pin
     * @param string|int $businessType
     * @return array{valid: bool, message: string}
     */
    public static function validateBusinessTypeAlignment(string $pin, string|int $businessType): array
    {
        if (empty($pin) || empty($businessType)) {
            return ['valid' => true, 'message' => ''];
        }

        // First validate format
        $formatValidation = self::validateFormat($pin);
        if (! $formatValidation['valid']) {
            return $formatValidation;
        }

        $pinPrefix = $formatValidation['prefix'];
        $isIncorporated = self::isIncorporatedEntity($businessType);
        $isIndividual = self::isIndividualEntity($businessType);

        // Individual business type with corporate PIN
        if ($isIndividual && $pinPrefix === 'P') {
            return [
                'valid' => false,
                'message' => 'Corporate Tax PINs (starting with P) cannot be used for individuals or sole proprietors. Individual Tax PINs must start with A.',
            ];
        }

        // Corporate business type with individual PIN
        if ($isIncorporated && $pinPrefix === 'A') {
            return [
                'valid' => false,
                'message' => 'Individual Tax PINs (starting with A) cannot be used for incorporated entities. Corporate Tax PINs must start with P.',
            ];
        }

        return ['valid' => true, 'message' => ''];
    }

    /**
     * Get business type text from ID or string
     *
     * @param string|int $businessType
     * @return string
     */
    protected static function getBusinessTypeText(string|int $businessType): string
    {
        // If it's already a string, return it
        if (is_string($businessType)) {
            return $businessType;
        }

        // If it's an ID, try to resolve it from CodeDetails
        if (is_numeric($businessType)) {
            $codeDetail = CodeDetail::find($businessType);

            return $codeDetail?->Description ?? $codeDetail?->Value ?? '';
        }

        return '';
    }

    /**
     * Extract PIN prefix (A or P)
     *
     * @param string $pin
     * @return string|null
     */
    public static function getPinPrefix(string $pin): ?string
    {
        $pin = strtoupper(trim($pin));

        if (strlen($pin) === 11 && in_array($pin[0], ['A', 'P'])) {
            return $pin[0];
        }

        return null;
    }

    /**
     * Get expected PIN prefix for a business type
     *
     * @param string|int $businessType
     * @return string|null Returns 'A', 'P', or null if unknown
     */
    public static function getExpectedPrefix(string|int $businessType): ?string
    {
        if (self::isIndividualEntity($businessType)) {
            return 'A';
        }

        if (self::isIncorporatedEntity($businessType)) {
            return 'P';
        }

        return null;
    }
}
