<?php

namespace Player\Contracts;

use Player\PlayerData;

/**
 * PlayerNameDecoratorInterface - Contract for player name formatting
 * 
 * Defines the interface for decorating player names with status indicators.
 * Handles visual markup for free agency eligibility, waivers status, etc.
 */
interface PlayerNameDecoratorInterface
{
    /**
     * Decorate a player name with status indicators
     * 
     * Adds special symbols to player names based on their status:
     * - "(name)*" - Player is on waivers (ordinal > JSB::WAIVERS_ORDINAL)
     * - "name^" - Player eligible for free agency at end of this season
     *            (contractCurrentYear == contractTotalYears)
     * - "name" - All other players
     * 
     * Returns the decorated name as a string ready for HTML display.
     * 
     * @param PlayerData $playerData The player to decorate
     * @return string Decorated player name with indicators
     */
    public function decoratePlayerName(PlayerData $playerData): string;
}
