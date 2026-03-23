<?php

declare(strict_types=1);

namespace Player;

use League\League;
use Player\Contracts\PlayerPageServiceInterface;
use Player\Views\PlayerViewFactory;
use Team\Team;

/**
 * @see PlayerPageServiceInterface
 */
class PlayerPageService implements PlayerPageServiceInterface
{
    private PlayerRepository $repository;
    private PlayerStatsRepository $statsRepository;
    private PlayerViewFactory $viewFactory;

    public function __construct(\mysqli $db)
    {
        $this->repository = new PlayerRepository($db);
        $this->statsRepository = new PlayerStatsRepository($db);
        $this->viewFactory = new PlayerViewFactory($this->repository, $this->statsRepository);
    }

    /**
     * Get the view factory for creating view instances
     * 
     * @return PlayerViewFactory
     */
    public function getViewFactory(): PlayerViewFactory
    {
        return $this->viewFactory;
    }

    /**
     * Get the player repository
     * 
     * @return PlayerRepository
     */
    public function getRepository(): PlayerRepository
    {
        return $this->repository;
    }

    /**
     * @see PlayerPageServiceInterface::canShowRenegotiationButton()
     */
    public function canShowRenegotiationButton(Player $player, object $userTeam, object $season): bool
    {
        if ($player->wasRookieOptioned()) {
            return false;
        }

        /** @var Team $userTeam */
        /** @var \Season $season */
        return $userTeam->name !== League::FREE_AGENTS_TEAM_NAME
            && $userTeam->hasUsedExtensionThisSeason === 0
            && $player->canRenegotiateContract()
            && $player->teamID === $userTeam->teamID
            && $season->phase !== 'Draft'
            && $season->phase !== 'Free Agency';
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
        /** @var Team $userTeam */
        /** @var \Season $season */
        return $userTeam->name !== League::FREE_AGENTS_TEAM_NAME
            && $player->canRookieOption($season->phase)
            && $player->teamID === $userTeam->teamID;
    }
}
