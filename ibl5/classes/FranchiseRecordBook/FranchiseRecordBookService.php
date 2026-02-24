<?php

declare(strict_types=1);

namespace FranchiseRecordBook;

use FranchiseRecordBook\Contracts\FranchiseRecordBookRepositoryInterface;
use FranchiseRecordBook\Contracts\FranchiseRecordBookServiceInterface;

/**
 * Service for franchise record book business logic.
 *
 * Groups raw database records by stat category for display.
 *
 * @phpstan-import-type AlltimeRecord from FranchiseRecordBookRepositoryInterface
 * @phpstan-import-type TeamInfo from FranchiseRecordBookRepositoryInterface
 * @phpstan-import-type RecordsByCategory from FranchiseRecordBookServiceInterface
 * @phpstan-import-type RecordBookData from FranchiseRecordBookServiceInterface
 */
class FranchiseRecordBookService implements FranchiseRecordBookServiceInterface
{
    private FranchiseRecordBookRepositoryInterface $repository;

    /** Display order for single-season stat categories */
    private const SINGLE_SEASON_STAT_ORDER = ['ppg', 'rpg', 'apg', 'spg', 'bpg', 'fg_pct', 'ft_pct', 'three_pct'];

    /** Display order for career stat categories */
    private const CAREER_STAT_ORDER = ['pts', 'trb', 'ast', 'stl', 'blk', 'fg_pct', 'ft_pct', 'three_pct'];

    /**
     * Human-readable stat category names.
     *
     * @var array<string, string>
     */
    public const STAT_LABELS = [
        'ppg' => 'Points Per Game',
        'rpg' => 'Rebounds Per Game',
        'apg' => 'Assists Per Game',
        'spg' => 'Steals Per Game',
        'bpg' => 'Blocks Per Game',
        'fg_pct' => 'Field Goal %',
        'ft_pct' => 'Free Throw %',
        'three_pct' => 'Three-Point %',
        'pts' => 'Total Points',
        'trb' => 'Total Rebounds',
        'ast' => 'Total Assists',
        'stl' => 'Total Steals',
        'blk' => 'Total Blocks',
    ];

    /**
     * Abbreviated stat category labels for table headers.
     *
     * @var array<string, string>
     */
    public const STAT_ABBREV = [
        'ppg' => 'PPG',
        'rpg' => 'RPG',
        'apg' => 'APG',
        'spg' => 'SPG',
        'bpg' => 'BPG',
        'fg_pct' => 'FG%',
        'ft_pct' => 'FT%',
        'three_pct' => '3P%',
        'pts' => 'PTS',
        'trb' => 'TRB',
        'ast' => 'AST',
        'stl' => 'STL',
        'blk' => 'BLK',
    ];

    public function __construct(FranchiseRecordBookRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see FranchiseRecordBookServiceInterface::getTeamRecordBook()
     *
     * @return RecordBookData
     */
    public function getTeamRecordBook(int $teamId): array
    {
        $singleSeasonRecords = $this->repository->getTeamSingleSeasonRecords($teamId);
        $careerRecords = $this->repository->getLeagueCareerRecords();
        $team = $this->repository->getTeamInfo($teamId);
        $teams = $this->repository->getAllTeams();

        return [
            'singleSeason' => $this->groupByCategory($singleSeasonRecords, self::SINGLE_SEASON_STAT_ORDER),
            'career' => $this->groupByCategory($careerRecords, self::CAREER_STAT_ORDER),
            'team' => $team,
            'teams' => $teams,
            'scope' => 'team',
        ];
    }

    /**
     * @see FranchiseRecordBookServiceInterface::getLeagueRecordBook()
     *
     * @return RecordBookData
     */
    public function getLeagueRecordBook(): array
    {
        $singleSeasonRecords = $this->repository->getLeagueSingleSeasonRecords();
        $careerRecords = $this->repository->getLeagueCareerRecords();
        $teams = $this->repository->getAllTeams();

        return [
            'singleSeason' => $this->groupByCategory($singleSeasonRecords, self::SINGLE_SEASON_STAT_ORDER),
            'career' => $this->groupByCategory($careerRecords, self::CAREER_STAT_ORDER),
            'team' => null,
            'teams' => $teams,
            'scope' => 'league',
        ];
    }

    /**
     * Group records by stat category in display order.
     *
     * @param list<AlltimeRecord> $records
     * @param list<string> $categoryOrder
     * @return RecordsByCategory
     */
    private function groupByCategory(array $records, array $categoryOrder): array
    {
        /** @var RecordsByCategory $grouped */
        $grouped = [];

        // Initialize categories in display order
        foreach ($categoryOrder as $category) {
            $grouped[$category] = [];
        }

        foreach ($records as $record) {
            $category = $record['stat_category'];
            if (array_key_exists($category, $grouped)) {
                $grouped[$category][] = $record;
            }
        }

        return $grouped;
    }
}
