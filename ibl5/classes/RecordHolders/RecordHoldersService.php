<?php

declare(strict_types=1);

namespace RecordHolders;

use RecordHolders\Contracts\RecordFormatterInterface;
use RecordHolders\Contracts\RecordHoldersRepositoryInterface;
use RecordHolders\Contracts\RecordHoldersServiceInterface;

/**
 * RecordHoldersService - Business logic for all-time IBL record holders.
 *
 * Thin orchestrator: fetches data from the repository and delegates all
 * formatting to RecordFormatter (injected as RecordFormatterInterface).
 *
 * @phpstan-import-type AllRecordsData from RecordHoldersServiceInterface
 * @phpstan-import-type FormattedPlayerRecord from RecordHoldersServiceInterface
 * @phpstan-import-type FormattedSeasonRecord from RecordHoldersServiceInterface
 * @phpstan-import-type FormattedTeamGameRecord from RecordHoldersServiceInterface
 * @phpstan-import-type FormattedTeamSeasonRecord from RecordHoldersServiceInterface
 * @phpstan-import-type FormattedFranchiseRecord from RecordHoldersServiceInterface
 *
 * @see RecordHoldersServiceInterface
 */
class RecordHoldersService implements RecordHoldersServiceInterface
{
    private RecordHoldersRepositoryInterface $repository;
    private RecordFormatterInterface $formatter;

    /**
     * Full-season stat columns in ibl_hist and the games column to divide by.
     *
     * @var array<string, array{statColumn: string, gamesColumn: string}>
     */
    private const SEASON_STAT_COLUMNS = [
        'Highest Scoring Average in a Regular Season' => ['statColumn' => 'pts', 'gamesColumn' => 'games'],
        'Highest Rebounding Average in a Regular Season' => ['statColumn' => 'reb', 'gamesColumn' => 'games'],
        'Highest Assist Average in a Regular Season' => ['statColumn' => 'ast', 'gamesColumn' => 'games'],
        'Highest Steals Average in a Regular Season' => ['statColumn' => 'stl', 'gamesColumn' => 'games'],
        'Highest Blocks Average in a Regular Season' => ['statColumn' => 'blk', 'gamesColumn' => 'games'],
    ];

    public function __construct(
        RecordHoldersRepositoryInterface $repository,
        ?RecordFormatterInterface $formatter = null,
    ) {
        $this->repository = $repository;
        $this->formatter = $formatter ?? new RecordFormatter();
    }

    /**
     * @see RecordHoldersServiceInterface::getAllRecords()
     *
     * @return AllRecordsData
     */
    public function getAllRecords(): array
    {
        return [
            'playerSingleGame' => [
                'regularSeason' => $this->getPlayerSingleGameRecords('regularSeason'),
                'playoffs' => $this->getPlayerSingleGameRecords('playoffs'),
                'heat' => $this->getPlayerSingleGameRecords('heat'),
            ],
            'quadrupleDoubles' => $this->formatter->formatQuadrupleDoubles($this->repository->getQuadrupleDoubles()),
            'allStarRecord' => $this->formatter->formatAllStarRecord($this->repository->getMostAllStarAppearances()),
            'playerFullSeason' => $this->getPlayerFullSeasonRecords(),
            'teamGameRecords' => $this->getTeamGameRecords(),
            'teamSeasonRecords' => $this->getTeamSeasonRecords(),
            'teamFranchise' => $this->getTeamFranchiseRecords(),
        ];
    }

    /**
     * Get player single-game records for a given game type using batch query.
     *
     * @param string $gameType One of 'regularSeason', 'playoffs', 'heat'
     * @return array<string, list<FormattedPlayerRecord>>
     */
    private function getPlayerSingleGameRecords(string $gameType): array
    {
        $dateFilter = RecordStatDefinitions::DATE_FILTERS[$gameType] ?? RecordStatDefinitions::DATE_FILTERS['regularSeason'];

        $batchResults = $this->repository->getTopPlayerSingleGameBatch(self::playerStatExpressions(), $dateFilter);

        $records = [];
        foreach ($batchResults as $category => $dbRecords) {
            $formatted = $this->formatter->formatPlayerRecords($dbRecords, $gameType);
            /** @var list<FormattedPlayerRecord> $withTies */
            $withTies = $this->formatter->detectTies($formatted);
            $categoryLabel = $this->formatter->addTieLabel($category, $withTies);
            $records[$categoryLabel] = $withTies;
        }

        return $records;
    }

