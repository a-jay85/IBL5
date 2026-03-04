<?php

declare(strict_types=1);

namespace LeagueControlPanel\Contracts;

interface LeagueControlPanelViewInterface
{
    /**
     * Render the control panel HTML
     *
     * @param array{short_name: string, full_name: string} $leagueConfig Current league configuration
     * @param string $currentLeague Current league key ('ibl' or 'olympics')
     * @param array{phase: string, allowTrades: string, allowWaivers: string, showDraftLink: string, freeAgencyNotifications: string, triviaMode: string, simLengthInDays: int, seasonEndingYear: int} $panelData Panel data from service
     * @param string|null $resultMessage Flash message to display
     * @param bool $resultSuccess Whether the flash message is a success
     * @return string Rendered HTML
     */
    public function render(array $leagueConfig, string $currentLeague, array $panelData, ?string $resultMessage, bool $resultSuccess): string;
}
