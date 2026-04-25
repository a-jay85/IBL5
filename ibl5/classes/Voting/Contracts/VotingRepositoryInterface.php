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
 *     mvp_1: string, mvp_2: string, mvp_3: string,
 *     six_1: string, six_2: string, six_3: string,
 *     roy_1: string, roy_2: string, roy_3: string,
 *     gm_1: string, gm_2: string, gm_3: string
 * }
 *
 * @phpstan-type AsgBallot array{
 *     east_f1: string, east_f2: string, east_f3: string, east_f4: string,
 *     east_b1: string, east_b2: string, east_b3: string, east_b4: string,
 *     west_f1: string, west_f2: string, west_f3: string, west_f4: string,
 *     west_b1: string, west_b2: string, west_b3: string, west_b4: string
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
     * @param list<string> $columns Ballot column names (e.g., ['east_f1', 'east_f2', ...])
     * @return list<VoteRow> Sorted by votes DESC, name ASC; blank entries labeled
     */
    public function fetchAllStarTotals(array $columns): array;

    /**
     * Fetch aggregated end-of-year vote totals with weighted scoring
     *
     * Builds a UNION ALL with per-column weights, groups by player name,
     * sums weighted scores, and resolves player IDs.
     *
     * @param array<string, int> $columnsWithWeights Column name => point value (e.g., ['mvp_1' => 3, 'mvp_2' => 2, 'mvp_3' => 1])
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
