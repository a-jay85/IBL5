<?php

declare(strict_types=1);

namespace Boxscore;

use Boxscore\Contracts\BoxscoreProcessorInterface;
use Boxscore\Contracts\ProgressReporterInterface;
use Boxscore\FlushProgressReporter;
use JsbParser\ScoFileParser;
use League\LeagueContext;
use Season\Season;

/**
 * BoxscoreProcessor - Orchestrates .sco file parsing and boxscore insertion
 *
 * Handles the complete .sco file processing pipeline: parsing game/player data,
 * upsert logic (insert/skip/update), and sim date management.
 *
 * @see BoxscoreProcessorInterface
 */
class BoxscoreProcessor implements BoxscoreProcessorInterface
{
    public const DEFAULT_AWAY_NAME = 'Team Away';
    public const DEFAULT_HOME_NAME = 'Team Home';

    protected \mysqli $db;
    protected BoxscoreRepository $repository;
    protected Season $season;
    private ?LeagueContext $leagueContext;
    private ProgressReporterInterface $progressReporter;
    private RegularSeasonGameProcessor $regularSeasonProcessor;
    private RisingStarsGameProcessor $risingStarsProcessor;
    private AllStarGameProcessor $allStarProcessor;

    public function __construct(
        \mysqli $db,
        ?BoxscoreRepository $repository = null,
        ?Season $season = null,
        ?LeagueContext $leagueContext = null,
        ?ProgressReporterInterface $progressReporter = null,
        ?RegularSeasonGameProcessor $regularSeasonProcessor = null,
        ?RisingStarsGameProcessor $risingStarsProcessor = null,
        ?AllStarGameProcessor $allStarProcessor = null,
    ) {
        $this->db = $db;
        $this->leagueContext = $leagueContext;
        $this->repository = $repository ?? new BoxscoreRepository($db, $leagueContext);
        $this->season = $season ?? new Season($db);
        $this->progressReporter = $progressReporter ?? new FlushProgressReporter();
        $resolver = new GameUpsertResolver($this->repository);
        $writer = new GameLineWriter($db, $this->repository);
        $this->regularSeasonProcessor = $regularSeasonProcessor ?? new RegularSeasonGameProcessor($resolver, $writer);
        $this->risingStarsProcessor = $risingStarsProcessor ?? new RisingStarsGameProcessor($resolver, $writer);
        $this->allStarProcessor = $allStarProcessor ?? new AllStarGameProcessor($resolver, $writer, $this->repository);
    }

    /**
     * @see BoxscoreProcessorInterface::processScoFile()
     */
    public function processScoFile(string $filePath, int $seasonEndingYear, string $seasonPhase, bool $skipSimDates = false, ?string $sourceArchive = null): array
    {
        $data = @file_get_contents($filePath);
        if ($data === false) {
            return [
                'success' => false,
                'gamesInserted' => 0,
                'gamesUpdated' => 0,
                'gamesSkipped' => 0,
                'linesProcessed' => 0,
                'messages' => [],
                'error' => 'Failed to open .sco file',
                'gamesRejected' => 0,
                'rejectedGames' => [],
                'rejectsRecorded' => 0,
                'scheduleGuardEnabled' => true,
                'sourceArchive' => $sourceArchive,
            ];
        }

        return $this->processScoData($data, $seasonEndingYear, $seasonPhase, $skipSimDates, $sourceArchive ?? basename($filePath));
    }

