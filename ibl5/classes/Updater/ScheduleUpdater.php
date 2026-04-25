<?php

declare(strict_types=1);

namespace Updater;

use League\League;
use League\LeagueContext;
use Utilities\UuidGenerator;
use Utilities\SchFileParser;
use Utilities\ScheduleHtmParser;
use Utilities\DateParser;
use Utilities\HtmlSanitizer;
use Season\Season;

class ScheduleUpdater extends \BaseMysqliRepository {
    private Season $season;

    /** @var array<int, string> Team ID to name lookup (for logging) */
    private array $teamIdToNameMap = [];

    /** @var array<string, int> Team name to ID lookup (for Schedule.htm parsing) */
    private array $teamNameToIdMap = [];

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

    public function __construct(\mysqli $db, Season $season, ?LeagueContext $leagueContext = null) {
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
            $this->season->beginningYear = Season::IBL_PRESEASON_YEAR;
            $this->season->endingYear = Season::IBL_PRESEASON_YEAR + 1;
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
            League::MAX_REAL_TEAMID
        );

        foreach ($rows as $row) {
            $this->teamIdToNameMap[$row['teamid']] = $row['team_name'];
            $this->teamNameToIdMap[$row['team_name']] = $row['teamid'];
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

    /**
     * Insert playoff schedule entries from Schedule.htm.
     *
     * Playoff games are NOT stored in the .sch file — they exist only in
     * JSB's Schedule.htm HTML export. This parses both played games (with
     * scores) and upcoming/unplayed games (no scores yet).
     *
     * @return string Log of inserted playoff games
     */
    private function insertPlayoffGamesFromScheduleHtm(string $scheduleTable): string
    {
        $ibl5Root = \Bootstrap\AppPaths::root();
        $leagueDir = $this->leagueContext !== null ? $this->leagueContext->getCurrentLeague() : 'IBL';
        $scheduleHtmPath = $ibl5Root . '/ibl/' . $leagueDir . '/Schedule.htm';

        if (!file_exists($scheduleHtmPath)) {
            return "Schedule.htm not found at {$scheduleHtmPath} — skipping playoff games<br>";
        }

        $html = file_get_contents($scheduleHtmPath);
        if ($html === false) {
            return "Failed to read Schedule.htm — skipping playoff games<br>";
        }

        $playoffGames = ScheduleHtmParser::parsePlayoffGames($html);

        $log = '';

        foreach ($playoffGames as $game) {
            $fullDate = $this->extractDate($game['date_label']);
            if ($fullDate === null) {
                continue;
            }

            $visitor_teamid = $this->teamNameToIdMap[$game['visitor']] ?? null;
            $home_teamid = $this->teamNameToIdMap[$game['home']] ?? null;

            if ($visitor_teamid === null || $home_teamid === null) {
                $log .= "Unknown team in playoff game: {$game['visitor']} @ {$game['home']}<br>";
                continue;
            }

            $boxId = $game['played'] && $game['box_id'] !== null
                ? $game['box_id']
                : self::UNPLAYED_BOX_ID;

            $uuid = UuidGenerator::generateUuid();

            $this->execute(
                "INSERT INTO {$scheduleTable} (
                    season_year, box_id, game_date, visitor_teamid, visitor_score, home_teamid, home_score, uuid
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                "iisiiiis",
                $fullDate['year'],
                $boxId,
                $fullDate['date'],
                $visitor_teamid,
                $game['visitor_score'],
                $home_teamid,
                $game['home_score'],
                $uuid
            );
            $log .= "Inserted playoff game: {$game['visitor']} @ {$game['home']} on {$fullDate['date']}<br>";
        }

        return $log;
    }

    public function update(): void {
        $scheduleTable = $this->resolveTable('ibl_schedule');

        echo "Updating the {$scheduleTable} database table...<p>";

        $log = '';

        $this->execute("TRUNCATE TABLE {$scheduleTable}", '');
        $log .= "TRUNCATE TABLE {$scheduleTable}<p>";

        $this->preloadTeamNameMap();

        $ibl5Root = \Bootstrap\AppPaths::root();
        $filePrefix = $this->leagueContext !== null ? $this->leagueContext->getFilePrefix() : 'IBL5';
        $schFilePath = $ibl5Root . '/' . $filePrefix . '.sch';

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
            if ($this->season->phase === "HEAT" && $month !== Season::IBL_HEAT_MONTH) {
                continue;
            }

            // Compute BoxID: real for played games, placeholder for unplayed
            if ($game['played']) {
                $boxID = SchFileParser::computeBoxId($game['date_slot'], $game['game_index']);
            } else {
                $boxID = self::UNPLAYED_BOX_ID;
            }

            $visitor_teamid = $game['visitor'];
            $home_teamid = $game['home'];
            $vScore = $game['visitor_score'];
            $hScore = $game['home_score'];

            $uuid = UuidGenerator::generateUuid();

            $visitorName = $this->getTeamNameById($visitor_teamid);
            $homeName = $this->getTeamNameById($home_teamid);

            try {
                $this->execute(
                    "INSERT INTO {$scheduleTable} (
                        season_year,
                        box_id,
                        game_date,
                        visitor_teamid,
                        visitor_score,
                        home_teamid,
                        home_score,
                        uuid
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    "iisiiiis",
                    $year,
                    $boxID,
                    $date,
                    $visitor_teamid,
                    $vScore,
                    $home_teamid,
                    $hScore,
                    $uuid
                );
                $log .= "Inserted game: {$visitorName} @ {$homeName} on {$date}<br>";
            } catch (\Exception $e) {
                $errorMessage = "Failed to insert schedule data for game between {$visitorName} and {$homeName}: " . $e->getMessage();
                \Logging\LoggerFactory::getChannel('db')->error('ScheduleUpdater database insert error', ['error' => $errorMessage]);
                echo '<strong class="ibl-form-error">Script Error: Failed to insert schedule data for game between ' . HtmlSanitizer::e($visitorName) . ' and ' . HtmlSanitizer::e($homeName) . '.</strong>';
                throw new \RuntimeException($errorMessage, 1002);
            }
        }

        $log .= $this->insertPlayoffGamesFromScheduleHtm($scheduleTable);

        \UI\DebugOutput::display($log, "{$scheduleTable} SQL Queries");

        echo "The {$scheduleTable} database table has been updated.<p>";
    }
}
