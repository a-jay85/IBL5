<?php

declare(strict_types=1);

namespace Utilities;

/**
 * StandingsGrouper - Determine groupings for conference/division standings
 * 
 * Maps regions to their appropriate grouping columns in the database.
 */
class StandingsGrouper
{
    public const CONFERENCES = ['Eastern', 'Western'];
    public const DIVISIONS = ['Atlantic', 'Central', 'Midwest', 'Pacific'];

    /**
     * Get grouping columns for a region
     * 
     * @param string $region The region name (Eastern, Western, Atlantic, Central, Midwest, Pacific)
     * @return array{grouping: string, groupingGB: string, groupingMagicNumber: string}
     */
    public static function getGroupingsFor(string $region): array
    {
        if (in_array($region, self::CONFERENCES, true)) {
            return [
                'grouping' => 'conference',
                'groupingGB' => 'confGB',
                'groupingMagicNumber' => 'confMagicNumber',
            ];
        }

        if (in_array($region, self::DIVISIONS, true)) {
            return [
                'grouping' => 'division',
                'groupingGB' => 'divGB',
                'groupingMagicNumber' => 'divMagicNumber',
            ];
        }

        // Default to conference grouping
        return [
            'grouping' => 'conference',
            'groupingGB' => 'confGB',
            'groupingMagicNumber' => 'confMagicNumber',
        ];
    }

    /**
     * Check if a region is a conference
     */
    public static function isConference(string $region): bool
    {
        return in_array($region, self::CONFERENCES, true);
    }

    /**
     * Check if a region is a division
     */
    public static function isDivision(string $region): bool
    {
        return in_array($region, self::DIVISIONS, true);
    }
}