    /**
     * @see BoxscoreProcessorInterface::processScoData()
     */
    public function processScoData(string $data, int $seasonEndingYear, string $seasonPhase, bool $skipSimDates = false, ?string $sourceArchive = null): array
    {
        /** @var list<string> $messages */
        $messages = [];

        $operatingSeasonEndingYear = $seasonEndingYear > 0 ? $seasonEndingYear : $this->season->endingYear;
        $operatingSeasonPhase = $seasonPhase !== '' ? $seasonPhase : $this->season->phase;
        $operatingSeasonStartingYear = $operatingSeasonEndingYear - 1;

        $messages[] = "Parsing .sco file for the {$operatingSeasonStartingYear}-{$operatingSeasonEndingYear} {$operatingSeasonPhase}...";

        $scheduleGuard = $this->makeScheduleGuard($operatingSeasonEndingYear);

        if (strlen($data) < ScoFileParser::HEADER_OFFSET_BYTES) {
            return [
                'success' => false,
                'gamesInserted' => 0,
                'gamesUpdated' => 0,
                'gamesSkipped' => 0,
                'linesProcessed' => 0,
                'messages' => $messages,
                'error' => 'Data too short for .sco format',
                'gamesRejected' => 0,
                'rejectedGames' => [],
                'operatingSeasonEndingYear' => $operatingSeasonEndingYear,
                'operatingSeasonPhase' => $operatingSeasonPhase,
                'rejectsRecorded' => 0,
                'scheduleGuardEnabled' => $scheduleGuard->isEnabled(),
                'sourceArchive' => $sourceArchive,
            ];
        }

        $offset = ScoFileParser::HEADER_OFFSET_BYTES;
        $gamesInserted = 0;
        $gamesUpdated = 0;
        $gamesSkipped = 0;
        $linesProcessed = 0;
        $league = $this->leagueContext !== null ? $this->leagueContext->getCurrentLeague() : 'ibl';

        $gamesRejected = 0;
        /** @var list<RejectedGame> $rejectedGames */
        $rejectedGames = [];

        if (!$scheduleGuard->isEnabled()) {
            $messages[] = "Schedule guard disabled: ibl_schedule has no rows for season {$operatingSeasonEndingYear}; importing without membership validation.";
        }

        // Detector B: tally games whose decoded date falls outside the schedule window.
        // Skip entirely when the schedule index is empty (same fail-open as isEnabled()).
        $window = $scheduleGuard->scheduleDateWindow();
        /** @var array<string, int> $outOfWindowDates date => count */
        $outOfWindowDates = [];

        $dataLength = strlen($data);

        // Bound on GAME_PAYLOAD_SIZE, not RECORD_SIZE: JSB omits the 352-byte trailing
        // padding on the last record it writes, so the final game is short. substr()
        // returns the short remainder and all 30 player slots still resolve within it.
        while ($offset + ScoFileParser::GAME_PAYLOAD_SIZE <= $dataLength) {
            $line = substr($data, $offset, ScoFileParser::RECORD_SIZE);
            $offset += ScoFileParser::RECORD_SIZE;

            $gameInfoLine = ScoFileParser::extractGameInfo($line);
            $boxscoreGameInfo = Boxscore::withGameInfoLine($gameInfoLine, $operatingSeasonEndingYear, $operatingSeasonPhase, $league);

            // Detector B: check this game's decoded date against the schedule window.
            // Tallied BEFORE the reject continue on purpose: a game outside the window
            // is almost always also absent from the schedule index, so the guard rejects
            // it first. Tallying only accepted games would make this detector unreachable,
            // because an accepted game is in the index and therefore inside [min, max].
            // Skip games that the guard exempts (All-Star/Rising Stars and off-schedule months)
            // so we do not re-break the February All-Star import the Phase 3 whitelist protects.
            // ISO dates compare correctly with plain string operators; no DateTime construction.
            if ($window !== null
                && !in_array($boxscoreGameInfo->visitor_teamid, ScheduleMembershipGuard::EXEMPT_TEAMIDS, true)
                && !in_array($boxscoreGameInfo->home_teamid, ScheduleMembershipGuard::EXEMPT_TEAMIDS, true)
                && !in_array((int) $boxscoreGameInfo->gameMonth, ScheduleMembershipGuard::OFF_SCHEDULE_MONTHS, true)
            ) {
                $gameDate = $boxscoreGameInfo->gameDate;
                if ($gameDate < $window[0] || $gameDate > $window[1]) {
                    $outOfWindowDates[$gameDate] = ($outOfWindowDates[$gameDate] ?? 0) + 1;
                }
            }

            $rejection = $scheduleGuard->evaluate($boxscoreGameInfo);

            if ($rejection !== null) {
                $gamesRejected++;
                $rejectedGames[] = $rejection;
                $this->progressReporter->report($gamesInserted + $gamesUpdated + $gamesSkipped + $gamesRejected);
                continue;
            }

            $result = $this->regularSeasonProcessor->process($line, $operatingSeasonEndingYear, $operatingSeasonPhase, $league);
            $upsertAction = $result['action'];

            if ($upsertAction === 'skip') {
                $gamesSkipped++;
                continue;
            }

            $gameLinesProcessed = $result['linesProcessed'];

            if ($gameLinesProcessed > 0) {
                if ($upsertAction === 'update') {
                    $gamesUpdated++;
                } else {
                    $gamesInserted++;
                }
                $linesProcessed += $gameLinesProcessed;
            }

            $totalGames = $gamesInserted + $gamesUpdated + $gamesSkipped + $gamesRejected;
            $this->progressReporter->report($totalGames);
        }

        $messages[] = "Number of .sco lines processed: {$linesProcessed}";
        $messages[] = "Games inserted: {$gamesInserted} | Games updated: {$gamesUpdated} | Games skipped: {$gamesSkipped} | Games rejected: {$gamesRejected}";

        if ($gamesRejected > 0) {
            $messages[] = "{$gamesRejected} game(s) rejected: not on the {$operatingSeasonEndingYear} schedule, or duplicate of an existing game at a different game_of_that_day.";
        }

        if ($outOfWindowDates !== [] && $window !== null) {
            ksort($outOfWindowDates);
            $messages[] = sprintf(
                'WARNING: %d game(s) on %d date(s) fall outside the %d schedule window %s..%s (earliest %s, latest %s). Wrong source archive?',
                array_sum($outOfWindowDates),
                count($outOfWindowDates),
                $operatingSeasonEndingYear,
                $window[0],
                $window[1],
                (string) array_key_first($outOfWindowDates),
                (string) array_key_last($outOfWindowDates),
            );
        }

        if (!$skipSimDates) {
            $simDateMessages = $this->updateSimDates($operatingSeasonPhase);
            $messages = array_merge($messages, $simDateMessages);
        }

        $rejectsRecorded = $this->repository->recordRejectedGames(
            $operatingSeasonEndingYear,
            $rejectedGames,
            $sourceArchive,
        );

        return [
            'success' => true,
            'gamesInserted' => $gamesInserted,
            'gamesUpdated' => $gamesUpdated,
            'gamesSkipped' => $gamesSkipped,
            'linesProcessed' => $linesProcessed,
            'messages' => $messages,
            'gamesRejected' => $gamesRejected,
            'rejectedGames' => $rejectedGames,
            'operatingSeasonEndingYear' => $operatingSeasonEndingYear,
            'operatingSeasonPhase' => $operatingSeasonPhase,
            'outOfWindowGames' => array_sum($outOfWindowDates),
            'rejectsRecorded' => $rejectsRecorded,
            'scheduleGuardEnabled' => $scheduleGuard->isEnabled(),
            'sourceArchive' => $sourceArchive,
        ];
    }

