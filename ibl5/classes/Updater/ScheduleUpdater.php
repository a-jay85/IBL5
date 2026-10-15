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

class ScheduleUpdater extends \Database\BaseMysqliRepository {
    private Season $season;

    /** @var array<int, string> Team ID to name lookup (for logging) */
    private array $teamIdToNameMap = [];

    /** @var array<string, int> Team name to ID lookup (for Schedule.htm parsing) */
    private array $teamNameToIdMap = [];

    /** @var array<int, true> Olympics real-team IDs (empty for IBL — all teams pass) */
    private array $realTeamIds = [];

    private const UNPLAYED_BOX_ID = 100000;

    /** Season phase whose schedule rows exist ONLY in Schedule.htm. */
    private const PLAYOFF_PHASE = 'Playoffs';

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
     * Parse step: read Schedule.htm and return its playoff rows.
     *
     * Returns the skip-log string instead of rows when the file is missing or
     * unreadable outside the Playoffs phase, so the caller's early return keeps
     * its exact wording.
     *
     * @return list<array{date_label: string, visitor: string, home: string, visitor_score: int, home_score: int, box_id: int|null, played: bool}>|string
     *
     * @throws \RuntimeException When Schedule.htm cannot be read during the Playoffs phase
     */
    private function parsePlayoffGamesFromScheduleHtm(string $scheduleHtmPath, string $leagueDir): array|string
    {
        if (!file_exists($scheduleHtmPath)) {
            $this->channelLogger->warning('ScheduleUpdater could not read Schedule.htm — no playoff games imported', [
                'path' => $scheduleHtmPath,
                'reason' => 'missing',
                'league' => $leagueDir,
                'season_phase' => $this->season->phase,
                'season_ending_year' => $this->season->endingYear,
            ]);

            // Playoff rows exist ONLY in this file. During the Playoffs phase a missing
            // export means update() would commit a schedule with no postseason games,
            // and ScheduleMembershipGuard then rejects every real playoff boxscore as
            // "not in ibl_schedule". Throw so the enclosing transaction rolls back and
            // the previous schedule — playoff rows included — survives intact.
            if ($this->season->phase === self::PLAYOFF_PHASE) {
                throw new \RuntimeException(
                    "Schedule.htm not found at {$scheduleHtmPath} — refusing to rebuild `ibl_schedule` during the Playoffs phase without playoff games. Export Schedule.htm from JSB and re-run."
                );
            }

            return "Schedule.htm not found at {$scheduleHtmPath} — skipping playoff games<br>";
        }

        $html = file_get_contents($scheduleHtmPath);
        if ($html === false) {
            $this->channelLogger->warning('ScheduleUpdater could not read Schedule.htm — no playoff games imported', [
                'path' => $scheduleHtmPath,
                'reason' => 'unreadable',
                'league' => $leagueDir,
                'season_phase' => $this->season->phase,
                'season_ending_year' => $this->season->endingYear,
            ]);

            // Same rollback rationale as the missing-file branch above.
            if ($this->season->phase === self::PLAYOFF_PHASE) {
                throw new \RuntimeException(
                    "Failed to read Schedule.htm at {$scheduleHtmPath} — refusing to rebuild `ibl_schedule` during the Playoffs phase without playoff games."
                );
            }

            return "Failed to read Schedule.htm — skipping playoff games<br>";
        }

        return ScheduleHtmParser::parsePlayoffGames($html);
    }

    /**
     * Classify step: decide what the orchestrator does with one parsed row.
     *
     * Pure with respect to the database and the log: it reads the season, the
     * team map and the league context, and writes nothing.
     *
     * @param array{date_label: string, visitor: string, home: string, visitor_score: int, home_score: int, box_id: int|null, played: bool} $game
     * @return array{outcome: 'stale', post_year: int|null}
     *       |array{outcome: 'skip'}
     *       |array{outcome: 'unknown_team'}
     *       |array{outcome: 'insert', visitor_teamid: int, home_teamid: int, game_date: string, box_id: int}
     */
    private function classifyPlayoffGame(array $game): array
    {
        // Season guard: Schedule.htm is a JSB export that can be left over
        // from a prior season. Every row in a given export carries that
        // season's ENDING year in its "Post N YYYY" header, so a year
        // mismatch means the whole file is stale and its rows must not be
        // relabelled into the current season. This must run before
        // extractDate(), which discards the label's year by design
        // (required for regular-season rows).
        $postYear = null;
        if (preg_match('/(\d{4})\s*$/', $game['date_label'], $yearMatch) === 1) {
            $postYear = (int) $yearMatch[1];
        }

        if ($postYear !== $this->season->endingYear) {
            return ['outcome' => 'stale', 'post_year' => $postYear];
        }

        $fullDate = $this->extractDate($game['date_label']);
        if ($fullDate === null) {
            return ['outcome' => 'skip'];
        }

        $visitor_teamid = $this->teamNameToIdMap[$game['visitor']] ?? null;
        $home_teamid = $this->teamNameToIdMap[$game['home']] ?? null;

        if ($visitor_teamid === null || $home_teamid === null) {
            return ['outcome' => 'unknown_team'];
        }

        if (!$this->isRealTeamGame($visitor_teamid, $home_teamid)) {
            return ['outcome' => 'skip'];
        }

        $boxId = $game['played'] && $game['box_id'] !== null
            ? $game['box_id']
            : self::UNPLAYED_BOX_ID;

        return [
            'outcome' => 'insert',
            'visitor_teamid' => $visitor_teamid,
            'home_teamid' => $home_teamid,
            'game_date' => $fullDate['date'],
            'box_id' => $boxId,
        ];
    }

