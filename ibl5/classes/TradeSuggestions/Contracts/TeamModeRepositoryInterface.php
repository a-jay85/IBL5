<?php

declare(strict_types=1);

namespace TradeSuggestions\Contracts;

use TradeSuggestions\TeamModeSetting;

interface TeamModeRepositoryInterface
{
    /**
     * Read the stored mode setting for a team. A missing team row or an
     * unknown stored value reads as Auto.
     */
    public function getStoredSetting(int $tid): TeamModeSetting;

    /**
     * Stored mode settings for every team, keyed by teamid, in one query.
     * Unknown stored values read as Auto.
     *
     * @return array<int, TeamModeSetting>
     */
    public function getAllStoredSettings(): array;

    /**
     * Persist the mode setting for exactly one team.
     */
    public function saveSetting(int $tid, TeamModeSetting $setting): void;

    /**
     * Inputs for the win-now/rebuild guess: the team's current record and
     * the average age of its active roster.
     *
     * @return array{wins: int, losses: int, avgAge: float}
     */
    public function getModeInputs(int $tid): array;
}
