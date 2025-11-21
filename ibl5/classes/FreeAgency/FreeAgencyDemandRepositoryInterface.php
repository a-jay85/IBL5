<?php

namespace FreeAgency;

/**
 * Interface for retrieving free agency demand-related data from the database
 */
interface FreeAgencyDemandRepositoryInterface
{
    /**
     * Get team contract performance data
     * 
     * @param string $teamName Team name
     * @return array{wins: int, losses: int, tradWins: int, tradLosses: int}
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
     * @param string $playerName Player name
     * @return array{dem1: int, dem2: int, dem3: int, dem4: int, dem5: int, dem6: int}
     */
    public function getPlayerDemands(string $playerName): array;
}
