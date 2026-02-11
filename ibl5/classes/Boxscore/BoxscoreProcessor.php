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
     * @see BoxscoreProcessorInterface::processAllStarGames()
     */
    public function processAllStarGames(
        string $filePath,
        int $seasonEndingYear,
        ?string $allStarAwayName = null,
        ?string $allStarHomeName = null,
    ): array {
        /** @var list<string> $messages */
        $messages = [];

        $operatingSeasonEndingYear = $seasonEndingYear > 0 ? $seasonEndingYear : $this->season->endingYear;

        // Check if regular season has progressed past All-Star Weekend
        $lastBoxScoreDate = $this->season->getLastBoxScoreDate();
        $allStarCutoff = $operatingSeasonEndingYear . '-02-04';

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

        // Second request (team names provided): file is a single All-Star game line
        if ($allStarAwayName !== null && $allStarHomeName !== null) {
            $allStarLine = fgets($scoFile, 2001);
            fclose($scoFile);

            if ($allStarLine !== false && trim(substr($allStarLine, 0, 58)) !== '') {
                $this->processAllStarGame(
                    $allStarLine,
                    $operatingSeasonEndingYear,
                    $allStarAwayName,
                    $allStarHomeName,
                    $messages,
                );
            }

            return [
                'success' => true,
                'messages' => $messages,
            ];
        }

        // First request: read both blocks from the full .sco file
        // Block 0: Rising Stars Game (bytes 0–1999)
        $risingStarsLine = fgets($scoFile, 2001);
        // Block 1: All-Star Game (bytes 2000–3999)
        $allStarLine = fgets($scoFile, 2001);

        fclose($scoFile);

        // Process Rising Stars Game
        if ($risingStarsLine !== false && trim(substr($risingStarsLine, 0, 58)) !== '') {
            $this->processRisingStarsGame($risingStarsLine, $operatingSeasonEndingYear, $messages);
        }

        // Process All-Star Game
        if ($allStarLine !== false && trim(substr($allStarLine, 0, 58)) !== '') {
            $allStarResult = $this->processAllStarGame(
                $allStarLine,
                $operatingSeasonEndingYear,
                null,
                null,
                $messages,
            );

            if ($allStarResult !== null) {
                // Pending prompt needed — return with prompt data
                return $allStarResult;
            }
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
            $seasonEndingYear . '-02-02',
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
     * @param string $line 2000-byte game line
     * @param int $seasonEndingYear Season ending year
     * @param string|null $awayName Custom away team name (null = prompt needed)
     * @param string|null $homeName Custom home team name (null = prompt needed)
     * @param list<string> $messages Log messages (modified by reference)
     * @return array{success: bool, messages: list<string>, allStarPending: bool, awayLeadingScorer: string, homeLeadingScorer: string, allStarRawData: string, seasonEndingYear: int}|null Prompt data if names needed, null if processed
     */
    private function processAllStarGame(
        string $line,
        int $seasonEndingYear,
        ?string $awayName,
        ?string $homeName,
        array &$messages,
    ): ?array {
        $gameDate = $seasonEndingYear . '-02-03';

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
                    // Outcome (a): scores match — skip
                    $messages[] = 'All-Star Game: already exists with matching scores, skipped.';
                    return null;
                }

                // Outcome (b): scores differ — read existing names before deleting
                $savedAwayName = $existingNames['awayName'];
                $savedHomeName = $existingNames['homeName'];

                $this->repository->deleteTeamBoxscoresByGame($gameDate, self::ALL_STAR_VISITOR_TID, self::ALL_STAR_HOME_TID, 1);
                $this->repository->deletePlayerBoxscoresByGame($gameDate, self::ALL_STAR_VISITOR_TID, self::ALL_STAR_HOME_TID);

                $linesProcessed = $this->processGameLine($line, $boxscoreGameInfo, $savedAwayName, $savedHomeName);
                if ($linesProcessed > 0) {
                    $messages[] = "All-Star Game: updated with existing team names ({$linesProcessed} lines).";
                }

                return null;
            }
        }

        // Outcome (c): game not in DB — check if names were provided
        if ($awayName !== null && $homeName !== null) {
            // Second request: names provided, insert
            $upsertAction = $this->processGameUpsert($boxscoreGameInfo);
            if ($upsertAction === 'skip') {
                $messages[] = 'All-Star Game: already exists, skipped.';
                return null;
            }

            $linesProcessed = $this->processGameLine($line, $boxscoreGameInfo, $awayName, $homeName);
            if ($linesProcessed > 0) {
                $action = $upsertAction === 'update' ? 'updated' : 'inserted';
                $messages[] = "All-Star Game: {$action} as {$awayName} vs {$homeName} ({$linesProcessed} lines).";
            }

            return null;
        }

        // First request: calculate leading scorers and return prompt data
        $awayLeadingScorer = $this->findLeadingScorer($line, 0, 14);
        $homeLeadingScorer = $this->findLeadingScorer($line, 15, 29);

        return [
            'success' => true,
            'messages' => $messages,
            'allStarPending' => true,
            'awayLeadingScorer' => $awayLeadingScorer,
            'homeLeadingScorer' => $homeLeadingScorer,
            'allStarRawData' => base64_encode($line),
            'seasonEndingYear' => $seasonEndingYear,
        ];
    }

    /**
     * Find the leading scorer among players in a range of the 30-player array
     *
     * @param string $line 2000-byte game line
     * @param int $startIndex First player index (inclusive)
     * @param int $endIndex Last player index (inclusive)
     * @return string Leading scorer name
     */
    private function findLeadingScorer(string $line, int $startIndex, int $endIndex): string
    {
        $maxPoints = -1;
        $leadingScorerName = 'Unknown';

        for ($i = $startIndex; $i <= $endIndex; $i++) {
            $x = $i * 53;
            $playerInfoLine = substr($line, 58 + $x, 53);

            $playerID = trim(substr($playerInfoLine, 18, 6));
            if ($playerID === '' || (int) $playerID === 0) {
                continue;
            }

            $fgm = (int) substr($playerInfoLine, 26, 2);
            $ftm = (int) substr($playerInfoLine, 31, 2);
            $tpm = (int) substr($playerInfoLine, 35, 2);
            // FGM includes 3-pointers as 2-point FGs; TGM adds the extra point
            $points = ($fgm * 2) + $ftm + $tpm;

            if ($points > $maxPoints) {
                $maxPoints = $points;
                $name = trim(substr($playerInfoLine, 0, 16));
                $converted = mb_convert_encoding($name, 'UTF-8', 'ISO-8859-1');
                $leadingScorerName = $converted !== false ? $converted : $name;
            }
        }

        return $leadingScorerName;
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
