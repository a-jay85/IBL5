<?php

declare(strict_types=1);

namespace SeasonHighs;

use SeasonHighs\Contracts\SeasonHighsServiceInterface;
use SeasonHighs\Contracts\SeasonHighsRepositoryInterface;
use Season\Season;

/**
 * SeasonHighsService - Business logic for season highs
 *
 * Calculates date ranges and retrieves season high stats.
 *
 * @phpstan-import-type SeasonHighsData from SeasonHighsServiceInterface
 * @phpstan-import-type SeasonHighEntry from SeasonHighsServiceInterface
 * @phpstan-import-type RcbDiscrepancy from SeasonHighsServiceInterface
 *
 * @see SeasonHighsServiceInterface For the interface contract
 */
class SeasonHighsService implements SeasonHighsServiceInterface
{
    private SeasonHighsRepositoryInterface $repository;
    private Season $season;

    /**
     * Stat definitions with SQL expressions and display names.
     *
     * @var array<string, string>
     */
    private const STATS = [
        'POINTS' => '(`game_2gm`*2) + `game_ftm` + (`game_3gm`*3)',
        'REBOUNDS' => '(`game_orb` + `game_drb`)',
        'ASSISTS' => '`game_ast`',
        'STEALS' => '`game_stl`',
        'BLOCKS' => '`game_blk`',
        'TURNOVERS' => '`game_tov`',
        'Field Goals Made' => '(`game_2gm` + `game_3gm`)',
        'Free Throws Made' => '`game_ftm`',
        'Three Pointers Made' => '`game_3gm`',
    ];

    /**
     * Stats used for home/away highs (no TURNOVERS — RCB doesn't track it).
     *
     * @var array<string, string>
     */
    private const HOME_AWAY_STATS = [
        'POINTS' => '(`game_2gm`*2) + `game_ftm` + (`game_3gm`*3)',
        'REBOUNDS' => '(`game_orb` + `game_drb`)',
        'ASSISTS' => '`game_ast`',
        'STEALS' => '`game_stl`',
        'BLOCKS' => '`game_blk`',
        'Field Goals Made' => '(`game_2gm` + `game_3gm`)',
        'Three Pointers Made' => '`game_3gm`',
        'Free Throws Made' => '`game_ftm`',
    ];

    /**
     * Map RCB stat categories to box score stat names for cross-validation.
     *
     * @var array<string, string>
     */
    private const RCB_TO_STAT_MAP = [
        'pts' => 'POINTS',
        'reb' => 'REBOUNDS',
        'ast' => 'ASSISTS',
        'stl' => 'STEALS',
        'blk' => 'BLOCKS',
        'two_gm' => 'Field Goals Made',
        'three_gm' => 'Three Pointers Made',
        'ftm' => 'Free Throws Made',
    ];

    private const HOME_AWAY_LIMIT = 10;

    public function __construct(SeasonHighsRepositoryInterface $repository, Season $season)
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

        $playerHighs = $this->repository->getSeasonHighsBatch(
            self::STATS,
            '',
            $dateRange['start'],
            $dateRange['end']
        );

        $teamHighs = $this->repository->getSeasonHighsBatch(
            self::STATS,
            '_teams',
            $dateRange['start'],
            $dateRange['end']
        );

