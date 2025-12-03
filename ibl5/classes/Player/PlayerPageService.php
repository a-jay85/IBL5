<?php

declare(strict_types=1);

namespace Player;

use Player\Contracts\PlayerPageServiceInterface;

/**
 * @see PlayerPageServiceInterface
 */
class PlayerPageService implements PlayerPageServiceInterface
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * @see PlayerPageServiceInterface::canShowRenegotiationButton()
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
     * @see PlayerPageServiceInterface::shouldShowRookieOptionUsedMessage()
     */
    public function shouldShowRookieOptionUsedMessage(Player $player): bool
    {
        return $player->wasRookieOptioned();
    }

    /**
     * @see PlayerPageServiceInterface::canShowRookieOptionButton()
     */
    public function canShowRookieOptionButton(Player $player, object $userTeam, object $season): bool
    {
        return $userTeam->name != "Free Agents"
            && $player->canRookieOption($season->phase)
            && $player->teamName == $userTeam->name;
    }
}
