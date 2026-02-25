<?php

declare(strict_types=1);

namespace FreeAgency\Contracts;

/**
 * Interface for retrieving free agency demand-related data from the database
 *
 * @phpstan-type TeamPerformanceRow array{wins: int, losses: int, tradWins: int, tradLosses: int}
 * @phpstan-type PlayerDemandsRow array{dem1: int, dem2: int, dem3: int, dem4: int, dem5: int, dem6: int}
 * @phpstan-type ContractYearRow array{cy: ?int, cy1: ?int, cy2: ?int, cy3: ?int, cy4: ?int, cy5: ?int, cy6: ?int}
 */
interface FreeAgencyDemandRepositoryInterface
{
    /**
     * Get team contract performance data
     *
     * @param string $teamName Team name
     * @return TeamPerformanceRow
     */
    public function getTeamPerformance(string $teamName): array;

    /**
     * Get total salary committed to a specific position on a team
     *
     * @param string $teamName Team name
     * @param string $position Player position
     * @param int $excludePlayerID Player ID to exclude from calculation
     * @return int Total salary committed to the position
     */
    public function getPositionSalaryCommitment(string $teamName, string $position, int $excludePlayerID): int;

    /**
     * Get player contract demands
     *
     * @param int $playerID Player ID
     * @return PlayerDemandsRow
     */
    public function getPlayerDemands(int $playerID): array;
}
