<?php

declare(strict_types=1);

namespace SeasonHighs;

use SeasonHighs\Contracts\SeasonHighsServiceInterface;
use SeasonHighs\Contracts\SeasonHighsRepositoryInterface;

/**
 * SeasonHighsService - Business logic for season highs
 *
 * Calculates date ranges and retrieves season high stats.
 *
 * @phpstan-import-type SeasonHighsData from SeasonHighsServiceInterface
 * @phpstan-import-type RcbSeasonHighEntry from SeasonHighsServiceInterface
 *
 * @see SeasonHighsServiceInterface For the interface contract
 */
class SeasonHighsService implements SeasonHighsServiceInterface
{
    private SeasonHighsRepositoryInterface $repository;
    private \Season $season;

    /**
     * Stat definitions with SQL expressions and display names.
     *
     * @var array<string, string>
     */
    private const STATS = [
        'POINTS' => '(`game2GM`*2) + `gameFTM` + (`game3GM`*3)',
        'REBOUNDS' => '(`gameORB` + `gameDRB`)',
        'ASSISTS' => '`gameAST`',
        'STEALS' => '`gameSTL`',
        'BLOCKS' => '`gameBLK`',
        'TURNOVERS' => '`gameTOV`',
        'Field Goals Made' => '(`game2GM` + `game3GM`)',
        'Free Throws Made' => '`gameFTM`',
        'Three Pointers Made' => '`game3GM`',
    ];

    public function __construct(SeasonHighsRepositoryInterface $repository, \Season $season)
    {
        $this->repository = $repository;
        $this->season = $season;
    }

    /**
     * @see SeasonHighsServiceInterface::getSeasonHighsData()
     *
     * @return SeasonHighsData
     */
    public function getSeasonHighsData(string $seasonPhase): array
    {
        $dateRange = $this->getDateRangeForPhase($seasonPhase);

        $playerHighs = [];
        $teamHighs = [];

        foreach (self::STATS as $statName => $statExpression) {
            $playerHighs[$statName] = $this->repository->getSeasonHighs(
                $statExpression,
                $statName,
                '',
                $dateRange['start'],
                $dateRange['end']
            );

            $teamHighs[$statName] = $this->repository->getSeasonHighs(
                $statExpression,
                $statName,
                '_teams',
                $dateRange['start'],
                $dateRange['end']
            );
        }

        return [
            'playerHighs' => $playerHighs,
            'teamHighs' => $teamHighs,
        ];
    }

    /**
     * RCB stat category display labels.
     *
     * @var array<string, string>
     */
    private const RCB_STAT_LABELS = [
        'pts' => 'Points',
        'reb' => 'Rebounds',
        'ast' => 'Assists',
        'stl' => 'Steals',
        'blk' => 'Blocks',
        'two_gm' => 'Field Goals Made',
        'three_gm' => 'Three Pointers Made',
        'ftm' => 'Free Throws Made',
    ];

    /**
     * Display order for RCB stat categories.
     *
     * @var list<string>
     */
    private const RCB_STAT_ORDER = ['pts', 'reb', 'ast', 'stl', 'blk', 'two_gm', 'three_gm', 'ftm'];

    /**
     * @see SeasonHighsServiceInterface::getHomeAwayHighs()
     *
     * @return array{home: array<string, list<RcbSeasonHighEntry>>, away: array<string, list<RcbSeasonHighEntry>>}
     */
    public function getHomeAwayHighs(): array
    {
        $seasonYear = $this->season->beginningYear;

        $homeRecords = $this->repository->getRcbSeasonHighs($seasonYear, 'home');
        $awayRecords = $this->repository->getRcbSeasonHighs($seasonYear, 'away');

        return [
            'home' => $this->groupRcbByCategory($homeRecords),
            'away' => $this->groupRcbByCategory($awayRecords),
        ];
    }

    /**
     * Group RCB season records by stat category in display order.
     *
     * @param list<RcbSeasonHighEntry> $records
     * @return array<string, list<RcbSeasonHighEntry>>
     */
    private function groupRcbByCategory(array $records): array
    {
        /** @var array<string, list<RcbSeasonHighEntry>> $grouped */
        $grouped = [];
        foreach (self::RCB_STAT_ORDER as $category) {
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

    /**
     * Get the display label for an RCB stat category.
     */
    public static function getRcbStatLabel(string $category): string
    {
        return self::RCB_STAT_LABELS[$category] ?? $category;
    }

    /**
     * Get date range for a season phase.
     *
     * @param string $seasonPhase Season phase
     * @return array{start: string, end: string} Date range
     */
    private function getDateRangeForPhase(string $seasonPhase): array
    {
        $beginningYear = $this->season->beginningYear;
        $endingYear = $this->season->endingYear;

        switch ($seasonPhase) {
            case 'Playoffs':
                return [
                    'start' => sprintf('%d-%02d-01', $endingYear, \Season::IBL_PLAYOFF_MONTH),
                    'end' => sprintf('%d-%02d-30', $endingYear, \Season::IBL_PLAYOFF_MONTH),
                ];

            case 'Preseason':
                return [
                    'start' => sprintf('%d-%02d-01', \Season::IBL_PRESEASON_YEAR, \Season::IBL_REGULAR_SEASON_STARTING_MONTH),
                    'end' => sprintf('%d-%02d-30', \Season::IBL_PRESEASON_YEAR + 1, \Season::IBL_REGULAR_SEASON_ENDING_MONTH),
                ];

            case 'HEAT':
                return [
                    'start' => sprintf('%d-%02d-01', $beginningYear, \Season::IBL_HEAT_MONTH),
                    'end' => sprintf('%d-%02d-30', $beginningYear, \Season::IBL_HEAT_MONTH),
                ];

            default: // Regular Season
                return [
                    'start' => sprintf('%d-%02d-01', $beginningYear, \Season::IBL_REGULAR_SEASON_STARTING_MONTH),
                    'end' => sprintf('%d-%02d-30', $endingYear, \Season::IBL_REGULAR_SEASON_ENDING_MONTH),
                ];
        }
    }
}
