<?php

declare(strict_types=1);

namespace RecordHolders;

use RecordHolders\Contracts\RecordFormatterInterface;

/**
 * RecordFormatter - Pure display-formatting for Record Holders.
 *
 * Transforms already-fetched DB rows into view-ready arrays/strings.
 * Repository-free: team-lookup is a const registry; all helpers are static.
 *
 * @phpstan-import-type FormattedPlayerRecord from \RecordHolders\Contracts\RecordHoldersServiceInterface
 * @phpstan-import-type FormattedSeasonRecord from \RecordHolders\Contracts\RecordHoldersServiceInterface
 * @phpstan-import-type FormattedTeamGameRecord from \RecordHolders\Contracts\RecordHoldersServiceInterface
 * @phpstan-import-type FormattedTeamSeasonRecord from \RecordHolders\Contracts\RecordHoldersServiceInterface
 * @phpstan-import-type FormattedFranchiseRecord from \RecordHolders\Contracts\RecordHoldersServiceInterface
 *
 * @see RecordFormatterInterface
 */
class RecordFormatter implements RecordFormatterInterface
{
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
     * @see RecordFormatterInterface::formatPlayerRecords()
     *
     * @param list<array{pid: int, name: string, teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, value: int}> $dbRecords
     * @return list<FormattedPlayerRecord>
     */
    public function formatPlayerRecords(array $dbRecords, string $gameType): array
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
     * @see RecordFormatterInterface::formatQuadrupleDoubles()
     *
     * @param list<array{pid: int, name: string, teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, points: int, rebounds: int, assists: int, steals: int, blocks: int}> $dbRecords
     * @return list<FormattedPlayerRecord>
     */
    public function formatQuadrupleDoubles(array $dbRecords): array
    {
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
     * @see RecordFormatterInterface::formatAllStarRecord()
     *
     * @param list<array{name: string, pid: int|null, appearances: int}> $dbRecords
     * @return array{name: string, pid: int|null, teams: string, teamTids: string, amount: int, years: string}
     */
    public function formatAllStarRecord(array $dbRecords): array
    {
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
     * @see RecordFormatterInterface::formatPlayerSeasonRecords()
     *
     * @param list<array{pid: int, name: string, teamid: int, team: string, year: int, value: float|int}> $dbRecords
     * @return list<FormattedSeasonRecord>
     */
    public function formatPlayerSeasonRecords(array $dbRecords): array
    {
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
        return $formatted;
    }

    /**
     * @see RecordFormatterInterface::formatTeamGameRecords()
     *
     * @param list<array{teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, value: int}> $dbRecords
     * @return list<FormattedTeamGameRecord>
     */
    public function formatTeamGameRecords(array $dbRecords): array
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
     * @see RecordFormatterInterface::formatMarginRecords()
     *
     * @param list<array{winner_tid: int, winner_name: string, loser_tid: int, loser_name: string, date: string, box_id: int, game_of_that_day: int, margin: int}> $dbRecords
     * @return list<FormattedTeamGameRecord>
     */
    public function formatMarginRecords(array $dbRecords): array
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
     * @see RecordFormatterInterface::formatSeasonWinLossRecords()
     *
     * @param list<array{team_name: string, year: int, wins: int, losses: int}> $dbRecords
     * @return list<FormattedTeamSeasonRecord>
     */
    public function formatSeasonWinLossRecords(array $dbRecords): array
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
     * @see RecordFormatterInterface::formatSeasonStartRecords()
     *
     * @param list<array{team_name: string, year: int, wins: int, losses: int}> $dbRecords
     * @param string $type 'best' or 'worst'
     * @return list<FormattedTeamSeasonRecord>
     */
    public function formatSeasonStartRecords(array $dbRecords, string $type): array
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
     * @see RecordFormatterInterface::formatStreakRecords()
     *
     * @param list<array{team_name: string, streak: int, start_date: string, end_date: string, start_year: int, end_year: int}> $dbRecords
     * @return list<FormattedTeamSeasonRecord>
     */
    public function formatStreakRecords(array $dbRecords): array
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
     * @see RecordFormatterInterface::formatFranchiseRecords()
     *
     * @param list<array{team_name: string, count: int, years: string}> $dbRecords
     * @return list<FormattedFranchiseRecord>
     */
    public function formatFranchiseRecords(array $dbRecords): array
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

    /**
     * @see RecordFormatterInterface::detectTies()
     *
     * @param list<array<string, mixed>> $records Each record must have an 'amount' key
     * @return list<array<string, mixed>>
     */
    public function detectTies(array $records): array
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
     * @see RecordFormatterInterface::addTieLabel()
     *
     * @param list<array<string, mixed>> $records
     */
    public function addTieLabel(string $category, array $records): string
    {
        if (count($records) > 1) {
            return $category . ' [tie]';
        }
        return $category;
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
