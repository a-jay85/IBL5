<?php

declare(strict_types=1);

namespace LeagueControlPanel\Contracts;

interface LeagueControlPanelServiceInterface
{
    /**
     * Get all panel data needed for the control panel form
     *
     * @return array{phase: string, allowTrades: string, allowWaivers: string, showDraftLink: string, freeAgencyNotifications: string, triviaMode: string, simLengthInDays: int, seasonEndingYear: int}
     */
    public function getPanelData(): array;
}
