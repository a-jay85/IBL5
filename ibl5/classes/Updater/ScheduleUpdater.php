<?php

declare(strict_types=1);

namespace Updater;

use League\LeagueContext;
use Utilities\UuidGenerator;
use Utilities\SchFileParser;
use Utilities\DateParser;

class ScheduleUpdater extends \BaseMysqliRepository {
    private \Season $season;
    private ?LeagueContext $leagueContext;

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
        parent::__construct($db);
        $this->season = $season;
        $this->leagueContext = $leagueContext;
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
     * Resolve a table name through LeagueContext (if set), else return as-is
     */
    private function resolveTable(string $iblTableName): string
    {
        return $this->leagueContext !== null
            ? $this->leagueContext->getTableName($iblTableName)
            : $iblTableName;
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
     */
    private function buildDateString(int $month, int $day): string
    {
        $monthName = self::MONTH_NAMES[$month];

        return "{$monthName} {$day}, 2000";
    }

    /**
     * Get team name by ID for logging purposes.
     */
    private function getTeamNameById(int $teamId): string
    {
        return $this->teamIdToNameMap[$teamId] ?? "Team #{$teamId}";
    }

    public function update(): void {
        $scheduleTable = $this->resolveTable('ibl_schedule');

        echo "Updating the {$scheduleTable} database table...<p>";

        $log = '';

        $this->execute("TRUNCATE TABLE {$scheduleTable}", '');
        $log .= "TRUNCATE TABLE {$scheduleTable}<p>";

        $this->preloadTeamNameMap();

        $documentRootRaw = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $documentRoot = is_string($documentRootRaw) ? $documentRootRaw : '';
        $schFilePath = $documentRoot . '/ibl5/IBL5.sch';

        $games = SchFileParser::parseFile($schFilePath);

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
                    $dateString = $this->buildDateString($monthDay['month'], $monthDay['day']);
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
