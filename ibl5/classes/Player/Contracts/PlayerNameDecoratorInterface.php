<?php

declare(strict_types=1);

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
     * Return the player's raw name without any status indicators
     *
     * @param PlayerData $playerData The player to decorate
     * @return string Player name
     */
    public function decoratePlayerName(PlayerData $playerData): string;

    /**
     * Get the CSS class for a player's contract status indicator
     *
     * Status classes drive ::after pseudo-element indicators via CSS:
     * - "player-waived" — ordinal > JSB::WAIVERS_ORDINAL (shows * via CSS)
     * - "player-expiring" — contractCurrentYear == contractTotalYears (shows ^ via CSS)
     * - "" — no indicator
     *
     * @param PlayerData $playerData The player to check
     * @return string CSS class name, or empty string
     */
    public function getNameStatusClass(PlayerData $playerData): string;
}
