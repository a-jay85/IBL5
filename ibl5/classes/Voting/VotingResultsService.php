<?php

declare(strict_types=1);

namespace Voting;

use Voting\Contracts\VotingRepositoryInterface;
use Voting\Contracts\VotingResultsServiceInterface;

/**
 * VotingResultsService — Assembles voting result tables from repository data
 *
 * Delegates all database access to VotingRepository. Owns the category
 * definitions (which columns belong to which award) and wraps repository
 * results into VoteTable structures for the renderer.
 *
 * @phpstan-import-type VoteRow from VotingResultsServiceInterface
 * @phpstan-import-type VoteTable from VotingResultsServiceInterface
 *
 * @see VotingResultsServiceInterface
 */
class VotingResultsService implements VotingResultsServiceInterface
{
    /** @var array<string, list<string>> */
    private const ALL_STAR_CATEGORIES = [
        'Eastern Conference Frontcourt' => ['East_F1', 'East_F2', 'East_F3', 'East_F4'],
        'Eastern Conference Backcourt' => ['East_B1', 'East_B2', 'East_B3', 'East_B4'],
        'Western Conference Frontcourt' => ['West_F1', 'West_F2', 'West_F3', 'West_F4'],
        'Western Conference Backcourt' => ['West_B1', 'West_B2', 'West_B3', 'West_B4'],
    ];

    /** @var array<string, array<string, int>> */
    private const END_OF_YEAR_CATEGORIES = [
        'Most Valuable Player' => ['MVP_1' => 3, 'MVP_2' => 2, 'MVP_3' => 1],
        'Sixth Man of the Year' => ['Six_1' => 3, 'Six_2' => 2, 'Six_3' => 1],
        'Rookie of the Year' => ['ROY_1' => 3, 'ROY_2' => 2, 'ROY_3' => 1],
        'GM of the Year' => ['GM_1' => 3, 'GM_2' => 2, 'GM_3' => 1],
    ];

    private VotingRepositoryInterface $repository;

    public function __construct(VotingRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see VotingResultsServiceInterface::getAllStarResults()
     *
     * @return list<VoteTable>
     */
    public function getAllStarResults(): array
    {
        $results = [];
        foreach (self::ALL_STAR_CATEGORIES as $title => $columns) {
            $results[] = [
                'title' => $title,
                'rows' => $this->repository->fetchAllStarTotals($columns),
            ];
        }
        return $results;
    }

    /**
     * @see VotingResultsServiceInterface::getEndOfYearResults()
     *
     * @return list<VoteTable>
     */
    public function getEndOfYearResults(): array
    {
        $results = [];
        foreach (self::END_OF_YEAR_CATEGORIES as $title => $ballots) {
            $results[] = [
                'title' => $title,
                'rows' => $this->repository->fetchEndOfYearTotals($ballots),
            ];
        }

        return $results;
    }

    /**
     * Extract the player name from a vote entry like "LeBron James, Sting".
     *
     * Strips the trailing ", TeamName" portion. If there is no comma, returns
     * the full string (handles GM names and other non-player entries).
     */
    public static function extractPlayerName(string $voteName): string
    {
        $lastComma = strrpos($voteName, ',');
        if ($lastComma === false) {
            return trim($voteName);
        }

        return trim(substr($voteName, 0, $lastComma));
    }
}
