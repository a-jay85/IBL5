<?php

declare(strict_types=1);

namespace Repositories\Contracts;

interface SalaryCapRepositoryInterface
{
    public function getTeamTotalSalary(string $teamName): int;

    /**
     * Current-season salary for a single player (or cash-consideration row).
     *
     * Reads the same `current_salary` basis as {@see self::getTeamTotalSalary()}
     * from vw_current_salary, keyed by pid, so post-trade cap math
     * (post = current - sent + received) stays internally consistent.
     */
    public function getPlayerCurrentSalary(int $playerId): int;

    public function getTeamNextYearSalary(string $teamName): int;

    public function getPositionSalaryCommitmentNextYear(string $teamName, string $position, int $excludePlayerID): int;

    /**
     * @return array{current: int, nextYear: int}
     */
    public function getTeamSalarySummary(string $teamName): array;

    public function getTeamCapSpaceNextSeason(string $teamName): int;
}
