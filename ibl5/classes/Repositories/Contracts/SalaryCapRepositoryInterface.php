<?php

declare(strict_types=1);

namespace Repositories\Contracts;

interface SalaryCapRepositoryInterface
{
    public function getTeamTotalSalary(string $teamName): int;

    public function getTeamNextYearSalary(string $teamName): int;

    public function getPositionSalaryCommitmentNextYear(string $teamName, string $position, int $excludePlayerID): int;

    /**
     * @return array{current: int, nextYear: int}
     */
    public function getTeamSalarySummary(string $teamName): array;

    public function getTeamCapSpaceNextSeason(string $teamName): int;
}
