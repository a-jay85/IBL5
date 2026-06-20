<?php

declare(strict_types=1);

namespace Updater;

use League\League;
use League\LeagueContext;
use Updater\Contracts\JsbSourceResolverInterface;
use Utilities\UuidGenerator;
use JsbParser\SchFileParser;
use Security\HtmlSanitizer;
use Season\Season;

class ScheduleUpdater extends \BaseMysqliRepository {
    private Season $season;

    /** @var array<int, string> Team ID to name lookup (for logging) */
    private array $teamIdToNameMap = [];

    /** @var array<string, int> Team name to ID lookup (for Schedule.htm parsing) */
    private array $teamNameToIdMap = [];

    /** @var array<int, true> Olympics real-team IDs (empty for IBL — all teams pass) */
    private array $realTeamIds = [];

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

    private ?JsbSourceResolverInterface $sourceResolver;

    private string $basePath;

    /**
     * Optional PSR-3 logger. When null, falls back to LoggerFactory::getChannel('db').
     */
    private \Psr\Log\LoggerInterface $channelLogger;

    public function __construct(\mysqli $db, Season $season, ?LeagueContext $leagueContext = null, ?JsbSourceResolverInterface $sourceResolver = null, ?string $basePath = null, ?\Psr\Log\LoggerInterface $logger = null) {
        parent::__construct($db, $leagueContext);
        $this->season = $season;
        $this->sourceResolver = $sourceResolver;
        $this->basePath = $basePath ?? \Bootstrap\AppPaths::root();
        $this->channelLogger = $logger ?? \Logging\LoggerFactory::getChannel('db');
    }

    /**
     * @return array{date: string, year: int, month: int, day: int}|null
     */
    protected function extractDate(string $rawDate): ?array {
        $currentLeague = $this->leagueContext !== null
            ? $this->leagueContext->getCurrentLeague()
            : 'IBL';

        if ($rawDate === '') {
            return null;
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
        $isOlympics = $this->leagueContext !== null && $this->leagueContext->isOlympics();

        if ($isOlympics) {
            /** @var list<array{team_name: string, teamid: int, is_real_team: int}> $rows */
            $rows = $this->fetchAll(
                "SELECT team_name, teamid, is_real_team FROM `ibl_team_info`",
                "",
            );
        } else {
            /** @var list<array{team_name: string, teamid: int}> $rows */
            $rows = $this->fetchAll(
                "SELECT team_name, teamid FROM `ibl_team_info` WHERE teamid BETWEEN 1 AND ?",
                "i",
                League::MAX_REAL_TEAMID,
            );
        }

        foreach ($rows as $row) {
            $this->teamIdToNameMap[$row['teamid']] = $row['team_name'];
            $this->teamNameToIdMap[$row['team_name']] = $row['teamid'];
        }

        if ($isOlympics) {
            foreach ($rows as $row) {
                if ($row['is_real_team'] === 1) {
                    $this->realTeamIds[$row['teamid']] = true;
                }
            }
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

    private function isRealTeamGame(int $visitorId, int $homeId): bool
    {
        if ($this->realTeamIds === []) {
            return true;
        }
        return isset($this->realTeamIds[$visitorId]) && isset($this->realTeamIds[$homeId]);
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
    private function insertPlayoffGamesFromScheduleHtm(): string
    {
        $ibl5Root = $this->basePath;
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

            if (!$this->isRealTeamGame($visitor_teamid, $home_teamid)) {
                continue;
            }

            $boxId = $game['played'] && $game['box_id'] !== null
                ? $game['box_id']
                : self::UNPLAYED_BOX_ID;

            $uuid = UuidGenerator::generateUuid();

            $this->execute(
                "INSERT INTO `ibl_schedule` (
                    season_year, box_id, game_date, visitor_teamid, visitor_score, home_teamid, home_score, uuid
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                "iisiiiis",
                $this->season->endingYear,
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
        echo "Updating the `ibl_schedule` database table...<p>";

        $log = '';

        // Rebuild the schedule atomically: a DELETE followed by row-by-row
        // INSERTs is not safe to expose to concurrent readers. Without a
        // transaction, a page load mid-import (or an import that throws partway)
        // sees only the games inserted so far — the early season, since inserts
        // run in date order. Wrapping it commits the new schedule all at once
        // and rolls the DELETE back if any insert fails.
        $this->transactional(function () use (&$log): void {
            $this->execute("DELETE FROM `ibl_schedule`", '');
            $log .= "DELETE FROM `ibl_schedule`<p>";

            $this->preloadTeamNameMap();

            if ($this->sourceResolver !== null) {
                $schData = $this->sourceResolver->getContents('sch');
                if ($schData === null) {
                    throw new \RuntimeException('Schedule file not found via resolver');
                }
                $games = SchFileParser::parse($schData);
            } else {
                $ibl5Root = $this->basePath;
                $filePrefix = $this->leagueContext !== null ? $this->leagueContext->getFilePrefix() : 'IBL5';
                $schFilePath = $ibl5Root . '/' . $filePrefix . '.sch';
                $games = SchFileParser::parseFile($schFilePath);
            }

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
                if ($this->season->phase === "Preseason" && $month !== null
                    && $month !== Season::IBL_PRESEASON_MONTH && $month !== Season::IBL_HEAT_MONTH) {
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

                if (!$this->isRealTeamGame($visitor_teamid, $home_teamid)) {
                    continue;
                }

                $vScore = $game['visitor_score'];
                $hScore = $game['home_score'];

                $uuid = UuidGenerator::generateUuid();

                $visitorName = $this->getTeamNameById($visitor_teamid);
                $homeName = $this->getTeamNameById($home_teamid);

                try {
                    $this->execute(
                        "INSERT INTO `ibl_schedule` (
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
                        $this->season->endingYear,
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
                    $this->channelLogger->error('ScheduleUpdater database insert error', ['error' => $errorMessage]);
                    echo '<strong class="ibl-form-error">Script Error: Failed to insert schedule data for game between ' . HtmlSanitizer::e($visitorName) . ' and ' . HtmlSanitizer::e($homeName) . '.</strong>';
                    throw new \RuntimeException($errorMessage, 1002);
                }
            }

            $log .= $this->insertPlayoffGamesFromScheduleHtm();
        });

        \UI\DebugOutput::display($log, "`ibl_schedule` SQL Queries");

        echo "The `ibl_schedule` database table has been updated.<p>";
    }
}
