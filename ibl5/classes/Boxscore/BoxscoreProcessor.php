<?php

declare(strict_types=1);

namespace Boxscore;

use Boxscore\Contracts\BoxscoreProcessorInterface;
use Player\PlayerStats;
use Utilities\UuidGenerator;

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
    /** @phpstan-var \mysqli */
    protected object $db;
    protected BoxscoreRepository $repository;
    protected \Season $season;

    /**
     * @phpstan-param \mysqli $db
     */
    public function __construct(object $db, ?BoxscoreRepository $repository = null, ?\Season $season = null)
    {
        $this->db = $db;
        $this->repository = $repository ?? new BoxscoreRepository($db);
        $this->season = $season ?? new \Season($db);
    }

    /**
     * @see BoxscoreProcessorInterface::processScoFile()
     */
    public function processScoFile(string $filePath, int $seasonEndingYear, string $seasonPhase): array
    {
        /** @var list<string> $messages */
        $messages = [];

        $operatingSeasonEndingYear = $seasonEndingYear > 0 ? $seasonEndingYear : $this->season->endingYear;
        $operatingSeasonPhase = $seasonPhase !== '' ? $seasonPhase : $this->season->phase;
        $operatingSeasonStartingYear = $operatingSeasonEndingYear - 1;

        $messages[] = "Parsing .sco file for the {$operatingSeasonStartingYear}-{$operatingSeasonEndingYear} {$operatingSeasonPhase}...";

        $scoFile = @fopen($filePath, 'rb');
        if ($scoFile === false) {
            return [
                'success' => false,
                'gamesInserted' => 0,
                'gamesUpdated' => 0,
                'gamesSkipped' => 0,
                'linesProcessed' => 0,
                'messages' => $messages,
                'error' => 'Failed to open .sco file',
            ];
        }

        fseek($scoFile, 1000000);

        $gamesInserted = 0;
        $gamesUpdated = 0;
        $gamesSkipped = 0;
        $linesProcessed = 0;

        while (!feof($scoFile)) {
            $line = fgets($scoFile, 2001);
            if ($line === false) {
                break;
            }

            $gameInfoLine = substr($line, 0, 58);
            $boxscoreGameInfo = \Boxscore::withGameInfoLine($gameInfoLine, $operatingSeasonEndingYear, $operatingSeasonPhase);

            $upsertAction = $this->processGameUpsert($boxscoreGameInfo);

            if ($upsertAction === 'skip') {
                $gamesSkipped++;
                continue;
            }

            $gameLinesProcessed = 0;

            for ($i = 0; $i < 30; $i++) {
                $x = $i * 53;
                $playerInfoLine = substr($line, 58 + $x, 53);
                /** @var PlayerStats $playerStats */
                $playerStats = PlayerStats::withBoxscoreInfoLine($this->db, $playerInfoLine);

                $name = mb_convert_encoding($playerStats->name, 'UTF-8', 'ISO-8859-1');
                if ($name === false) {
                    $name = $playerStats->name;
                }

                if ($name !== '') {
                    if ((int) $playerStats->playerID === 0) {
                        $this->repository->insertTeamBoxscore(
                            $boxscoreGameInfo->gameDate,
                            $name,
                            $boxscoreGameInfo->gameOfThatDay,
                            $boxscoreGameInfo->visitorTeamID,
                            $boxscoreGameInfo->homeTeamID,
                            (int) $boxscoreGameInfo->attendance,
                            (int) $boxscoreGameInfo->capacity,
                            (int) $boxscoreGameInfo->visitorWins,
                            (int) $boxscoreGameInfo->visitorLosses,
                            (int) $boxscoreGameInfo->homeWins,
                            (int) $boxscoreGameInfo->homeLosses,
                            (int) $boxscoreGameInfo->visitorQ1points,
                            (int) $boxscoreGameInfo->visitorQ2points,
                            (int) $boxscoreGameInfo->visitorQ3points,
                            (int) $boxscoreGameInfo->visitorQ4points,
                            (int) $boxscoreGameInfo->visitorOTpoints,
                            (int) $boxscoreGameInfo->homeQ1points,
                            (int) $boxscoreGameInfo->homeQ2points,
                            (int) $boxscoreGameInfo->homeQ3points,
                            (int) $boxscoreGameInfo->homeQ4points,
                            (int) $boxscoreGameInfo->homeOTpoints,
                            (int) $playerStats->gameFieldGoalsMade,
                            (int) $playerStats->gameFieldGoalsAttempted,
                            (int) $playerStats->gameFreeThrowsMade,
                            (int) $playerStats->gameFreeThrowsAttempted,
                            (int) $playerStats->gameThreePointersMade,
                            (int) $playerStats->gameThreePointersAttempted,
                            (int) $playerStats->gameOffensiveRebounds,
                            (int) $playerStats->gameDefensiveRebounds,
                            (int) $playerStats->gameAssists,
                            (int) $playerStats->gameSteals,
                            (int) $playerStats->gameTurnovers,
                            (int) $playerStats->gameBlocks,
                            (int) $playerStats->gamePersonalFouls,
                        );
                        $gameLinesProcessed++;
                    } else {
                        $playerUuid = UuidGenerator::generateUuid();
                        $this->repository->insertPlayerBoxscore(
                            $boxscoreGameInfo->gameDate,
                            $playerUuid,
                            $name,
                            $playerStats->position,
                            (int) $playerStats->playerID,
                            $boxscoreGameInfo->visitorTeamID,
                            $boxscoreGameInfo->homeTeamID,
                            (int) $playerStats->gameMinutesPlayed,
                            (int) $playerStats->gameFieldGoalsMade,
                            (int) $playerStats->gameFieldGoalsAttempted,
                            (int) $playerStats->gameFreeThrowsMade,
                            (int) $playerStats->gameFreeThrowsAttempted,
                            (int) $playerStats->gameThreePointersMade,
                            (int) $playerStats->gameThreePointersAttempted,
                            (int) $playerStats->gameOffensiveRebounds,
                            (int) $playerStats->gameDefensiveRebounds,
                            (int) $playerStats->gameAssists,
                            (int) $playerStats->gameSteals,
                            (int) $playerStats->gameTurnovers,
                            (int) $playerStats->gameBlocks,
                            (int) $playerStats->gamePersonalFouls,
                        );
                        $gameLinesProcessed++;
                    }
                }
            }

            // Only count the game if data was actually written to the DB
            if ($gameLinesProcessed > 0) {
                if ($upsertAction === 'update') {
                    $gamesUpdated++;
                } else {
                    $gamesInserted++;
                }
                $linesProcessed += $gameLinesProcessed;
            }
        }

        fclose($scoFile);

        $messages[] = "Number of .sco lines processed: {$linesProcessed}";
        $messages[] = "Games inserted: {$gamesInserted} | Games updated: {$gamesUpdated} | Games skipped: {$gamesSkipped}";

        $simDateMessages = $this->updateSimDates($operatingSeasonPhase);
        $messages = array_merge($messages, $simDateMessages);

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
     * Determine upsert action for a game and perform delete if updating
     *
     * @return string 'insert', 'skip', or 'update'
     */
    protected function processGameUpsert(\Boxscore $boxscoreGameInfo): string
    {
        $existingGame = $this->repository->findTeamBoxscore(
            $boxscoreGameInfo->gameDate,
            $boxscoreGameInfo->visitorTeamID,
            $boxscoreGameInfo->homeTeamID,
            $boxscoreGameInfo->gameOfThatDay
        );

        if ($existingGame === null) {
            return 'insert';
        }

        /** @var array{visitorQ1points: int, visitorQ2points: int, visitorQ3points: int, visitorQ4points: int, visitorOTpoints: int, homeQ1points: int, homeQ2points: int, homeQ3points: int, homeQ4points: int, homeOTpoints: int} $existingGame */
        if ($boxscoreGameInfo->scoresMatchDatabase($existingGame)) {
            return 'skip';
        }

        // Scores differ — delete old records, then re-insert
        $this->repository->deleteTeamBoxscoresByGame(
            $boxscoreGameInfo->gameDate,
            $boxscoreGameInfo->visitorTeamID,
            $boxscoreGameInfo->homeTeamID,
            $boxscoreGameInfo->gameOfThatDay
        );
        $this->repository->deletePlayerBoxscoresByGame(
            $boxscoreGameInfo->gameDate,
            $boxscoreGameInfo->visitorTeamID,
            $boxscoreGameInfo->homeTeamID
        );

        return 'update';
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

        if ($operatingSeasonPhase === 'Preseason') {
            $messages[] = 'Preseason box scores added. Sim Start/End Dates not updated during Preseason.';
            return $messages;
        }

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
