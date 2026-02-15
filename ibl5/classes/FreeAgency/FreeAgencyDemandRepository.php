<?php

declare(strict_types=1);

namespace FreeAgency;

use BaseMysqliRepository;
use FreeAgency\Contracts\FreeAgencyDemandRepositoryInterface;

/**
 * FreeAgencyDemandRepository - Database operations for free agency demand calculations
 *
 * Extends BaseMysqliRepository for standardized prepared statement handling.
 * Centralizes all database queries for the FreeAgency demand calculation module.
 *
 * @see FreeAgencyDemandRepositoryInterface For method contracts
 * @see BaseMysqliRepository For base class documentation and error codes
 *
 * @phpstan-import-type TeamPerformanceRow from \FreeAgency\Contracts\FreeAgencyDemandRepositoryInterface
 * @phpstan-import-type PlayerDemandsRow from \FreeAgency\Contracts\FreeAgencyDemandRepositoryInterface
 */
class FreeAgencyDemandRepository extends BaseMysqliRepository implements FreeAgencyDemandRepositoryInterface
{
    /**
     * Constructor - inherits from BaseMysqliRepository
     *
     * @param \mysqli $db Active mysqli connection
     * @throws \RuntimeException If connection is invalid (error code 1002)
     */
    public function __construct(\mysqli $db)
    {
        parent::__construct($db);
    }

    /**
     * @see FreeAgencyDemandRepositoryInterface::getTeamPerformance()
     *
     * @return TeamPerformanceRow
     */
    public function getTeamPerformance(string $teamName): array
    {
        /** @var array{Contract_Wins: int, Contract_Losses: int, Contract_AvgW: int, Contract_AvgL: int}|null $result */
        $result = $this->fetchOne(
            "SELECT Contract_Wins, Contract_Losses, Contract_AvgW, Contract_AvgL
             FROM ibl_team_info
             WHERE team_name = ?",
            "s",
            $teamName
        );

        if ($result === null) {
            return ['wins' => 0, 'losses' => 0, 'tradWins' => 0, 'tradLosses' => 0];
        }

        return [
            'wins' => $result['Contract_Wins'],
            'losses' => $result['Contract_Losses'],
            'tradWins' => $result['Contract_AvgW'],
            'tradLosses' => $result['Contract_AvgL'],
        ];
    }

    /**
     * @see FreeAgencyDemandRepositoryInterface::getPositionSalaryCommitment()
     *
     * @return int Total salary committed to the position
     */
    public function getPositionSalaryCommitment(string $teamName, string $position, int $excludePlayerID): int
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
     * @see FreeAgencyDemandRepositoryInterface::getPlayerDemands()
     *
     * @return PlayerDemandsRow
     */
    public function getPlayerDemands(string $playerName): array
    {
        /** @var array{dem1: int, dem2: int, dem3: int, dem4: int, dem5: int, dem6: int}|null $result */
        $result = $this->fetchOne(
            "SELECT dem1, dem2, dem3, dem4, dem5, dem6
             FROM ibl_demands
             WHERE name = ?",
            "s",
            $playerName
        );

        if ($result === null) {
            return [
                'dem1' => 0,
                'dem2' => 0,
                'dem3' => 0,
                'dem4' => 0,
                'dem5' => 0,
                'dem6' => 0,
            ];
        }

        return [
            'dem1' => $result['dem1'],
            'dem2' => $result['dem2'],
            'dem3' => $result['dem3'],
            'dem4' => $result['dem4'],
            'dem5' => $result['dem5'],
            'dem6' => $result['dem6'],
        ];
    }
}