    /**
     * @see BoxscoreProcessorInterface::processAllStarGames()
     */
    public function processAllStarGames(
        string $filePath,
        int $seasonEndingYear,
    ): array {
        $data = @file_get_contents($filePath);
        if ($data === false) {
            return [
                'success' => false,
                'messages' => ['Failed to open .sco file for All-Star games'],
            ];
        }

        return $this->processAllStarGamesData($data, $seasonEndingYear);
    }

    /**
     * @see BoxscoreProcessorInterface::processAllStarGamesData()
     */
    public function processAllStarGamesData(
        string $data,
        int $seasonEndingYear,
    ): array {
        /** @var list<string> $messages */
        $messages = [];

        if ($this->leagueContext !== null && $this->leagueContext->isOlympics()) {
            return [
                'success' => true,
                'messages' => ['All-Star games skipped (Olympics context).'],
                'skipped' => 'Olympics context',
            ];
        }

        $operatingSeasonEndingYear = $seasonEndingYear > 0 ? $seasonEndingYear : $this->season->endingYear;

        $lastBoxScoreDate = $this->season->getLastBoxScoreDate();
        $allStarCutoff = sprintf('%d-%02d-%02d', $operatingSeasonEndingYear, Season::IBL_ALL_STAR_MONTH, Season::IBL_ALL_STAR_BREAK_END_DAY);

        if ($lastBoxScoreDate === '' || $lastBoxScoreDate <= $allStarCutoff) {
            return [
                'success' => true,
                'messages' => $messages,
                'skipped' => 'All-Star Weekend not yet reached',
            ];
        }

        $risingStarsLine = strlen($data) >= ScoFileParser::RECORD_SIZE
            ? substr($data, 0, ScoFileParser::RECORD_SIZE)
            : null;
        $allStarLine = strlen($data) >= ScoFileParser::RECORD_SIZE * 2
            ? substr($data, ScoFileParser::RECORD_SIZE, ScoFileParser::RECORD_SIZE)
            : null;

        $league = $this->leagueContext !== null ? $this->leagueContext->getCurrentLeague() : 'ibl';

        if ($risingStarsLine !== null && trim(ScoFileParser::extractGameInfo($risingStarsLine)) !== '') {
            $result = $this->risingStarsProcessor->process($risingStarsLine, $operatingSeasonEndingYear, 'Regular Season/Playoffs', $league);
            $messages = array_merge($messages, $result['messages']);
        }

        if ($allStarLine !== null && trim(ScoFileParser::extractGameInfo($allStarLine)) !== '') {
            $result = $this->allStarProcessor->process($allStarLine, $operatingSeasonEndingYear, 'Regular Season/Playoffs', $league);
            $messages = array_merge($messages, $result['messages']);
        }

        return [
            'success' => true,
            'messages' => $messages,
        ];
    }

