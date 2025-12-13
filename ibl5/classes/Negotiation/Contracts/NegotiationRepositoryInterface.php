<?php

declare(strict_types=1);

namespace Negotiation\Contracts;

/**
 * NegotiationRepositoryInterface - Contract for negotiation database operations
 * 
 * Defines all database query methods needed for the negotiation system.
 * Implementations should extend BaseMysqliRepository.
 */
interface NegotiationRepositoryInterface
{
    /**
     * Get team contract performance data
     * 
     * @param string $teamName Team name
     * @return array{Contract_Wins: int, Contract_Losses: int, Contract_AvgW: int, Contract_AvgL: int}
     */
    public function getTeamPerformance(string $teamName): array;

    /**
     * Get total salary committed to a position for next season
     * 
     * @param string $teamName Team name
     * @param string $position Player position
     * @param string $excludePlayerName Player name to exclude from calculation
     * @return int Total salary committed to the position
     */
    public function getPositionSalaryCommitment(string $teamName, string $position, string $excludePlayerName): int;

    /**
     * Calculate available cap space for next season
     * 
     * @param string $teamName Team name
     * @return int Available cap space
     */
    public function getTeamCapSpaceNextSeason(string $teamName): int;
}
