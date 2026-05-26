<?php

declare(strict_types=1);

namespace LeagueControlPanel;

use League\LeagueContext;
use LeagueControlPanel\Contracts\LeagueControlPanelRepositoryInterface;
use LeagueControlPanel\Contracts\LeagueControlPanelServiceInterface;

/**
 * @see LeagueControlPanelServiceInterface
 */
class LeagueControlPanelService implements LeagueControlPanelServiceInterface
{
    private LeagueControlPanelRepositoryInterface $repository;
    private string $league;

    public function __construct(LeagueControlPanelRepositoryInterface $repository, string $league = LeagueContext::LEAGUE_IBL)
    {
        $this->repository = $repository;
        $this->league = $league;
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
            'Current Season Ending Year',
        ]);

        $simLengthInDays = $this->repository->getSimLengthInDays();

        $seasonEndingYear = (int) ($settings['Current Season Ending Year'] ?? '0');

        $isOlympics = $this->league === LeagueContext::LEAGUE_OLYMPICS;

        return [
            'phase' => $settings['Current Season Phase'] ?? 'Preseason',
            'allowTrades' => $settings['Allow Trades'] ?? 'No',
            'allowWaivers' => $settings['Allow Waiver Moves'] ?? 'No',
            'showDraftLink' => $settings['Show Draft Link'] ?? 'Off',
            'freeAgencyNotifications' => $settings['Free Agency Notifications'] ?? 'Off',
            'triviaMode' => $settings['Trivia Mode'] ?? 'Off',
            'simLengthInDays' => $simLengthInDays,
            'seasonEndingYear' => $seasonEndingYear,
            'hasFinalsMvp' => $isOlympics ? false : $this->repository->hasFinalsMvp($seasonEndingYear),
        ];
    }
}