    /**
     * Insert step: write one classified playoff row to ibl_schedule.
     *
     * Argument order matches the column order of the INSERT and the
     * iisiiiis bind string: season_year(i) box_id(i) game_date(s)
     * visitor_teamid(i) visitor_score(i) home_teamid(i) home_score(i) uuid(s).
     */
    private function insertPlayoffGame(
        int $boxId,
        string $gameDate,
        int $visitorTeamId,
        int $visitorScore,
        int $homeTeamId,
        int $homeScore,
    ): void {
        $uuid = UuidGenerator::generateUuid();

        $this->execute(
            "INSERT INTO `ibl_schedule` (
                season_year, box_id, game_date, visitor_teamid, visitor_score, home_teamid, home_score, uuid
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            "iisiiiis",
            $this->season->endingYear,
            $boxId,
            $gameDate,
            $visitorTeamId,
            $visitorScore,
            $homeTeamId,
            $homeScore,
            $uuid
        );
    }

    /**
     * Insert playoff schedule entries from Schedule.htm.
     *
     * Playoff games are NOT stored in the .sch file — they exist only in
     * JSB's Schedule.htm HTML export. This parses both played games (with
     * scores) and upcoming/unplayed games (no scores yet).
     *
     * During the Playoffs phase an unreadable Schedule.htm is fatal: the postseason
     * rows have no other source, so continuing would commit a playoff-less schedule.
     *
     * @return string Log of inserted playoff games
     *
     * @throws \RuntimeException When Schedule.htm cannot be read, or contains only stale
     *                             rows from another season, during the Playoffs phase
     */
    private function insertPlayoffGamesFromScheduleHtm(): string
    {
        $ibl5Root = $this->basePath;
        $leagueDir = $this->leagueContext !== null ? $this->leagueContext->getCurrentLeague() : 'IBL';
        $scheduleHtmPath = $ibl5Root . '/ibl/' . $leagueDir . '/Schedule.htm';

        $playoffGames = $this->parsePlayoffGamesFromScheduleHtm($scheduleHtmPath, $leagueDir);
        if (is_string($playoffGames)) {
            return $playoffGames;
        }

        $log = '';
        $skippedCount = 0;
        $insertedCount = 0;
        /** @var array<int|string, true> $skippedYears */
        $skippedYears = [];

        foreach ($playoffGames as $game) {
            $classification = $this->classifyPlayoffGame($game);

            if ($classification['outcome'] === 'stale') {
                $skippedCount++;
                $skippedYears[$classification['post_year'] ?? 'unparseable'] = true;
                continue;
            }

            if ($classification['outcome'] === 'skip') {
                continue;
            }

            if ($classification['outcome'] === 'unknown_team') {
                $log .= "Unknown team in playoff game: {$game['visitor']} @ {$game['home']}<br>";
                continue;
            }

            $this->insertPlayoffGame(
                $classification['box_id'],
                $classification['game_date'],
                $classification['visitor_teamid'],
                $game['visitor_score'],
                $classification['home_teamid'],
                $game['home_score'],
            );
            $insertedCount++;
            $log .= "Inserted playoff game: {$game['visitor']} @ {$game['home']} on {$classification['game_date']}<br>";
        }

        if ($skippedCount > 0) {
            $years = implode(', ', array_map('strval', array_keys($skippedYears)));
            $message = "Skipped {$skippedCount} stale playoff row(s) from Schedule.htm year(s) {$years}"
                . " — current season ending year is {$this->season->endingYear}";
            $log .= $message . '<br>';
            $this->channelLogger->warning('ScheduleUpdater skipped stale playoff rows', [
                'skipped' => $skippedCount,
                'file_years' => array_keys($skippedYears),
                'season_ending_year' => $this->season->endingYear,
            ]);

            // A stale export is as destructive as a missing one: every row belongs to
            // another season, so the rebuild would commit a playoff-less schedule and
            // ScheduleMembershipGuard would reject every real playoff boxscore.
            // Requiring skippedCount > 0 keeps this off the legitimate zero-row case —
            // early in the Playoffs phase JSB has not seeded the bracket yet, so an
            // export with no playoff rows at all is expected and must not be fatal.
            if ($this->season->phase === self::PLAYOFF_PHASE && $insertedCount === 0) {
                throw new \RuntimeException(
                    "Schedule.htm at {$scheduleHtmPath} contains only stale playoff rows from year(s) {$years}"
                    . " — refusing to rebuild `ibl_schedule` during the Playoffs phase without playoff games"
                    . " for {$this->season->endingYear}. Export a current Schedule.htm from JSB and re-run."
                );
            }
        }

        return $log;
    }