    /**
     * Factory for the schedule-membership guard — overridable in tests.
     */
    protected function makeScheduleGuard(int $seasonEndingYear): ScheduleMembershipGuard
    {
        return ScheduleMembershipGuard::fromRepository($this->repository, $seasonEndingYear);
    }

    /**
     * Update sim dates after processing boxscores
     *
     * @return list<string> Log messages
     */
    protected function updateSimDates(string $operatingSeasonPhase): array
    {
        /** @var list<string> $messages */
        $messages = [];

        $newSimEndDate = $this->season->getLastBoxScoreDate();

        if ($this->season->lastSimEndDate !== '') {
            if ($this->season->lastSimEndDate !== $newSimEndDate) {
                $dateObjectForNewSimEndDate = date_create($this->season->lastSimEndDate);
                if ($dateObjectForNewSimEndDate !== false) {
                    date_modify($dateObjectForNewSimEndDate, '+1 day');
                    $newSimStartDate = date_format($dateObjectForNewSimEndDate, 'Y-m-d');
                } else {
                    $newSimStartDate = $newSimEndDate;
                }

                $newSimNumber = $this->season->lastSimNumber + 1;

                $result = $this->season->setLastSimDatesArray(
                    (string) $newSimNumber,
                    $newSimStartDate,
                    $newSimEndDate
                );

                if ($result > 0) {
                    $messages[] = "Added box scores from {$newSimStartDate} through {$newSimEndDate}.";
                } else {
                    $messages[] = 'Failed to insert sim dates.';
                }
            } else {
                $messages[] = "Looks like new box scores haven't been added. Sim Start/End Dates will stay set to {$this->season->lastSimStartDate} and {$this->season->lastSimEndDate}.";
            }
        } else {
            $newSimStartDate = $this->season->getFirstBoxScoreDate();
            $result = $this->season->setLastSimDatesArray('1', $newSimStartDate, $newSimEndDate);

            if ($result > 0) {
                $messages[] = "Added box scores from {$newSimStartDate} through {$newSimEndDate}.";
            } else {
                $messages[] = 'Failed to insert initial sim dates.';
            }
        }

        return $messages;
    }
}
