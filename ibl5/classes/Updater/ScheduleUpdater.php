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
     * Detect the first day within month offset 6 (April) that belongs to playoffs.
     *
     * Month offset 6 in the .sch file contains both the final regular season games
     * and playoff games. This method finds the boundary day — everything at or after
     * this day gets June dates instead of April dates.
     *
     * Strategy:
     * 1. Primary: Find the first empty-day gap in offset 6 (RS days are populated, playoff
     *    slots start empty until playoffs begin, creating a gap).
     * 2. Fallback (all played): Query ibl_box_scores for June games to identify which
     *    offset 6 days are playoffs.
     * 3. Final fallback: Query ibl_sim_dates for the last RS sim end date.
     *
     * @param list<array{date_slot: int, game_index: int, visitor: int, home: int, visitor_score: int, home_score: int, played: bool}> $games
     * @return int|null The first playoff day (1-based) within offset 6, or null if no boundary found
     */
    protected function detectPlayoffBoundaryDay(array $games): ?int
    {
        $offset6Start = 6 * SchFileParser::DAYS_PER_MONTH;
        $offset6End = $offset6Start + SchFileParser::DAYS_PER_MONTH - 1;

        // Collect days that have games in offset 6
        /** @var array<int, bool> $daysWithGames Maps day (1-based) to whether it has games */
        $daysWithGames = [];
        foreach ($games as $game) {
            if ($game['date_slot'] >= $offset6Start && $game['date_slot'] <= $offset6End) {
                $dayZeroBased = $game['date_slot'] - $offset6Start;
                $day = $dayZeroBased + 1;
                $daysWithGames[$day] = true;
            }
        }

        if ($daysWithGames === []) {
            return null;
        }

        ksort($daysWithGames);
        $populatedDays = array_keys($daysWithGames);

        // Primary: Find the first gap (empty day) between populated days.
        // RS days are contiguous; playoff days start after a gap.
        $lastDay = $populatedDays[0];
        for ($i = 1; $i < count($populatedDays); $i++) {
            $currentDay = $populatedDays[$i];
            // Check if there's a gap of empty days between consecutive populated days
            // But skip day gaps caused by invalid dates (e.g., April 31 doesn't exist)
            if ($currentDay > $lastDay + 1) {
                // Verify the gap isn't just from invalid dates (April only has 30 days)
                $gapHasValidDates = false;
                for ($d = $lastDay + 1; $d < $currentDay; $d++) {
                    if (checkdate(4, $d, 2000)) {
                        $gapHasValidDates = true;
                        break;
                    }
                }
                if ($gapHasValidDates) {
                    return $currentDay;
                }
            }
            $lastDay = $currentDay;
        }

        // Fallback: All offset 6 days are populated (no gap). Query box scores for June games.
        $boxScoresTeamsTable = $this->resolveTable('ibl_box_scores_teams');

        /** @var list<array{day_num: int}> $juneGameDays */
        $juneGameDays = $this->fetchAll(
            "SELECT DISTINCT DAY(bst.Date) AS day_num
             FROM {$boxScoresTeamsTable} bst
             WHERE bst.Date LIKE CONCAT(?, '-06-%')
             ORDER BY day_num ASC",
            'i',
            $this->season->endingYear
        );

        if ($juneGameDays !== []) {
            // The first June box score day corresponds to the first playoff day in offset 6.
            // Map: box scores have correct June dates, offset 6 games with the same matchups
            // are the playoff games.
            $firstJuneDay = $juneGameDays[0]['day_num'];
            return (int) $firstJuneDay;
        }

        // Final fallback: Query ibl_sim_dates for the last RS sim end date
        /** @var array{max_end: string|null}|null $simRow */
        $simRow = $this->fetchOne(
            "SELECT MAX(End_Date) AS max_end FROM ibl_sim_dates
             WHERE YEAR(End_Date) = ? AND MONTH(End_Date) <= 5",
            'i',
            $this->season->endingYear
        );

        if ($simRow !== null && $simRow['max_end'] !== null) {
            $lastRsDate = new \DateTime($simRow['max_end']);
            $lastRsDay = (int) $lastRsDate->format('j');
            return $lastRsDay + 1;
        }

        return null;
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

        $playoffBoundaryDay = $this->detectPlayoffBoundaryDay($games);
        $offset6Start = 6 * SchFileParser::DAYS_PER_MONTH;

        $currentDateSlot = -1;
        $date = null;
        $year = null;
        /** @var int|null $month */
        $month = null;

        foreach ($games as $game) {
            // Compute date when we move to a new date slot
            if ($game['date_slot'] !== $currentDateSlot) {
                $currentDateSlot = $game['date_slot'];
                $monthDay = SchFileParser::dateSlotToMonthDay($game['date_slot']);

                if ($monthDay !== null) {
                    // Detect if this game is in offset 6 and past the playoff boundary
                    $monthOverride = null;
                    $monthOffset = intdiv($game['date_slot'], SchFileParser::DAYS_PER_MONTH);
                    if ($monthOffset === 6 && $playoffBoundaryDay !== null) {
                        $dayInOffset = $game['date_slot'] - $offset6Start + 1;
                        if ($dayInOffset >= $playoffBoundaryDay) {
                            $monthOverride = \Season::IBL_PLAYOFF_MONTH;
                        }
                    }

                    $dateString = $this->buildDateString($monthDay['month'], $monthDay['day'], $monthOverride);
                    $fullDate = $this->extractDate($dateString);
                    $date = $fullDate['date'] ?? null;
                    $year = $fullDate['year'] ?? null;
                    $month = $fullDate['month'] ?? null;
                } else {
                    $date = null;
                    $year = null;
                    $month = null;
                }
            }

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
