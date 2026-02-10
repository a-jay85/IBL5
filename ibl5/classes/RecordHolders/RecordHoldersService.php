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
     * Team ID to logo abbreviation mapping.
     *
     * These abbreviations correspond to image files in images/topics/{abbr}.png
     *
     * @var array<int, string>
     */
    private const TEAM_ABBREVIATIONS = [
        1 => 'bos',
        2 => 'mia',
        3 => 'nyk',
        4 => 'bkn',
        5 => 'orl',
        6 => 'mil',
        7 => 'chi',
        8 => 'nor',
        9 => 'atl',
        10 => 'cha',
        11 => 'ind',
        12 => 'tor',
        13 => 'uta',
        14 => 'min',
        15 => 'den',
        16 => 'lva',
        17 => 'hou',
        18 => 'por',
        19 => 'lac',
        20 => 'van',
        21 => 'lal',
        22 => 'braves',
        23 => 'phx',
        24 => 'gsw',
        25 => 'det',
        26 => 'sac',
        27 => 'was',
        28 => 'dal',
    ];

    /**
     * Stat SQL expressions for player single-game records.
     *
     * @var array<string, string>
     */
    private const PLAYER_STAT_EXPRESSIONS = [
        'Most Points in a Single Game' => 'bs.calc_points',
        'Most Rebounds in a Single Game' => 'bs.calc_rebounds',
        'Most Assists in a Single Game' => 'bs.gameAST',
        'Most Steals in a Single Game' => 'bs.gameSTL',
        'Most Blocks in a Single Game' => 'bs.gameBLK',
        'Most Turnovers in a Single Game' => 'bs.gameTOV',
        'Most Field Goals in a Single Game' => 'bs.calc_fg_made',
        'Most Free Throws in a Single Game' => 'bs.gameFTM',
        'Most Three Pointers in a Single Game' => 'bs.game3GM',
    ];

    /**
     * Stat SQL expressions for team single-game records.
     *
     * @var array<string, string>
     */
    private const TEAM_STAT_EXPRESSIONS = [
        'Most Points in a Single Game' => 'bs.calc_points',
        'Most Rebounds in a Single Game' => 'bs.calc_rebounds',
        'Most Assists in a Single Game' => 'bs.gameAST',
        'Most Steals in a Single Game' => 'bs.gameSTL',
        'Most Blocks in a Single Game' => 'bs.gameBLK',
        'Most Field Goals in a Single Game' => 'bs.calc_fg_made',
        'Most Free Throws in a Single Game' => 'bs.gameFTM',
        'Most Three Pointers in a Single Game' => 'bs.game3GM',
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
     * @param list<array{pid: int, name: string, tid: int, team_name: string, date: string, BoxID: int, gameOfThatDay: int, oppTid: int, opp_team_name: string, value: int}> $dbRecords
     * @param string $gameType
     * @return list<FormattedPlayerRecord>
     */
    private function formatPlayerRecords(array $dbRecords, string $gameType): array
    {
        /** @var list<FormattedPlayerRecord> $formatted */
        $formatted = [];
        foreach ($dbRecords as $record) {
            $teamAbbr = $this->getTeamAbbreviation($record['tid']);
            $seasonYear = $this->dateToSeasonEndingYear($record['date']);
            $formatted[] = [
                'pid' => $record['pid'],
                'name' => $record['name'],
                'teamAbbr' => $teamAbbr,
                'teamTid' => $record['tid'],
                'teamYr' => (string) $seasonYear,
                'boxScoreUrl' => $this->buildBoxScoreUrl($record['date'], $record['gameOfThatDay'], $record['BoxID']),
                'dateDisplay' => $this->formatDateDisplay($record['date'], $gameType),
                'oppAbbr' => $this->getTeamAbbreviation($record['oppTid']),
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
            $gameType = $this->getGameTypeFromDate($record['date']);
            $seasonYear = $this->dateToSeasonEndingYear($record['date']);
            $teamAbbr = $this->getTeamAbbreviation($record['tid']);

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
                'teamTid' => $record['tid'],
                'teamYr' => (string) $seasonYear,
                'boxScoreUrl' => $this->buildBoxScoreUrl($record['date'], $record['gameOfThatDay'], $record['BoxID']),
                'dateDisplay' => $this->formatDateDisplay($record['date'], $gameType),
                'oppAbbr' => $this->getTeamAbbreviation($record['oppTid']),
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

        // Get the years for this player's all-star appearances
        $years = $this->getAllStarYears($topRecord['name']);

        // Get teams from ibl_hist for this player
        $teams = $this->getAllStarTeams($topRecord['name']);

        return [
            'name' => $topRecord['name'],
            'pid' => $topRecord['pid'],
            'teams' => $teams['abbrs'],
            'teamTids' => $teams['tids'],
            'amount' => $topRecord['appearances'],
            'years' => $years,
        ];
    }

    /**
     * Get all-star years for a player.
     */
    private function getAllStarYears(string $playerName): string
    {
        // The repository returns the count; for years, we'd need another query.
        // For now, return empty — the view can handle displaying just the count.
        return '';
    }

    /**
     * Get teams a player was an all-star for.
     *
     * @return array{abbrs: string, tids: string}
     */
    private function getAllStarTeams(string $playerName): array
    {
        // Would need additional repository query for team info during all-star years
        return ['abbrs' => '', 'tids' => ''];
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
                    'teamAbbr' => $this->getTeamAbbreviation($record['teamid']),
                    'teamTid' => $record['teamid'],
                    'teamYr' => (string) $record['year'],
                    'season' => $this->formatSeasonYearRange($record['year']),
                    'amount' => number_format((float) $record['value'], 1),
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
     * @param list<array{tid: int, team_name: string, date: string, BoxID: int, gameOfThatDay: int, oppTid: int, opp_team_name: string, value: int}> $dbRecords
     * @return list<FormattedTeamGameRecord>
     */
    private function formatTeamGameRecords(array $dbRecords): array
    {
        /** @var list<FormattedTeamGameRecord> $formatted */
        $formatted = [];
        foreach ($dbRecords as $record) {
            $formatted[] = [
                'teamAbbr' => $this->getTeamAbbreviation($record['tid']),
                'teamTid' => $record['tid'],
                'boxScoreUrl' => $this->buildBoxScoreUrl($record['date'], $record['gameOfThatDay'], $record['BoxID']),
                'dateDisplay' => $this->formatDateDisplay($record['date'], 'regularSeason'),
                'oppAbbr' => $this->getTeamAbbreviation($record['oppTid']),
                'oppTid' => $record['oppTid'],
                'amount' => (string) $record['value'],
            ];
        }
        return $formatted;
    }

    /**
     * Format margin of victory records.
     *
     * @param list<array{winner_tid: int, winner_name: string, loser_tid: int, loser_name: string, date: string, BoxID: int, gameOfThatDay: int, margin: int}> $dbRecords
     * @return list<FormattedTeamGameRecord>
     */
    private function formatMarginRecords(array $dbRecords): array
    {
        /** @var list<FormattedTeamGameRecord> $formatted */
        $formatted = [];
        foreach ($dbRecords as $record) {
            $formatted[] = [
                'teamAbbr' => $this->getTeamAbbreviation($record['winner_tid']),
                'teamTid' => $record['winner_tid'],
                'boxScoreUrl' => $this->buildBoxScoreUrl($record['date'], $record['gameOfThatDay'], $record['BoxID']),
                'dateDisplay' => $this->formatDateDisplay($record['date'], 'regularSeason'),
                'oppAbbr' => $this->getTeamAbbreviation($record['loser_tid']),
                'oppTid' => $record['loser_tid'],
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
                'teamAbbr' => $this->getTeamAbbreviationByName($record['team_name']),
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
                'teamAbbr' => $this->getTeamAbbreviationByName($record['team_name']),
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
                'teamAbbr' => $this->getTeamAbbreviationByName($record['team_name']),
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
                'teamAbbr' => $this->getTeamAbbreviationByName($record['team_name']),
                'amount' => (string) $record['count'],
                'years' => strip_tags($record['years']),
            ];
        }
        return $formatted;
    }

    /**
     * Get team abbreviation from team ID.
     */
    private function getTeamAbbreviation(int $teamId): string
    {
        return self::TEAM_ABBREVIATIONS[$teamId] ?? '';
    }

    /** @var array<string, int>|null */
    private ?array $nameToIdCache = null;

    private function getTeamAbbreviationByName(string $teamName): string
    {
        if ($this->nameToIdCache === null) {
            $this->nameToIdCache = [
                'Celtics' => 1, 'Heat' => 2, 'Knicks' => 3, 'Nets' => 4,
                'Magic' => 5, 'Bucks' => 6, 'Bulls' => 7, 'Pelicans' => 8,
                'Hawks' => 9, 'Sting' => 10, 'Pacers' => 11, 'Raptors' => 12,
                'Jazz' => 13, 'Timberwolves' => 14, 'Nuggets' => 15, 'Aces' => 16,
                'Rockets' => 17, 'Trailblazers' => 18, 'Clippers' => 19,
                'Grizzlies' => 20, 'Lakers' => 21, 'Braves' => 22,
                'Suns' => 23, 'Warriors' => 24, 'Pistons' => 25,
                'Kings' => 26, 'Bullets' => 27, 'Mavericks' => 28,
            ];
        }

        $teamId = $this->nameToIdCache[$teamName] ?? 0;
        return $this->getTeamAbbreviation($teamId);
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
     * HEAT: "1995 HEAT" (using the beginning year of the season)
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
            $year = (int) date('Y', $timestamp);
            return $year . ' HEAT';
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

    /**
     * Convert a date to its IBL season ending year.
     */
    private function dateToSeasonEndingYear(string $date): int
    {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return 0;
        }
        $month = (int) date('n', $timestamp);
        $year = (int) date('Y', $timestamp);

        return $month >= 10 ? $year + 1 : $year;
    }

    /**
     * Determine the game type from a date.
     */
    private function getGameTypeFromDate(string $date): string
    {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return 'regularSeason';
        }
        $month = (int) date('n', $timestamp);

        if ($month === 10) {
            return 'heat';
        }
        if ($month === 6) {
            return 'playoffs';
        }
        return 'regularSeason';
    }
}
