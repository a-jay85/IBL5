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
    private const ALL_STAR_VISITOR_TID = 50;
    private const ALL_STAR_HOME_TID = 51;
    private const RISING_STARS_VISITOR_TID = 40;
    private const RISING_STARS_HOME_TID = 41;

    public const DEFAULT_AWAY_NAME = 'Team Away';
    public const DEFAULT_HOME_NAME = 'Team Home';

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
    public function processScoFile(string $filePath, int $seasonEndingYear, string $seasonPhase, bool $skipSimDates = false): array
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

            $gameLinesProcessed = $this->processGameLine($line, $boxscoreGameInfo);

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
        /** @var list<string> $messages */
        $messages = [];

        $operatingSeasonEndingYear = $seasonEndingYear > 0 ? $seasonEndingYear : $this->season->endingYear;

        // Check if regular season has progressed past All-Star Weekend
        $lastBoxScoreDate = $this->season->getLastBoxScoreDate();
        $allStarCutoff = sprintf('%d-%02d-%02d', $operatingSeasonEndingYear, \Season::IBL_ALL_STAR_MONTH, \Season::IBL_ALL_STAR_BREAK_END_DAY);

        if ($lastBoxScoreDate === '' || $lastBoxScoreDate <= $allStarCutoff) {
            return [
                'success' => true,
                'messages' => $messages,
                'skipped' => 'All-Star Weekend not yet reached',
            ];
        }

        $scoFile = @fopen($filePath, 'rb');
        if ($scoFile === false) {
            return [
                'success' => false,
                'messages' => ['Failed to open .sco file for All-Star games'],
            ];
        }

        fseek($scoFile, 0);

        // Block 0: Rising Stars Game (bytes 0–1999)
        $risingStarsLine = fgets($scoFile, 2001);
        // Block 1: All-Star Game (bytes 2000–3999)
        $allStarLine = fgets($scoFile, 2001);

        fclose($scoFile);

        // Process Rising Stars Game
        if ($risingStarsLine !== false && trim(substr($risingStarsLine, 0, 58)) !== '') {
            $this->processRisingStarsGame($risingStarsLine, $operatingSeasonEndingYear, $messages);
        }

        // Process All-Star Game (inserted with default placeholder names)
        if ($allStarLine !== false && trim(substr($allStarLine, 0, 58)) !== '') {
            $this->processAllStarGame($allStarLine, $operatingSeasonEndingYear, $messages);
        }

        return [
            'success' => true,
            'messages' => $messages,
        ];
    }

    /**
     * Process a single 2000-byte game line: insert team totals and player stats
     *
     * @param string $line The 2000-byte game line
     * @param \Boxscore $boxscoreGameInfo Parsed game info (with possible overrides)
     * @param string|null $visitorTeamName Override for visitor team-total name
     * @param string|null $homeTeamName Override for home team-total name
     * @return int Number of lines processed
     */
    private function processGameLine(
        string $line,
        \Boxscore $boxscoreGameInfo,
        ?string $visitorTeamName = null,
        ?string $homeTeamName = null,
    ): int {
        $gameLinesProcessed = 0;
        $visitorTeamTotalSeen = false;

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
                    // Team total row — apply name overrides
                    if (!$visitorTeamTotalSeen) {
                        $visitorTeamTotalSeen = true;
                        if ($visitorTeamName !== null) {
                            $name = $visitorTeamName;
                        }
                    } else {
                        if ($homeTeamName !== null) {
                            $name = $homeTeamName;
                        }
                    }

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
                    // Determine player's team ID based on position in 30-player array
                    // Players 0-14 are visitors, players 15-29 are home team
                    $playerTeamID = $i < 15 ? $boxscoreGameInfo->visitorTeamID : $boxscoreGameInfo->homeTeamID;
                    $this->repository->insertPlayerBoxscore(
                        $boxscoreGameInfo->gameDate,
                        $playerUuid,
                        $name,
                        $playerStats->position,
                        (int) $playerStats->playerID,
                        $boxscoreGameInfo->visitorTeamID,
                        $boxscoreGameInfo->homeTeamID,
                        $boxscoreGameInfo->gameOfThatDay,
                        (int) $boxscoreGameInfo->attendance,
                        (int) $boxscoreGameInfo->capacity,
                        (int) $boxscoreGameInfo->visitorWins,
                        (int) $boxscoreGameInfo->visitorLosses,
                        (int) $boxscoreGameInfo->homeWins,
                        (int) $boxscoreGameInfo->homeLosses,
                        $playerTeamID,
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

        return $gameLinesProcessed;
    }

    /**
     * Process the Rising Stars Game (Block 0)
     *
     * @param string $line 2000-byte game line
     * @param int $seasonEndingYear Season ending year
     * @param list<string> $messages Log messages (modified by reference)
     */
    private function processRisingStarsGame(string $line, int $seasonEndingYear, array &$messages): void
    {
        $gameInfoLine = substr($line, 0, 58);
        $boxscoreGameInfo = \Boxscore::withGameInfoLine($gameInfoLine, $seasonEndingYear, 'Regular Season/Playoffs');
        $boxscoreGameInfo->overrideGameContext(
            sprintf('%d-%02d-%02d', $seasonEndingYear, \Season::IBL_ALL_STAR_MONTH, \Season::IBL_RISING_STARS_GAME_DAY),
            self::RISING_STARS_VISITOR_TID,
            self::RISING_STARS_HOME_TID,
            1,
        );

        $upsertAction = $this->processGameUpsert($boxscoreGameInfo);

        if ($upsertAction === 'skip') {
            $messages[] = 'Rising Stars Game: already exists, skipped.';
            return;
        }

        $linesProcessed = $this->processGameLine($line, $boxscoreGameInfo, 'Rookies', 'Sophomores');

        if ($linesProcessed > 0) {
            $action = $upsertAction === 'update' ? 'updated' : 'inserted';
            $messages[] = "Rising Stars Game: {$action} ({$linesProcessed} lines).";
        }
    }

    /**
     * Process the All-Star Game (Block 1)
     *
     * Outcome A: scores match existing record — skip.
     * Outcome B: scores differ — re-insert preserving existing custom names.
     * Outcome C: new game — insert with default placeholder names.
     *
     * @param string $line 2000-byte game line
     * @param int $seasonEndingYear Season ending year
     * @param list<string> $messages Log messages (modified by reference)
     */
    private function processAllStarGame(
        string $line,
        int $seasonEndingYear,
        array &$messages,
    ): void {
        $gameDate = sprintf('%d-%02d-%02d', $seasonEndingYear, \Season::IBL_ALL_STAR_MONTH, \Season::IBL_ALL_STAR_GAME_DAY);

        $gameInfoLine = substr($line, 0, 58);
        $boxscoreGameInfo = \Boxscore::withGameInfoLine($gameInfoLine, $seasonEndingYear, 'Regular Season/Playoffs');
        $boxscoreGameInfo->overrideGameContext(
            $gameDate,
            self::ALL_STAR_VISITOR_TID,
            self::ALL_STAR_HOME_TID,
            1,
        );

        // Check if team names already exist in DB for this game
        $existingNames = $this->repository->findAllStarTeamNames($gameDate);

        if ($existingNames !== null) {
            // Names already set — check if scores match
            $existingGame = $this->repository->findTeamBoxscore(
                $gameDate,
                self::ALL_STAR_VISITOR_TID,
                self::ALL_STAR_HOME_TID,
                1
            );

            if ($existingGame !== null) {
                /** @var array{visitorQ1points: int, visitorQ2points: int, visitorQ3points: int, visitorQ4points: int, visitorOTpoints: int, homeQ1points: int, homeQ2points: int, homeQ3points: int, homeQ4points: int, homeOTpoints: int} $existingGame */
                if ($boxscoreGameInfo->scoresMatchDatabase($existingGame)) {
                    // Outcome A: scores match — skip
                    $messages[] = 'All-Star Game: already exists with matching scores, skipped.';
                    return;
                }

                // Outcome B: scores differ — read existing names before deleting
                $savedAwayName = $existingNames['awayName'];
                $savedHomeName = $existingNames['homeName'];

                $this->repository->deleteTeamBoxscoresByGame($gameDate, self::ALL_STAR_VISITOR_TID, self::ALL_STAR_HOME_TID, 1);
                $this->repository->deletePlayerBoxscoresByGame($gameDate, self::ALL_STAR_VISITOR_TID, self::ALL_STAR_HOME_TID);

                $linesProcessed = $this->processGameLine($line, $boxscoreGameInfo, $savedAwayName, $savedHomeName);
                if ($linesProcessed > 0) {
                    $messages[] = "All-Star Game: updated with existing team names ({$linesProcessed} lines).";
                }

                return;
            }
        }

        // Outcome C: game not in DB — insert with default placeholder names
        $upsertAction = $this->processGameUpsert($boxscoreGameInfo);
        if ($upsertAction === 'skip') {
            $messages[] = 'All-Star Game: already exists, skipped.';
            return;
        }

        $linesProcessed = $this->processGameLine(
            $line,
            $boxscoreGameInfo,
            self::DEFAULT_AWAY_NAME,
            self::DEFAULT_HOME_NAME,
        );
        if ($linesProcessed > 0) {
            $action = $upsertAction === 'update' ? 'updated' : 'inserted';
            $messages[] = "All-Star Game: {$action} ({$linesProcessed} lines).";
        }
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
        $scoresMatch = $boxscoreGameInfo->scoresMatchDatabase($existingGame);

        if ($scoresMatch) {
            // Scores match — but re-import if player records have NULL teamID
            $hasNullTeamId = $this->repository->hasNullTeamIdPlayerBoxscores(
                $boxscoreGameInfo->gameDate,
                $boxscoreGameInfo->visitorTeamID,
                $boxscoreGameInfo->homeTeamID
            );

            if (!$hasNullTeamId) {
                return 'skip';
            }
        }

        // Scores differ or player records need teamID fix — delete old records, then re-insert
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