    /**
     * Get player full-season records using batch query.
     *
     * @return array<string, list<FormattedSeasonRecord>>
     */
    private function getPlayerFullSeasonRecords(): array
    {
        $batchResults = $this->repository->getTopSeasonAverageBatch(self::SEASON_STAT_COLUMNS, 50);

        $records = [];
        foreach ($batchResults as $category => $dbRecords) {
            $formatted = $this->formatter->formatPlayerSeasonRecords($dbRecords);

            /** @var list<FormattedSeasonRecord> $withTies */
            $withTies = $this->formatter->detectTies($formatted);
            $categoryLabel = $this->formatter->addTieLabel($category, $withTies);
            $records[$categoryLabel] = $withTies;
        }

        return $records;
    }

    /**
     * Get team single-game records using batch query where possible.
     *
     * @return array<string, list<FormattedTeamGameRecord>>
     */
    private function getTeamGameRecords(): array
    {
        $records = [];
        $dateFilter = RecordStatDefinitions::DATE_FILTERS['regularSeason'];

        // Build batch config for all team stats (8 DESC + 1 ASC = 9 queries → 1)
        /** @var array<string, array{expression: string, order: string}> $batchConfig */
        $batchConfig = [];
        foreach (self::teamStatExpressions() as $category => $expression) {
            $batchConfig[$category] = ['expression' => $expression, 'order' => 'DESC'];
        }
        $batchConfig['Fewest Points in a Single Game'] = [
            'expression' => 'bs.calc_points',
            'order' => 'ASC',
        ];

        $batchResults = $this->repository->getTopTeamSingleGameBatch($batchConfig, $dateFilter);

        foreach ($batchResults as $category => $dbRecords) {
            $formatted = $this->formatter->formatTeamGameRecords($dbRecords);
            /** @var list<FormattedTeamGameRecord> $withTies */
            $withTies = $this->formatter->detectTies($formatted);
            $categoryLabel = $this->formatter->addTieLabel($category, $withTies);
            $records[$categoryLabel] = $withTies;
        }

        // Half scores (these use a different query structure, keep as individual calls)
        $mostHalf = $this->formatter->formatTeamGameRecords($this->repository->getTopTeamHalfScore('first', 'DESC'));
        /** @var list<FormattedTeamGameRecord> $mostHalfTies */
        $mostHalfTies = $this->formatter->detectTies($mostHalf);
        $records[$this->formatter->addTieLabel('Most Points in a Single Half', $mostHalfTies)] = $mostHalfTies;

        $fewestHalf = $this->formatter->formatTeamGameRecords($this->repository->getTopTeamHalfScore('second', 'ASC'));
        /** @var list<FormattedTeamGameRecord> $fewestHalfTies */
        $fewestHalfTies = $this->formatter->detectTies($fewestHalf);
        $records[$this->formatter->addTieLabel('Fewest Points in a Single Half', $fewestHalfTies)] = $fewestHalfTies;

        // Margin of victory (overall and playoffs)
        $marginOverall = $this->formatter->formatMarginRecords($this->repository->getLargestMarginOfVictory('1=1'));
        /** @var list<FormattedTeamGameRecord> $marginOverallTies */
        $marginOverallTies = $this->formatter->detectTies($marginOverall);
        $records[$this->formatter->addTieLabel('Largest Margin of Victory [overall]', $marginOverallTies)] = $marginOverallTies;

        $marginPlayoffs = $this->formatter->formatMarginRecords($this->repository->getLargestMarginOfVictory('bs.game_type = 2'));
        /** @var list<FormattedTeamGameRecord> $marginPlayoffTies */
        $marginPlayoffTies = $this->formatter->detectTies($marginPlayoffs);
        $records[$this->formatter->addTieLabel('Largest Margin of Victory [playoffs]', $marginPlayoffTies)] = $marginPlayoffTies;

        return $records;
    }

