<?php

namespace Player\Contracts;

use Player\Player;

/**
 * PlayerPageServiceInterface - Contract for player page business logic
 * 
 * Defines the interface for determining which action buttons and messages
 * should be displayed on the player page based on eligibility rules.
 */
interface PlayerPageServiceInterface
{
    /**
     * Determine if renegotiation button should be shown
     * 
     * The renegotiation button is displayed only if ALL conditions are met:
     * - Player was NOT rookie optioned (wasRookieOptioned() returns false)
     * - Viewing user is NOT looking at Free Agents team
     * - Viewing user has NOT already used extension this season
     * - Player is eligible for renegotiation (canRenegotiateContract() returns true)
     * - Player is on the viewing user's team (teamName matches)
     * - Season phase is NOT "Draft" or "Free Agency"
     * 
     * @param Player $player The player being viewed
     * @param object $userTeam The viewing user's team object with name and hasUsedExtensionThisSeason properties
     * @param object $season Current season with phase property
     * @return bool True if renegotiation button should be displayed
     */
    public function canShowRenegotiationButton(Player $player, object $userTeam, object $season): bool;

    /**
     * Determine if rookie option used message should be shown
     * 
     * The message "ROOKIE OPTION USED; RENEGOTIATION IMPOSSIBLE" is displayed
     * when the player's rookie option has been previously exercised.
     * 
     * @param Player $player The player being viewed
     * @return bool True if rookie option used message should be displayed
     */
    public function shouldShowRookieOptionUsedMessage(Player $player): bool;

    /**
     * Determine if rookie option button should be shown
     * 
     * The rookie option button is displayed only if ALL conditions are met:
     * - Viewing user is NOT looking at Free Agents team
     * - Player is eligible for rookie option (canRookieOption() returns true)
     * - Player is on the viewing user's team (teamName matches)
     * 
     * @param Player $player The player being viewed
     * @param object $userTeam The viewing user's team object with name property
     * @param object $season Current season with phase property
     * @return bool True if rookie option button should be displayed
     */
    public function canShowRookieOptionButton(Player $player, object $userTeam, object $season): bool;
}
