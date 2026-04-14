<?php

declare(strict_types=1);

namespace HeadToHeadRecords\Contracts;

/**
 * @phpstan-type Dimension 'active_teams'|'all_time_teams'|'gms'
 * @phpstan-type Phase 'regular'|'playoffs'|'heat'|'all'
 * @phpstan-type Scope 'current'|'all_time'
 * @phpstan-type AxisEntry array{key: string|int, label: string, logo: string, franchise_id: int}
 * @phpstan-type MatchupRecord array{wins: int, losses: int}
 * @phpstan-type MatrixPayload array{axis: list<AxisEntry>, matrix: array<string|int, array<string|int, MatchupRecord>>}
 */
interface HeadToHeadRecordsRepositoryInterface
{
    /**
     * Get the head-to-head matrix for a given scope, dimension, and phase.
     *
     * @param Scope $scope
     * @param Dimension $dimension
     * @param Phase $phase
     * @param int $currentSeasonYear
     * @return MatrixPayload
     */
    public function getMatrix(string $scope, string $dimension, string $phase, int $currentSeasonYear): array;

    /**
     * Get current-season head-to-head pairs for active teams (all phases).
     * Used by Standings for H2H tie-breaking.
     *
     * @return list<array{self: int, opponent: int, wins: int, losses: int}>
     */
    public function getPairsForActiveTeams(int $currentSeasonYear): array;
}
