<?php

declare(strict_types=1);

namespace Updater;
use Season\Season;


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
            return Season::IBL_HEAT_MONTH;
        }
        if ($phase === "Preseason") {
            return Season::IBL_PRESEASON_MONTH;
        }
        return Season::IBL_REGULAR_SEASON_STARTING_MONTH;
    }
}