    /**
     * Read the regular-season schedule from the injected resolver or, without one,
     * from <basePath>/<filePrefix>.sch.
     *
     * @return list<array{date_slot: int, game_index: int, visitor: int, home: int, visitor_score: int, home_score: int, played: bool}>
     */
    private function loadScheduledGames(): array
    {
        if ($this->sourceResolver !== null) {
            $schData = $this->sourceResolver->getContents('sch');
            if ($schData === null) {
                throw new \RuntimeException('Schedule file not found via resolver');
            }
            return SchFileParser::parse($schData);
        }

        $ibl5Root = $this->basePath;
        $filePrefix = $this->leagueContext !== null ? $this->leagueContext->getFilePrefix() : 'IBL5';
        $schFilePath = $ibl5Root . '/' . $filePrefix . '.sch';
        return SchFileParser::parseFile($schFilePath);
    }

    /**
     * Calendar date for a .sch date slot, phase-adjusted by extractDate().
     * Null when the slot does not map to a calendar day.
     *
     * @return array{date: string, year: int, month: int, day: int}|null
     */
    private function resolveSlotDate(int $dateSlot): ?array
    {
        $monthDay = SchFileParser::dateSlotToMonthDay($dateSlot);
        if ($monthDay === null) {
            return null;
        }

        return $this->extractDate($this->buildDateString($monthDay['month'], $monthDay['day']));
    }

    /**
     * True when a parsed .sch game must not be inserted: phase filters
     * (HEAT keeps only its month, Preseason keeps Preseason and HEAT months)
     * and the Olympics filler-team rule.
     *
     * @param array{date_slot: int, game_index: int, visitor: int, home: int, visitor_score: int, home_score: int, played: bool} $game
     */
    private function shouldSkipScheduledGame(array $game, int $month): bool
    {
        // HEAT phase: only include games from the HEAT month
        if ($this->season->phase === "HEAT" && $month !== Season::IBL_HEAT_MONTH) {
            return true;
        }

        // Preseason filtering
        if ($this->season->phase === "Preseason"
            && $month !== Season::IBL_PRESEASON_MONTH && $month !== Season::IBL_HEAT_MONTH) {
            return true;
        }

        // Olympics: skip games where either team is a filler (not a real team)
        return !$this->isRealTeamGame($game['visitor'], $game['home']);
    }

    /**
     * Inserts one regular-season .sch game and returns its debug-log line.
     *
     * @param array{date_slot: int, game_index: int, visitor: int, home: int, visitor_score: int, home_score: int, played: bool} $game
     * @throws \RuntimeException code 1002 when the insert fails
     */
    private function insertScheduledGame(array $game, string $date): string
    {
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
            return "Inserted game: {$visitorName} @ {$homeName} on {$date}<br>";
        } catch (\Exception $e) {
            $errorMessage = "Failed to insert schedule data for game between {$visitorName} and {$homeName}: " . $e->getMessage();
            $this->channelLogger->error('ScheduleUpdater database insert error', ['error' => $errorMessage]);
            echo '<strong class="ibl-form-error">Script Error: Failed to insert schedule data for game between ' . HtmlSanitizer::e($visitorName) . ' and ' . HtmlSanitizer::e($homeName) . '.</strong>';
            throw new \RuntimeException($errorMessage, 1002);
        }
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

            $currentDateSlot = null;
            $slotDate = null;
            foreach ($this->loadScheduledGames() as $game) {
                // Resolve the date when we move to a new date slot
                if ($game['date_slot'] !== $currentDateSlot) {
                    $currentDateSlot = $game['date_slot'];
                    $slotDate = $this->resolveSlotDate($game['date_slot']);
                }

                if ($slotDate === null || $this->shouldSkipScheduledGame($game, $slotDate['month'])) {
                    continue;
                }

                $log .= $this->insertScheduledGame($game, $slotDate['date']);
            }
            $log .= $this->insertPlayoffGamesFromScheduleHtm();
        });

        \UI\DebugOutput::display($log, "`ibl_schedule` SQL Queries");

        echo "The `ibl_schedule` database table has been updated.<p>";
    }
}
