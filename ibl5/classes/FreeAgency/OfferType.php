<?php

namespace FreeAgency;

/**
 * Free Agency offer type identifiers and constants
 * 
 * Provides type-safe constants for identifying contract offer types
 * and eliminates magic numbers throughout the Free Agency module.
 */
class OfferType
{
    /**
     * Custom offer - user-entered salary amounts
     */
    public const CUSTOM = 0;

    /**
     * Mid-Level Exception offers (1-6 years)
     */
    public const MLE_1_YEAR = 1;
    public const MLE_2_YEAR = 2;
    public const MLE_3_YEAR = 3;
    public const MLE_4_YEAR = 4;
    public const MLE_5_YEAR = 5;
    public const MLE_6_YEAR = 6;

    /**
     * Lower-Level Exception - single year at $145
     */
    public const LOWER_LEVEL_EXCEPTION = 7;

    /**
     * Veteran's Minimum - single year at player's veteran minimum salary
     */
    public const VETERAN_MINIMUM = 8;

    /**
     * Check if offer type is a Mid-Level Exception
     * 
     * @param int $offerType Offer type constant
     * @return bool True if MLE (1-6 years)
     */
    public static function isMLE(int $offerType): bool
    {
        return $offerType >= self::MLE_1_YEAR && $offerType <= self::MLE_6_YEAR;
    }

    /**
     * Check if offer type is Lower-Level Exception
     * 
     * @param int $offerType Offer type constant
     * @return bool True if LLE
     */
    public static function isLLE(int $offerType): bool
    {
        return $offerType === self::LOWER_LEVEL_EXCEPTION;
    }

    /**
     * Check if offer type is Veteran's Minimum
     * 
     * @param int $offerType Offer type constant
     * @return bool True if Veteran's Minimum
     */
    public static function isVeteranMinimum(int $offerType): bool
    {
        return $offerType === self::VETERAN_MINIMUM;
    }

    /**
     * Get human-readable name for offer type
     * 
     * @param int $offerType Offer type constant
     * @return string Human-readable offer type name
     */
    public static function getName(int $offerType): string
    {
        return match($offerType) {
            self::CUSTOM => 'Custom Offer',
            self::MLE_1_YEAR => '1-Year MLE',
            self::MLE_2_YEAR => '2-Year MLE',
            self::MLE_3_YEAR => '3-Year MLE',
            self::MLE_4_YEAR => '4-Year MLE',
            self::MLE_5_YEAR => '5-Year MLE',
            self::MLE_6_YEAR => '6-Year MLE',
            self::LOWER_LEVEL_EXCEPTION => 'Lower-Level Exception',
            self::VETERAN_MINIMUM => "Veteran's Minimum",
            default => 'Unknown'
        };
    }
}
