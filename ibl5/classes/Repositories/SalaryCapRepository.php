<?php

declare(strict_types=1);

namespace Repositories;

use League\League;
use Repositories\Contracts\SalaryCapRepositoryInterface;

class SalaryCapRepository extends \BaseMysqliRepository implements SalaryCapRepositoryInterface
{
    public function getTeamTotalSalary(string $teamName): int
    {
        /** @var array{total_salary: int|null}|null $result */
        $result = $this->fetchOne(
            "SELECT SUM(current_salary) AS total_salary
            FROM vw_current_salary
            WHERE teamname = ?",
            "s",
            $teamName
        );

        return (int) ($result['total_salary'] ?? 0);
    }

    public function getPlayerCurrentSalary(int $playerId): int
    {
        /** @var array{current_salary: int|null}|null $result */
        $result = $this->fetchOne(
            "SELECT current_salary
            FROM vw_current_salary
            WHERE pid = ?",
            "i",
            $playerId
        );

        return (int) ($result['current_salary'] ?? 0);
    }

    public function getTeamNextYearSalary(string $teamName): int
    {
        /** @var array{total_salary: int|null}|null $result */
        $result = $this->fetchOne(
            "SELECT SUM(next_year_salary) AS total_salary
            FROM vw_current_salary
            WHERE teamname = ?",
            "s",
            $teamName
        );

        return (int) ($result['total_salary'] ?? 0);
    }

    public function getPositionSalaryCommitmentNextYear(string $teamName, string $position, int $excludePlayerID): int
    {
        /** @var array{total_salary: int|null}|null $result */
        $result = $this->fetchOne(
            "SELECT SUM(next_year_salary) AS total_salary
            FROM vw_current_salary
            WHERE teamname = ?
              AND pos = ?
              AND pid != ?",
            "ssi",
            $teamName,
            $position,
            $excludePlayerID
        );

        return (int) ($result['total_salary'] ?? 0);
    }

    /**
     * @return array{current: int, nextYear: int}
     */
    public function getTeamSalarySummary(string $teamName): array
    {
        /** @var array{current_salary: int|null, next_year_salary: int|null}|null $result */
        $result = $this->fetchOne(
            "SELECT SUM(current_salary) AS current_salary, SUM(next_year_salary) AS next_year_salary
            FROM vw_current_salary
            WHERE teamname = ?",
            "s",
            $teamName
        );

        return [
            'current' => (int) ($result['current_salary'] ?? 0),
            'nextYear' => (int) ($result['next_year_salary'] ?? 0),
        ];
    }

    public function getTeamCapSpaceNextSeason(string $teamName): int
    {
        return League::HARD_CAP_MAX - $this->getTeamNextYearSalary($teamName);
    }
}
