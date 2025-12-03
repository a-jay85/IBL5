<?php

namespace Player\Contracts;

use Player\PlayerData;

/**
 * PlayerInjuryCalculatorInterface - Contract for injury-related calculations
 * 
 * Defines the interface for calculating injury return dates and related data.
 */
interface PlayerInjuryCalculatorInterface
{
    /**
     * Calculate when an injured player will return
     * 
     * Takes the number of days remaining in the injury and the last simulation end date,
     * then calculates the expected return date.
     * 
     * If daysRemainingForInjury is 0 or negative, the player is not injured and
     * an empty string is returned.
     * 
     * Otherwise adds (daysRemainingForInjury + 1) days to the simulation end date
     * and returns the calculated return date.
     * 
     * @param PlayerData $playerData Player data with daysRemainingForInjury
     * @param string $rawLastSimEndDate Last simulation end date (Y-m-d format)
     * @return string Return date in Y-m-d format, or empty string if not injured
     */
    public function getInjuryReturnDate(PlayerData $playerData, string $rawLastSimEndDate): string;
}