        return [
            'playerHighs' => $playerHighs,
            'teamHighs' => $teamHighs,
        ];
    }

    /**
     * @see SeasonHighsServiceInterface::getHomeAwayHighs()
     *
     * @return array{home: array<string, list<SeasonHighEntry>>, away: array<string, list<SeasonHighEntry>>}
     */
    public function getHomeAwayHighs(string $seasonPhase): array
    {
        $dateRange = $this->getDateRangeForPhase($seasonPhase);

        $homeHighs = $this->repository->getSeasonHighsBatch(
            self::HOME_AWAY_STATS,
            '',
            $dateRange['start'],
            $dateRange['end'],
            self::HOME_AWAY_LIMIT,
            'home'
        );

        $awayHighs = $this->repository->getSeasonHighsBatch(
            self::HOME_AWAY_STATS,
            '',
            $dateRange['start'],
            $dateRange['end'],
            self::HOME_AWAY_LIMIT,
            'away'
        );

        return [
            'home' => $homeHighs,
            'away' => $awayHighs,
        ];
    }

    /**
     * @see SeasonHighsServiceInterface::validateAgainstRcb()
     *
     * @param array{home: array<string, list<SeasonHighEntry>>, away: array<string, list<SeasonHighEntry>>} $homeAwayData
     * @return list<RcbDiscrepancy>
     */
    public function validateAgainstRcb(array $homeAwayData, int $seasonYear): array
    {
        /** @var list<RcbDiscrepancy> $discrepancies */
        $discrepancies = [];

        foreach (['home', 'away'] as $context) {
            $rcbRecords = $this->repository->getRcbSeasonHighs($seasonYear, $context);
            if ($rcbRecords === []) {
                continue;
            }

            // Index RCB #1 records by stat category
            /** @var array<string, array{player_name: string, stat_value: int}> $rcbTop */
            $rcbTop = [];
            foreach ($rcbRecords as $record) {
                if ($record['ranking'] === 1 && !array_key_exists($record['stat_category'], $rcbTop)) {
                    $rcbTop[$record['stat_category']] = [
                        'player_name' => $record['player_name'],
                        'stat_value' => $record['stat_value'],
                    ];
                }
            }

            foreach (self::RCB_TO_STAT_MAP as $rcbCategory => $boxStatName) {
                if (!isset($rcbTop[$rcbCategory])) {
                    continue;
                }

                $boxEntries = $homeAwayData[$context][$boxStatName] ?? [];
                if ($boxEntries === []) {
                    continue;
                }

                $boxTop = $boxEntries[0];
                $rcbEntry = $rcbTop[$rcbCategory];

                $valuesMatch = $boxTop['value'] === $rcbEntry['stat_value'];
                $namesMatch = $this->namesMatch($boxTop['name'], $rcbEntry['player_name']);

                if (!$valuesMatch || !$namesMatch) {
                    $discrepancies[] = [
                        'context' => $context,
                        'stat' => $boxStatName,
                        'boxValue' => $boxTop['value'],
                        'boxPlayer' => $boxTop['name'],
                        'rcbValue' => $rcbEntry['stat_value'],
                        'rcbPlayer' => $rcbEntry['player_name'],
                    ];
                }
            }
        }

        return $discrepancies;
    }

    /**
     * Compare player names with tolerance for RCB truncation.
     *
     * RCB names may be truncated compared to full names in ibl_plr.
     * Uses case-insensitive prefix matching.
     */
    private function namesMatch(string $boxName, string $rcbName): bool
    {
        $boxLower = strtolower(trim($boxName));
        $rcbLower = strtolower(trim($rcbName));

        if ($boxLower === $rcbLower) {
            return true;
        }

        // RCB name may be a prefix of the full box score name (truncation)
        if ($rcbLower !== '' && str_starts_with($boxLower, $rcbLower)) {
            return true;
        }

        return false;
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
                    'start' => sprintf('%d-%02d-01', $endingYear, Season::IBL_PLAYOFF_MONTH),
                    'end' => sprintf('%d-%02d-30', $endingYear, Season::IBL_PLAYOFF_MONTH),
                ];

            case 'Preseason':
                return [
                    'start' => sprintf('%d-%02d-01', $beginningYear, Season::IBL_PRESEASON_MONTH),
                    'end' => sprintf('%d-%02d-31', $beginningYear, Season::IBL_HEAT_MONTH),
                ];

            case 'HEAT':
                return [
                    'start' => sprintf('%d-%02d-01', $beginningYear, Season::IBL_HEAT_MONTH),
                    'end' => sprintf('%d-%02d-30', $beginningYear, Season::IBL_HEAT_MONTH),
                ];

            default: // Regular Season
                return [
                    'start' => sprintf('%d-%02d-01', $beginningYear, Season::IBL_REGULAR_SEASON_STARTING_MONTH),
                    'end' => sprintf('%d-%02d-30', $endingYear, Season::IBL_REGULAR_SEASON_ENDING_MONTH),
                ];
        }
    }
}
