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
    public function processScoFile(string $filePath, int $seasonEndingYear, string $seasonPhase, bool $skipSimDates = false): array
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
            ];
        }

        return $this->processScoData($data, $seasonEndingYear, $seasonPhase, $skipSimDates);
    }

    /**
     * @see BoxscoreProcessorInterface::processScoData()
     */
    public function processScoData(string $data, int $seasonEndingYear, string $seasonPhase, bool $skipSimDates = false): array
    {
        /** @var list<string> $messages */
        $messages = [];

        $operatingSeasonEndingYear = $seasonEndingYear > 0 ? $seasonEndingYear : $this->season->endingYear;
        $operatingSeasonPhase = $seasonPhase !== '' ? $seasonPhase : $this->season->phase;
        $operatingSeasonStartingYear = $operatingSeasonEndingYear - 1;

        $messages[] = "Parsing .sco file for the {$operatingSeasonStartingYear}-{$operatingSeasonEndingYear} {$operatingSeasonPhase}...";

        if (strlen($data) < ScoFileParser::HEADER_OFFSET_BYTES) {
            return [
                'success' => false,
                'gamesInserted' => 0,
                'gamesUpdated' => 0,
                'gamesSkipped' => 0,
                'linesProcessed' => 0,
                'messages' => $messages,
                'error' => 'Data too short for .sco format',
            ];
        }

        $offset = ScoFileParser::HEADER_OFFSET_BYTES;
        $gamesInserted = 0;
        $gamesUpdated = 0;
        $gamesSkipped = 0;
        $linesProcessed = 0;
        $league = $this->leagueContext !== null ? $this->leagueContext->getCurrentLeague() : 'ibl';

        while ($offset + ScoFileParser::RECORD_SIZE <= strlen($data)) {
            $line = substr($data, $offset, ScoFileParser::RECORD_SIZE);
            $offset += ScoFileParser::RECORD_SIZE;

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

            $totalGames = $gamesInserted + $gamesUpdated + $gamesSkipped;
            $this->progressReporter->report($totalGames);
        }

        $messages[] = "Number of .sco lines processed: {$linesProcessed}";
        $messages[] = "Games inserted: {$gamesInserted} | Games updated: {$gamesUpdated} | Games skipped: {$gamesSkipped}";

        if (!$skipSimDates) {
            $simDateMessages = $this->updateSimDates($operatingSeasonPhase);
            $messages = array_merge($messages, $simDateMessages);
        }

        return [
            'success' => true,
            'gamesInserted' => $gamesInserted,
            'gamesUpdated' => $gamesUpdated,
            'gamesSkipped' => $gamesSkipped,
            'linesProcessed' => $linesProcessed,
            'messages' => $messages,
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
