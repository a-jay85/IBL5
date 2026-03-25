<?php

declare(strict_types=1);

namespace Voting\Contracts;

/**
 * VotingRepositoryInterface — Database access for all voting operations
 *
 * Consolidates read and write queries for both All-Star and End-of-Year voting.
 *
 * @phpstan-import-type VoteRow from VotingResultsServiceInterface
 *
 * @phpstan-type EoyBallot array{
 *     MVP_1: string, MVP_2: string, MVP_3: string,
 *     Six_1: string, Six_2: string, Six_3: string,
 *     ROY_1: string, ROY_2: string, ROY_3: string,
 *     GM_1: string, GM_2: string, GM_3: string
 * }
 *
 * @phpstan-type AsgBallot array{
 *     East_F1: string, East_F2: string, East_F3: string, East_F4: string,
 *     East_B1: string, East_B2: string, East_B3: string, East_B4: string,
 *     West_F1: string, West_F2: string, West_F3: string, West_F4: string,
 *     West_B1: string, West_B2: string, West_B3: string, West_B4: string
 * }
 */
interface VotingRepositoryInterface
{
    // ==================== Write Methods ====================

    /**
     * Save end-of-year votes for a team
     *
     * Updates the team's row in ibl_votes_EOY with 12 ballot fields.
     *
     * @param string $teamName Team name (matches ibl_votes_EOY.team_name)
     * @param EoyBallot $ballot All 12 ballot selections
     */
    public function saveEoyVote(string $teamName, array $ballot): void;

    /**
     * Save All-Star votes for a team
     *
     * Updates the team's row in ibl_votes_ASG with 16 ballot fields.
     *
     * @param string $teamName Team name (matches ibl_votes_ASG.team_name)
     * @param AsgBallot $ballot All 16 ballot selections
     */
    public function saveAsgVote(string $teamName, array $ballot): void;

    /**
     * Record that a team has cast their end-of-year vote
     *
     * Sets ibl_team_info.eoy_vote to current timestamp.
     */
    public function markEoyVoteCast(string $teamName): void;

    /**
     * Record that a team has cast their All-Star vote
     *
     * Sets ibl_team_info.asg_vote to current timestamp.
     */
    public function markAsgVoteCast(string $teamName): void;

    // ==================== Read Methods ====================

    /**
     * Fetch aggregated All-Star vote totals for a set of ballot columns
     *
     * Builds a UNION ALL across the given columns, groups by player name,
     * counts votes (unweighted), and resolves player IDs.
     *
     * @param list<string> $columns Ballot column names (e.g., ['East_F1', 'East_F2', ...])
     * @return list<VoteRow> Sorted by votes DESC, name ASC; blank entries labeled
     */
    public function fetchAllStarTotals(array $columns): array;

    /**
     * Fetch aggregated end-of-year vote totals with weighted scoring
     *
     * Builds a UNION ALL with per-column weights, groups by player name,
     * sums weighted scores, and resolves player IDs.
     *
     * @param array<string, int> $columnsWithWeights Column name => point value (e.g., ['MVP_1' => 3, 'MVP_2' => 2, 'MVP_3' => 1])
     * @return list<VoteRow> Sorted by votes DESC, name ASC; blank entries labeled
     */
    public function fetchEndOfYearTotals(array $columnsWithWeights): array;

    /**
     * Batch-resolve player IDs from player names
     *
     * @param list<string> $names Player names to look up
     * @return array<string, int> Map of player name => pid (missing names omitted)
     */
    public function fetchPlayerIdsByNames(array $names): array;
}
