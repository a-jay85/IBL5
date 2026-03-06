<?php

declare(strict_types=1);

namespace Updater;

use League\LeagueContext;
use Utilities\UuidGenerator;
use Utilities\SchFileParser;
use Utilities\DateParser;

class ScheduleUpdater extends \BaseMysqliRepository {
    private \Season $season;

    /** @var array<int, string> Team ID to name lookup (for logging) */
    private array $teamIdToNameMap = [];

    private const UNPLAYED_BOX_ID = 100000;

    /** @var array<int, string> Calendar month number to name for date string construction */
    private const MONTH_NAMES = [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December',
    ];

    public function __construct(\mysqli $db, \Season $season, ?LeagueContext $leagueContext = null) {
        parent::__construct($db, $leagueContext);
        $this->season = $season;
    }

    /**
     * @return array{date: string, year: int, month: int, day: int}|null
     */
    protected function extractDate(string $rawDate): ?array {
        // Use injected LeagueContext, fall back to global, then default to IBL
        if ($this->leagueContext !== null) {
            $currentLeague = $this->leagueContext->getCurrentLeague();
        } else {
            global $leagueContext;
            /** @var LeagueContext|null $leagueContext */
            $currentLeague = $leagueContext !== null ? $leagueContext->getCurrentLeague() : 'IBL';
        }

        if ($rawDate === '') {
            return null;
        }

        // Handle Preseason year adjustments
        if ($this->season->phase === "Preseason") {
            $this->season->beginningYear = \Season::IBL_PRESEASON_YEAR;
            $this->season->endingYear = \Season::IBL_PRESEASON_YEAR + 1;
        }

        return DateParser::extractDate(
            $rawDate,
            $this->season->phase,
            $this->season->beginningYear,
            $this->season->endingYear,
            $currentLeague
        );
    }

    /**
     * Pre-fetch team ID→name mappings for logging
     */
    private function preloadTeamNameMap(): void
    {
        $teamInfoTable = $this->resolveTable('ibl_team_info');

        /** @var list<array{team_name: string, teamid: int}> $rows */
        $rows = $this->fetchAll(
            "SELECT team_name, teamid FROM {$teamInfoTable} WHERE teamid BETWEEN 1 AND ?",
            "i",
            \League::MAX_REAL_TEAMID
        );

        foreach ($rows as $row) {
            $this->teamIdToNameMap[$row['teamid']] = $row['team_name'];
        }
    }

    /**
     * Build a date string from month and day for DateParser consumption.
     *
     * Example: buildDateString(11, 2) → "November 2, 2000"
     *
     * @param int $month Calendar month number (1-12)
     * @param int $day Day of month
     * @param int|null $monthOverride Override the month (e.g., for playoff games that need June dates)
     */
    protected function buildDateString(int $month, int $day, ?int $monthOverride = null): string
    {
        $effectiveMonth = $monthOverride ?? $month;
        $monthName = self::MONTH_NAMES[$effectiveMonth];

        return "{$monthName} {$day}, 2000";
    }

    /**
     * Get team name by ID for logging purposes.
     */
    private function getTeamNameById(int $teamId): string
    {
        return $this->teamIdToNameMap[$teamId] ?? "Team #{$teamId}";
    }

    /**
     * Build a lookup set of playoff team pairings from box scores.
     *
     * Month offset 6 in the .sch file contains both final regular season games
     * and playoff games mixed on the same days. We identify playoff games by
     * matching their team pairings against June box scores (which have correct dates).
     *
     * @return array<string, true> Set of "visitorTID-homeTID" keys for playoff matchups
     */
    protected function getPlayoffMatchups(): array
    {
        $boxScoresTeamsTable = $this->resolveTable('ibl_box_scores_teams');

        /** @var list<array{visitorTeamID: int, homeTeamID: int}> $rows */
        $rows = $this->fetchAll(
            "SELECT DISTINCT visitorTeamID, homeTeamID
             FROM {$boxScoresTeamsTable}
             WHERE Date LIKE CONCAT(?, '-06-%')",
            'i',
            $this->season->endingYear
        );

        /** @var array<string, true> $matchups */
        $matchups = [];
        foreach ($rows as $row) {
            $matchups[$row['visitorTeamID'] . '-' . $row['homeTeamID']] = true;
        }

        return $matchups;
    }

