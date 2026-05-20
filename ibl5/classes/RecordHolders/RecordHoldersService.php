<?php

declare(strict_types=1);

namespace RecordHolders;

use RecordHolders\Contracts\RecordHoldersRepositoryInterface;
use RecordHolders\Contracts\RecordHoldersServiceInterface;

/**
 * RecordHoldersService - Business logic for all-time IBL record holders.
 *
 * Orchestrates repository calls, formats data for view rendering,
 * handles tie detection and date formatting.
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

    /**
     * Single source of truth for team ID → abbreviation + name.
     *
     * Abbreviations correspond to image files in images/topics/{abbr}.png.
     * Derive all team lookups from this registry so a rebrand is a one-line edit.
     *
     * @var array<int, array{abbr: string, name: string}>
     */
    private const TEAM_REGISTRY = [
        1  => ['abbr' => 'bos', 'name' => 'Celtics'],
        2  => ['abbr' => 'mia', 'name' => 'Heat'],
        3  => ['abbr' => 'nyk', 'name' => 'Knicks'],
        4  => ['abbr' => 'bkn', 'name' => 'Nets'],
        5  => ['abbr' => 'orl', 'name' => 'Magic'],
        6  => ['abbr' => 'mil', 'name' => 'Bucks'],
        7  => ['abbr' => 'chi', 'name' => 'Bulls'],
        8  => ['abbr' => 'nor', 'name' => 'Pelicans'],
        9  => ['abbr' => 'atl', 'name' => 'Hawks'],
        10 => ['abbr' => 'cha', 'name' => 'Sting'],
        11 => ['abbr' => 'ind', 'name' => 'Pacers'],
        12 => ['abbr' => 'tor', 'name' => 'Raptors'],
        13 => ['abbr' => 'uta', 'name' => 'Jazz'],
        14 => ['abbr' => 'min', 'name' => 'Timberwolves'],
        15 => ['abbr' => 'den', 'name' => 'Nuggets'],
        16 => ['abbr' => 'lva', 'name' => 'Aces'],
        17 => ['abbr' => 'hou', 'name' => 'Rockets'],
        18 => ['abbr' => 'por', 'name' => 'Trailblazers'],
        19 => ['abbr' => 'lac', 'name' => 'Clippers'],
        20 => ['abbr' => 'van', 'name' => 'Grizzlies'],
        21 => ['abbr' => 'lal', 'name' => 'Lakers'],
        22 => ['abbr' => 'braves', 'name' => 'Braves'],
        23 => ['abbr' => 'phx', 'name' => 'Suns'],
        24 => ['abbr' => 'gsw', 'name' => 'Warriors'],
        25 => ['abbr' => 'det', 'name' => 'Pistons'],
        26 => ['abbr' => 'sac', 'name' => 'Kings'],
        27 => ['abbr' => 'was', 'name' => 'Bullets'],
        28 => ['abbr' => 'dal', 'name' => 'Mavericks'],
    ];

    /**
     * Stat SQL expressions for player single-game records.
     *
     * @var array<string, string>
     */
    private const PLAYER_STAT_EXPRESSIONS = [
        'Most Points in a Single Game' => 'bs.calc_points',
        'Most Rebounds in a Single Game' => 'bs.calc_rebounds',
        'Most Assists in a Single Game' => 'bs.game_ast',
        'Most Steals in a Single Game' => 'bs.game_stl',
        'Most Blocks in a Single Game' => 'bs.game_blk',
        'Most Turnovers in a Single Game' => 'bs.game_tov',
        'Most Field Goals in a Single Game' => 'bs.calc_fg_made',
        'Most Free Throws in a Single Game' => 'bs.game_ftm',
        'Most Three Pointers in a Single Game' => 'bs.game_3gm',
    ];

    /**
     * Stat SQL expressions for team single-game records.
     *
     * @var array<string, string>
     */
    private const TEAM_STAT_EXPRESSIONS = [
        'Most Points in a Single Game' => 'bs.calc_points',
        'Most Rebounds in a Single Game' => 'bs.calc_rebounds',
        'Most Assists in a Single Game' => 'bs.game_ast',
        'Most Steals in a Single Game' => 'bs.game_stl',
        'Most Blocks in a Single Game' => 'bs.game_blk',
        'Most Field Goals in a Single Game' => 'bs.calc_fg_made',
        'Most Free Throws in a Single Game' => 'bs.game_ftm',
        'Most Three Pointers in a Single Game' => 'bs.game_3gm',
    ];

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

    /**
     * Date filters for game types.
     *
     * Regular season: Nov-May (months 11,12,1,2,3,4,5)
     * Playoffs: June (month 6)
     * HEAT: October (month 10)
     *
     * @var array<string, string>
     */
    private const DATE_FILTERS = [
        'regularSeason' => 'bs.game_type = 1',
        'playoffs' => 'bs.game_type = 2',
        'heat' => 'bs.game_type = 3',
    ];

    public function __construct(RecordHoldersRepositoryInterface $repository)
    {
        $this->repository = $repository;
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
            'quadrupleDoubles' => $this->formatQuadrupleDoubles(),
            'allStarRecord' => $this->formatAllStarRecord(),
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
        $dateFilter = self::DATE_FILTERS[$gameType] ?? self::DATE_FILTERS['regularSeason'];

        $batchResults = $this->repository->getTopPlayerSingleGameBatch(self::PLAYER_STAT_EXPRESSIONS, $dateFilter);

        $records = [];
        foreach ($batchResults as $category => $dbRecords) {
            $formatted = $this->formatPlayerRecords($dbRecords, $gameType);
            /** @var list<FormattedPlayerRecord> $withTies */
            $withTies = $this->detectTies($formatted);
            $categoryLabel = $this->addTieLabel($category, $withTies);
            $records[$categoryLabel] = $withTies;
        }

        return $records;
    }

    /**
     * Format player single-game records from DB rows.
     *
     * @param list<array{pid: int, name: string, teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, value: int}> $dbRecords
     * @param string $gameType
     * @return list<FormattedPlayerRecord>
     */
    private function formatPlayerRecords(array $dbRecords, string $gameType): array
    {
        /** @var list<FormattedPlayerRecord> $formatted */
        $formatted = [];
        foreach ($dbRecords as $record) {
            $teamAbbr = self::teamAbbreviation($record['teamid']);
            $seasonYear = IblSeasonDateHelper::dateToSeasonEndingYear($record['date']);
            $formatted[] = [
                'pid' => $record['pid'],
                'name' => $record['name'],
                'teamAbbr' => $teamAbbr,
                'teamTid' => $record['teamid'],
                'teamYr' => (string) $seasonYear,
                'boxScoreUrl' => $this->buildBoxScoreUrl($record['date'], $record['game_of_that_day'], $record['box_id']),
                'dateDisplay' => $this->formatDateDisplay($record['date'], $gameType),
                'oppAbbr' => self::teamAbbreviation($record['oppTid']),
                'oppTid' => $record['oppTid'],
                'oppYr' => (string) $seasonYear,
                'amount' => (string) $record['value'],
            ];
        }
        return $formatted;
    }

    /**
     * Format quadruple double records.
     *
     * @return list<FormattedPlayerRecord>
     */
    private function formatQuadrupleDoubles(): array
    {
        $dbRecords = $this->repository->getQuadrupleDoubles();
        /** @var list<FormattedPlayerRecord> $formatted */
        $formatted = [];

        foreach ($dbRecords as $record) {
            $gameType = IblSeasonDateHelper::getGameTypeFromDate($record['date']);
            $seasonYear = IblSeasonDateHelper::dateToSeasonEndingYear($record['date']);
            $teamAbbr = self::teamAbbreviation($record['teamid']);

            // Build multi-line amount string
            $amount = $record['points'] . "pts\n"
                . $record['rebounds'] . "rbs\n"
                . $record['assists'] . "ast\n"
                . $record['steals'] . "stl";
            if ($record['blocks'] >= 10) {
                $amount .= "\n" . $record['blocks'] . 'blk';
            }

            $formatted[] = [
                'pid' => $record['pid'],
                'name' => $record['name'],
                'teamAbbr' => $teamAbbr,
                'teamTid' => $record['teamid'],
                'teamYr' => (string) $seasonYear,
                'boxScoreUrl' => $this->buildBoxScoreUrl($record['date'], $record['game_of_that_day'], $record['box_id']),
                'dateDisplay' => $this->formatDateDisplay($record['date'], $gameType),
                'oppAbbr' => self::teamAbbreviation($record['oppTid']),
                'oppTid' => $record['oppTid'],
                'oppYr' => (string) $seasonYear,
                'amount' => $amount,
            ];
        }

        return $formatted;
    }

    /**
     * Format the all-star appearances record.
     *
     * @return array{name: string, pid: int|null, teams: string, teamTids: string, amount: int, years: string}
     */
    private function formatAllStarRecord(): array
    {
        $dbRecords = $this->repository->getMostAllStarAppearances();

        if ($dbRecords === []) {
            return ['name' => '', 'pid' => null, 'teams' => '', 'teamTids' => '', 'amount' => 0, 'years' => ''];
        }

        $topRecord = $dbRecords[0];

        return [
            'name' => $topRecord['name'],
            'pid' => $topRecord['pid'],
            'teams' => '',
            'teamTids' => '',
            'amount' => $topRecord['appearances'],
            'years' => '',
        ];
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
            /** @var list<FormattedSeasonRecord> $formatted */
            $formatted = [];
            foreach ($dbRecords as $record) {
                $formatted[] = [
                    'pid' => $record['pid'],
                    'name' => $record['name'],
                    'teamAbbr' => self::teamAbbreviation($record['teamid']),
                    'teamTid' => $record['teamid'],
                    'teamYr' => (string) $record['year'],
                    'season' => $this->formatSeasonYearRange($record['year']),
                    'amount' => \BasketballStats\StatsFormatter::formatWithDecimals((float) $record['value'], 1),
                ];
            }

            /** @var list<FormattedSeasonRecord> $withTies */
            $withTies = $this->detectTies($formatted);
            $categoryLabel = $this->addTieLabel($category, $withTies);
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
        $dateFilter = self::DATE_FILTERS['regularSeason'];

        // Build batch config for all team stats (8 DESC + 1 ASC = 9 queries → 1)
        /** @var array<string, array{expression: string, order: string}> $batchConfig */
        $batchConfig = [];
        foreach (self::TEAM_STAT_EXPRESSIONS as $category => $expression) {
            $batchConfig[$category] = ['expression' => $expression, 'order' => 'DESC'];
        }
        $batchConfig['Fewest Points in a Single Game'] = [
            'expression' => 'bs.calc_points',
            'order' => 'ASC',
        ];

        $batchResults = $this->repository->getTopTeamSingleGameBatch($batchConfig, $dateFilter);

        foreach ($batchResults as $category => $dbRecords) {
            $formatted = $this->formatTeamGameRecords($dbRecords);
            /** @var list<FormattedTeamGameRecord> $withTies */
            $withTies = $this->detectTies($formatted);
            $categoryLabel = $this->addTieLabel($category, $withTies);
            $records[$categoryLabel] = $withTies;
        }

        // Half scores (these use a different query structure, keep as individual calls)
        $mostHalf = $this->formatTeamGameRecords($this->repository->getTopTeamHalfScore('first', 'DESC'));
        /** @var list<FormattedTeamGameRecord> $mostHalfTies */
        $mostHalfTies = $this->detectTies($mostHalf);
        $records[$this->addTieLabel('Most Points in a Single Half', $mostHalfTies)] = $mostHalfTies;

        $fewestHalf = $this->formatTeamGameRecords($this->repository->getTopTeamHalfScore('second', 'ASC'));
        /** @var list<FormattedTeamGameRecord> $fewestHalfTies */
        $fewestHalfTies = $this->detectTies($fewestHalf);
        $records[$this->addTieLabel('Fewest Points in a Single Half', $fewestHalfTies)] = $fewestHalfTies;

        // Margin of victory (overall and playoffs)
        $marginOverall = $this->formatMarginRecords($this->repository->getLargestMarginOfVictory('1=1'));
        /** @var list<FormattedTeamGameRecord> $marginOverallTies */
        $marginOverallTies = $this->detectTies($marginOverall);
        $records[$this->addTieLabel('Largest Margin of Victory [overall]', $marginOverallTies)] = $marginOverallTies;

        $marginPlayoffs = $this->formatMarginRecords($this->repository->getLargestMarginOfVictory('bs.game_type = 2'));
        /** @var list<FormattedTeamGameRecord> $marginPlayoffTies */
        $marginPlayoffTies = $this->detectTies($marginPlayoffs);
        $records[$this->addTieLabel('Largest Margin of Victory [playoffs]', $marginPlayoffTies)] = $marginPlayoffTies;

        return $records;
    }

    /**
     * Format team single-game records from DB rows.
     *
     * @param list<array{teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, value: int}> $dbRecords
     * @return list<FormattedTeamGameRecord>
     */
    private function formatTeamGameRecords(array $dbRecords): array
    {
        /** @var list<FormattedTeamGameRecord> $formatted */
        $formatted = [];
        foreach ($dbRecords as $record) {
            $seasonYear = IblSeasonDateHelper::dateToSeasonEndingYear($record['date']);
            $formatted[] = [
                'teamAbbr' => self::teamAbbreviation($record['teamid']),
                'teamTid' => $record['teamid'],
                'teamYr' => (string) $seasonYear,
                'boxScoreUrl' => $this->buildBoxScoreUrl($record['date'], $record['game_of_that_day'], $record['box_id']),
                'dateDisplay' => $this->formatDateDisplay($record['date'], 'regularSeason'),
                'oppAbbr' => self::teamAbbreviation($record['oppTid']),
                'oppTid' => $record['oppTid'],
                'oppYr' => (string) $seasonYear,
                'amount' => (string) $record['value'],
            ];
        }
        return $formatted;
    }

    /**
     * Format margin of victory records.
     *
     * @param list<array{winner_tid: int, winner_name: string, loser_tid: int, loser_name: string, date: string, box_id: int, game_of_that_day: int, margin: int}> $dbRecords
     * @return list<FormattedTeamGameRecord>
     */
    private function formatMarginRecords(array $dbRecords): array
    {
        /** @var list<FormattedTeamGameRecord> $formatted */
        $formatted = [];
        foreach ($dbRecords as $record) {
            $seasonYear = IblSeasonDateHelper::dateToSeasonEndingYear($record['date']);
            $formatted[] = [
                'teamAbbr' => self::teamAbbreviation($record['winner_tid']),
                'teamTid' => $record['winner_tid'],
                'teamYr' => (string) $seasonYear,
                'boxScoreUrl' => $this->buildBoxScoreUrl($record['date'], $record['game_of_that_day'], $record['box_id']),
                'dateDisplay' => $this->formatDateDisplay($record['date'], 'regularSeason'),
                'oppAbbr' => self::teamAbbreviation($record['loser_tid']),
                'oppTid' => $record['loser_tid'],
                'oppYr' => (string) $seasonYear,
                'amount' => (string) $record['margin'],
            ];
        }
        return $formatted;
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
        $records['Best Season Record'] = $this->formatSeasonWinLossRecords($best);

        $worst = $this->repository->getBestWorstSeasonRecord('ASC');
        $records['Worst Season Record'] = $this->formatSeasonWinLossRecords($worst);

        // Best/worst season start
        $bestStart = $this->repository->getBestWorstSeasonStart('best');
        $records['Best Season Record, Start'] = $this->formatSeasonStartRecords($bestStart, 'best');

        $worstStart = $this->repository->getBestWorstSeasonStart('worst');
        $records['Worst Season Record, Start'] = $this->formatSeasonStartRecords($worstStart, 'worst');

        // Longest winning/losing streaks
        $winStreak = $this->repository->getLongestStreak('winning');
        $records['Longest Winning Streak'] = $this->formatStreakRecords($winStreak);

        $loseStreak = $this->repository->getLongestStreak('losing');
        $records['Longest Losing Streak'] = $this->formatStreakRecords($loseStreak);

        return $records;
    }

    /**
     * Format season win/loss records.
     *
     * @param list<array{team_name: string, year: int, wins: int, losses: int}> $dbRecords
     * @return list<FormattedTeamSeasonRecord>
     */
    private function formatSeasonWinLossRecords(array $dbRecords): array
    {
        /** @var list<FormattedTeamSeasonRecord> $formatted */
        $formatted = [];
        foreach ($dbRecords as $record) {
            $endingYear = $record['year'];
            $formatted[] = [
                'teamAbbr' => self::teamAbbreviationByName($record['team_name']),
                'teamTid' => self::teamIdByName($record['team_name']),
                'teamYr' => (string) $endingYear,
                'season' => $this->formatSeasonYearRange($endingYear),
                'amount' => $record['wins'] . '-' . $record['losses'],
            ];
        }
        /** @var list<FormattedTeamSeasonRecord> $withTies */
        $withTies = $this->detectTies($formatted);
        return $withTies;
    }

    /**
     * Format season start records.
     *
     * @param list<array{team_name: string, year: int, wins: int, losses: int}> $dbRecords
     * @param string $type 'best' or 'worst'
     * @return list<FormattedTeamSeasonRecord>
     */
    private function formatSeasonStartRecords(array $dbRecords, string $type): array
    {
        /** @var list<FormattedTeamSeasonRecord> $formatted */
        $formatted = [];
        foreach ($dbRecords as $record) {
            $formatted[] = [
                'teamAbbr' => self::teamAbbreviationByName($record['team_name']),
                'teamTid' => self::teamIdByName($record['team_name']),
                'teamYr' => (string) $record['year'],
                'season' => $this->formatSeasonYearRange($record['year']),
                'amount' => $record['wins'] . '-' . $record['losses'],
            ];
        }
        /** @var list<FormattedTeamSeasonRecord> $withTies */
        $withTies = $this->detectTies($formatted);
        return $withTies;
    }

    /**
     * Format streak records.
     *
     * @param list<array{team_name: string, streak: int, start_date: string, end_date: string, start_year: int, end_year: int}> $dbRecords
     * @return list<FormattedTeamSeasonRecord>
     */
    private function formatStreakRecords(array $dbRecords): array
    {
        /** @var list<FormattedTeamSeasonRecord> $formatted */
        $formatted = [];
        foreach ($dbRecords as $record) {
            $season = $this->formatSeasonYearRange($record['start_year']);
            if ($record['start_year'] !== $record['end_year']) {
                $season .= ', ' . $this->formatSeasonYearRange($record['end_year']);
            }
            $formatted[] = [
                'teamAbbr' => self::teamAbbreviationByName($record['team_name']),
                'teamTid' => self::teamIdByName($record['team_name']),
                'teamYr' => (string) $record['start_year'],
                'season' => $season,
                'amount' => (string) $record['streak'],
            ];
        }
        /** @var list<FormattedTeamSeasonRecord> $withTies */
        $withTies = $this->detectTies($formatted);
        return $withTies;
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
        $records['Most Playoff Appearances'] = $this->formatFranchiseRecords($playoffs);

        // Most division championships
        $divisions = $this->repository->getMostTitlesByType('Division');
        $records['Most Division Championships'] = $this->formatFranchiseRecords($divisions);

        // Most IBL Finals appearances (Conference Championship winners)
        $finals = $this->repository->getMostTitlesByType('Conference');
        $records['Most IBL Finals Appearances'] = $this->formatFranchiseRecords($finals);

        // Most IBL Championships
        $championships = $this->repository->getMostTitlesByType('IBL Champions');
        $records['Most IBL Championships'] = $this->formatFranchiseRecords($championships);

        // Add tie labels
        /** @var array<string, list<FormattedFranchiseRecord>> $labeled */
        $labeled = [];
        foreach ($records as $category => $data) {
            /** @var list<FormattedFranchiseRecord> $withTies */
            $withTies = $this->detectTies($data);
            $labeled[$this->addTieLabel($category, $withTies)] = $withTies;
        }

        return $labeled;
    }

    /**
     * Format franchise records (titles, appearances).
     *
     * @param list<array{team_name: string, count: int, years: string}> $dbRecords
     * @return list<FormattedFranchiseRecord>
     */
    private function formatFranchiseRecords(array $dbRecords): array
    {
        /** @var list<FormattedFranchiseRecord> $formatted */
        $formatted = [];
        foreach ($dbRecords as $record) {
            $formatted[] = [
                'teamAbbr' => self::teamAbbreviationByName($record['team_name']),
                'teamTid' => self::teamIdByName($record['team_name']),
                'amount' => (string) $record['count'],
                'years' => strip_tags($record['years']),
            ];
        }
        return $formatted;
    }

    private static function teamAbbreviation(int $teamId): string
    {
        return self::TEAM_REGISTRY[$teamId]['abbr'] ?? '';
    }

    private static function teamIdByName(string $teamName): int
    {
        /** @var array<string, int>|null $byName */
        static $byName = null;
        if ($byName === null) {
            $byName = [];
            foreach (self::TEAM_REGISTRY as $id => $info) {
                $byName[$info['name']] = $id;
            }
        }
        return $byName[$teamName] ?? 0;
    }

    private static function teamAbbreviationByName(string $teamName): string
    {
        return self::teamAbbreviation(self::teamIdByName($teamName));
    }

    /**
     * Detect ties in formatted records (multiple entries sharing the same top value).
     *
     * Only keeps entries that match the top (first) value.
     * Returns the same array shape as the input, with only top-value entries.
     *
     * @param list<array<string, mixed>> $records Each record must have an 'amount' key
     * @return list<array<string, mixed>>
     */
    private function detectTies(array $records): array
    {
        if (count($records) <= 1) {
            return $records;
        }

        $topAmount = $records[0]['amount'];
        $tied = [];
        foreach ($records as $record) {
            if ($record['amount'] === $topAmount) {
                $tied[] = $record;
            }
        }
        return $tied;
    }

    /**
     * Add " [tie]" suffix to category name if there are multiple records.
     *
     * @param list<array<string, mixed>> $records
     */
    private function addTieLabel(string $category, array $records): string
    {
        if (count($records) > 1) {
            return $category . ' [tie]';
        }
        return $category;
    }

    /**
     * Format a date for display based on game type.
     *
     * Regular season / Playoffs: "January 16, 1996"
     * HEAT: "HEAT\nOctober 16, 1995" (newline-separated for two-line display)
     */
    private function formatDateDisplay(string $date, string $gameType): string
    {
        if ($date === '' || $date === '0000-00-00') {
            return '';
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $date;
        }

        if ($gameType === 'heat') {
            return "HEAT\n" . date('F j, Y', $timestamp);
        }

        return date('F j, Y', $timestamp);
    }

    /**
     * Build a box score URL using the IBL6 SvelteKit URL pattern.
     *
     * Returns empty string if gameOfThatDay is not available (historical records).
     */
    private function buildBoxScoreUrl(string $date, int $gameOfThatDay, int $boxId = 0): string
    {
        return \Utilities\BoxScoreUrlBuilder::buildUrl($date, $gameOfThatDay, $boxId);
    }

    /**
     * Format a season ending year into "YYYY-YY" range format.
     *
     * Example: 1996 → "1995-96"
     */
    private function formatSeasonYearRange(int $endingYear): string
    {
        $beginYear = $endingYear - 1;
        $endYearShort = substr((string) $endingYear, 2);
        return $beginYear . '-' . $endYearShort;
    }

}
