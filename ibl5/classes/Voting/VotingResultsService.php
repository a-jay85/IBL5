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
        'Eastern Conference Frontcourt' => ['east_f1', 'east_f2', 'east_f3', 'east_f4'],
        'Eastern Conference Backcourt' => ['east_b1', 'east_b2', 'east_b3', 'east_b4'],
        'Western Conference Frontcourt' => ['west_f1', 'west_f2', 'west_f3', 'west_f4'],
        'Western Conference Backcourt' => ['west_b1', 'west_b2', 'west_b3', 'west_b4'],
    ];

    /** @var array<string, array<string, int>> */
    private const END_OF_YEAR_CATEGORIES = [
        'Most Valuable Player' => ['mvp_1' => 3, 'mvp_2' => 2, 'mvp_3' => 1],
        'Sixth Man of the Year' => ['six_1' => 3, 'six_2' => 2, 'six_3' => 1],
        'Rookie of the Year' => ['roy_1' => 3, 'roy_2' => 2, 'roy_3' => 1],
        'GM of the Year' => ['gm_1' => 3, 'gm_2' => 2, 'gm_3' => 1],
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
