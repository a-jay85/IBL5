<?php

declare(strict_types=1);

namespace Player;

/**
 * PlayerPageService - Business logic for player page actions and visibility
 * 
 * Handles business rules for determining which action buttons should be displayed
 * and whether specific actions are available to the current user.
 */
class PlayerPageService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Determine if renegotiation button should be shown
     * 
     * @param Player $player The player being viewed
     * @param object $userTeam The viewing user's team
     * @param object $season Current season information
     * @return bool True if renegotiation button should be displayed
     */
    public function canShowRenegotiationButton(Player $player, object $userTeam, object $season): bool
    {
        if ($player->wasRookieOptioned()) {
            return false;
        }

        return $userTeam->name != "Free Agents"
            && $userTeam->hasUsedExtensionThisSeason == 0
            && $player->canRenegotiateContract()
            && $player->teamName == $userTeam->name
            && $season->phase != 'Draft'
            && $season->phase != 'Free Agency';
    }

    /**
     * Determine if rookie option used message should be shown
     * 
     * @param Player $player The player being viewed
     * @return bool True if message should be displayed
     */
    public function shouldShowRookieOptionUsedMessage(Player $player): bool
    {
        return $player->wasRookieOptioned();
    }

    /**
     * Determine if rookie option button should be shown
     * 
     * @param Player $player The player being viewed
     * @param object $userTeam The viewing user's team
     * @param object $season Current season information
     * @return bool True if rookie option button should be displayed
     */
    public function canShowRookieOptionButton(Player $player, object $userTeam, object $season): bool
    {
        return $userTeam->name != "Free Agents"
            && $player->canRookieOption($season->phase)
            && $player->teamName == $userTeam->name;
    }
}
