<?php

declare(strict_types=1);

namespace LeagueControlPanel;

use LeagueControlPanel\Contracts\LeagueControlPanelRepositoryInterface;
use LeagueControlPanel\Contracts\LeagueControlPanelServiceInterface;

/**
 * @see LeagueControlPanelServiceInterface
 */
class LeagueControlPanelService implements LeagueControlPanelServiceInterface
{
    private LeagueControlPanelRepositoryInterface $repository;

    public function __construct(LeagueControlPanelRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see LeagueControlPanelServiceInterface::getPanelData()
     */
    public function getPanelData(): array
    {
        $settings = $this->repository->getBulkSettings([
            'Current Season Phase',
            'Allow Trades',
            'Allow Waiver Moves',
            'Show Draft Link',
            'Free Agency Notifications',
            'Trivia Mode',
            'Season Ending Year',
        ]);

        $simLengthInDays = $this->repository->getSimLengthInDays();

        return [
            'phase' => $settings['Current Season Phase'] ?? 'Preseason',
            'allowTrades' => $settings['Allow Trades'] ?? 'No',
            'allowWaivers' => $settings['Allow Waiver Moves'] ?? 'No',
            'showDraftLink' => $settings['Show Draft Link'] ?? 'Off',
            'freeAgencyNotifications' => $settings['Free Agency Notifications'] ?? 'Off',
            'triviaMode' => $settings['Trivia Mode'] ?? 'Off',
            'simLengthInDays' => $simLengthInDays,
            'seasonEndingYear' => (int) ($settings['Season Ending Year'] ?? '0'),
        ];
    }
}