    public function update(): void {
        $scheduleTable = $this->resolveTable('ibl_schedule');

        echo "Updating the {$scheduleTable} database table...<p>";

        $log = '';

        $this->execute("TRUNCATE TABLE {$scheduleTable}", '');
        $log .= "TRUNCATE TABLE {$scheduleTable}<p>";

        $this->preloadTeamNameMap();

        $ibl5RootRaw = defined('IBL5_ROOT') ? IBL5_ROOT : null;
        $ibl5Root = is_string($ibl5RootRaw) ? $ibl5RootRaw : '.';
        $filePrefix = $this->leagueContext !== null ? $this->leagueContext->getFilePrefix() : 'IBL5';
        $schFilePath = $ibl5Root . '/' . $filePrefix . '.sch';

        $games = SchFileParser::parseFile($schFilePath);

        $playoffMatchups = $this->getPlayoffMatchups();

        $currentDateSlot = -1;
        /** @var array{month: int, day: int}|null $currentMonthDay */
        $currentMonthDay = null;

        /** @var array<string, array{date: string|null, year: int|null, month: int|null}> $dateCache */
        $dateCache = [];

        foreach ($games as $game) {
            // Cache the month/day lookup when we move to a new date slot
            if ($game['date_slot'] !== $currentDateSlot) {
                $currentDateSlot = $game['date_slot'];
                $currentMonthDay = SchFileParser::dateSlotToMonthDay($game['date_slot']);
            }

            if ($currentMonthDay === null) {
                continue;
            }

            // Determine if this specific game is a playoff game (per-game, not per-day)
            $monthOverride = null;
            $monthOffset = intdiv($game['date_slot'], SchFileParser::DAYS_PER_MONTH);
            if ($monthOffset === 6 && $playoffMatchups !== []) {
                $pairKey = $game['visitor'] . '-' . $game['home'];
                if (isset($playoffMatchups[$pairKey])) {
                    $monthOverride = \Season::IBL_PLAYOFF_MONTH;
                }
            }

            // Build and cache the date string for this month/day/override combination
            $cacheKey = $currentMonthDay['month'] . '-' . $currentMonthDay['day'] . '-' . ($monthOverride ?? 0);
            if (!isset($dateCache[$cacheKey])) {
                $dateString = $this->buildDateString($currentMonthDay['month'], $currentMonthDay['day'], $monthOverride);
                $fullDate = $this->extractDate($dateString);
                $dateCache[$cacheKey] = [
                    'date' => $fullDate['date'] ?? null,
                    'year' => $fullDate['year'] ?? null,
                    'month' => $fullDate['month'] ?? null,
                ];
            }

            $date = $dateCache[$cacheKey]['date'];
            $year = $dateCache[$cacheKey]['year'];
            $month = $dateCache[$cacheKey]['month'];

            if ($date === null || $year === null) {
                continue;
            }

            // HEAT phase: only include games from the HEAT month
            if ($this->season->phase === "HEAT" && $month !== \Season::IBL_HEAT_MONTH) {
                continue;
            }

            // Compute BoxID: real for played games, placeholder for unplayed
            if ($game['played']) {
                $boxID = SchFileParser::computeBoxId($game['date_slot'], $game['game_index']);
            } else {
                $boxID = self::UNPLAYED_BOX_ID;
            }

            $visitorTID = $game['visitor'];
            $homeTID = $game['home'];
            $vScore = $game['visitor_score'];
            $hScore = $game['home_score'];

            $uuid = UuidGenerator::generateUuid();

            $visitorName = $this->getTeamNameById($visitorTID);
            $homeName = $this->getTeamNameById($homeTID);

            try {
                $this->execute(
                    "INSERT INTO {$scheduleTable} (
                        Year,
                        BoxID,
                        Date,
                        Visitor,
                        Vscore,
                        Home,
                        Hscore,
                        uuid
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    "iisiiiis",
                    $year,
                    $boxID,
                    $date,
                    $visitorTID,
                    $vScore,
                    $homeTID,
                    $hScore,
                    $uuid
                );
                $log .= "Inserted game: {$visitorName} @ {$homeName} on {$date}<br>";
            } catch (\Exception $e) {
                $errorMessage = "Failed to insert schedule data for game between {$visitorName} and {$homeName}: " . $e->getMessage();
                error_log("[ScheduleUpdater] Database insert error: {$errorMessage}");
                echo "<b><font color=red>Script Error: Failed to insert schedule data for game between {$visitorName} and {$homeName}.</font></b>";
                throw new \RuntimeException($errorMessage, 1002);
            }
        }

        \UI::displayDebugOutput($log, "{$scheduleTable} SQL Queries");

        echo "The {$scheduleTable} database table has been updated.<p>";
    }
}