    /**
     * Get team season records (best/worst record, streaks, season starts).
     *
     * @return array<string, list<FormattedTeamSeasonRecord>>
     */
    private function getTeamSeasonRecords(): array
    {
        $records = [];

        // Best/worst season record
        $best = $this->repository->getBestWorstSeasonRecord('DESC');
        $records['Best Season Record'] = $this->formatter->formatSeasonWinLossRecords($best);

        $worst = $this->repository->getBestWorstSeasonRecord('ASC');
        $records['Worst Season Record'] = $this->formatter->formatSeasonWinLossRecords($worst);

        // Best/worst season start
        $bestStart = $this->repository->getBestWorstSeasonStart('best');
        $records['Best Season Record, Start'] = $this->formatter->formatSeasonStartRecords($bestStart, 'best');

        $worstStart = $this->repository->getBestWorstSeasonStart('worst');
        $records['Worst Season Record, Start'] = $this->formatter->formatSeasonStartRecords($worstStart, 'worst');

        // Longest winning/losing streaks
        $winStreak = $this->repository->getLongestStreak('winning');
        $records['Longest Winning Streak'] = $this->formatter->formatStreakRecords($winStreak);

        $loseStreak = $this->repository->getLongestStreak('losing');
        $records['Longest Losing Streak'] = $this->formatter->formatStreakRecords($loseStreak);

        return $records;
    }

    /**
     * Get team franchise records (playoff appearances, titles).
     *
     * @return array<string, list<FormattedFranchiseRecord>>
     */
    private function getTeamFranchiseRecords(): array
    {
        $records = [];

        // Most playoff appearances
        $playoffs = $this->repository->getMostPlayoffAppearances();
        $records['Most Playoff Appearances'] = $this->formatter->formatFranchiseRecords($playoffs);

        // Most division championships
        $divisions = $this->repository->getMostTitlesByType('Division');
        $records['Most Division Championships'] = $this->formatter->formatFranchiseRecords($divisions);

        // Most IBL Finals appearances (Conference Championship winners)
        $finals = $this->repository->getMostTitlesByType('Conference');
        $records['Most IBL Finals Appearances'] = $this->formatter->formatFranchiseRecords($finals);

        // Most IBL Championships
        $championships = $this->repository->getMostTitlesByType('IBL Champions');
        $records['Most IBL Championships'] = $this->formatter->formatFranchiseRecords($championships);

        // Add tie labels
        /** @var array<string, list<FormattedFranchiseRecord>> $labeled */
        $labeled = [];
        foreach ($records as $category => $data) {
            /** @var list<FormattedFranchiseRecord> $withTies */
            $withTies = $this->formatter->detectTies($data);
            $labeled[$this->formatter->addTieLabel($category, $withTies)] = $withTies;
        }

        return $labeled;
    }

    /**
     * Player single-game stat expressions, keyed by record label (all 9 stats).
     *
     * Mapped from the canonical RecordStatDefinitions registry.
     *
     * @return array<string, string>
     */
    private static function playerStatExpressions(): array
    {
        $map = [];
        foreach (RecordStatDefinitions::STATS as $def) {
            $map[$def['recordLabel']] = $def['expression'];
        }
        return $map;
    }

    /**
     * Team single-game stat expressions, keyed by record label (the 8-stat team
     * subset — turnovers excluded).
     *
     * Mapped from the canonical RecordStatDefinitions registry.
     *
     * @return array<string, string>
     */
    private static function teamStatExpressions(): array
    {
        $map = [];
        foreach (RecordStatDefinitions::STATS as $def) {
            if ($def['teamKey'] === null) {
                continue;
            }
            $map[$def['recordLabel']] = $def['expression'];
        }
        return $map;
    }
}
