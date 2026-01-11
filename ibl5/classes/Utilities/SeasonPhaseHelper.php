<?php

declare(strict_types=1);

namespace Utilities;

/**
 * SeasonPhaseHelper - Season phase logic utilities
 * 
 * Determines months and settings based on the current season phase.
 */
class SeasonPhaseHelper
{
    /**
     * Get the starting month for a given season phase
     */
    public static function getMonthForPhase(string $phase): int
    {
        if ($phase === "HEAT") {
            return \Season::IBL_HEAT_MONTH;
        }
        return \Season::IBL_REGULAR_SEASON_STARTING_MONTH;
    }

    /**
     * Check if a phase is a regular season phase (not HEAT or playoffs)
     */
    public static function isRegularSeasonPhase(string $phase): bool
    {
        return in_array($phase, ['Regular Season', 'Preseason'], true);
    }

    /**
     * Check if a phase is the HEAT phase
     */
    public static function isHeatPhase(string $phase): bool
    {
        return $phase === 'HEAT';
    }
}
