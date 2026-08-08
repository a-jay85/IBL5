<?php

declare(strict_types=1);

namespace Boxscore;

use JsbParser\ScoFileParser;
use Player\Stats\PlayerStats;
use Utilities\UuidGenerator;

/**
 * GameLineWriter - Inserts team totals and player stats for a single 2000-byte game line
 */
class GameLineWriter
{
    public function __construct(
        private \mysqli $db,
        private BoxscoreRepository $repository,
    ) {
    }

    /**
     * Process a single 2000-byte game line: insert team totals and player stats
     *
     * @param string $line The 2000-byte game line
     * @param Boxscore $boxscoreGameInfo Parsed game info (with possible overrides)
     * @param string|null $visitorTeamName Override for visitor team-total name
     * @param string|null $homeTeamName Override for home team-total name
     * @return int Number of lines processed
     */
    public function write(
        string $line,
        Boxscore $boxscoreGameInfo,
        ?string $visitorTeamName = null,
        ?string $homeTeamName = null,
    ): int {
        $gameLinesProcessed = 0;
        $visitorTeamTotalSeen = false;

        for ($i = 0; $i < ScoFileParser::PLAYER_SLOT_COUNT; $i++) {
            $playerInfoLine = ScoFileParser::extractPlayerSlot($line, $i);
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

                    $this->repository->insertTeamBoxscore([
                        'game_date' => $boxscoreGameInfo->gameDate,
                        'name' => $name,
                        'game_of_that_day' => $boxscoreGameInfo->game_of_that_day,
                        'visitor_teamid' => $boxscoreGameInfo->visitor_teamid,
                        'home_teamid' => $boxscoreGameInfo->home_teamid,
                        'attendance' => (int) $boxscoreGameInfo->attendance,
                        'capacity' => (int) $boxscoreGameInfo->capacity,
                        'visitor_wins' => (int) $boxscoreGameInfo->visitor_wins,
                        'visitor_losses' => (int) $boxscoreGameInfo->visitor_losses,
                        'home_wins' => (int) $boxscoreGameInfo->home_wins,
                        'home_losses' => (int) $boxscoreGameInfo->home_losses,
                        'visitor_q1_points' => (int) $boxscoreGameInfo->visitor_q1_points,
                        'visitor_q2_points' => (int) $boxscoreGameInfo->visitor_q2_points,
                        'visitor_q3_points' => (int) $boxscoreGameInfo->visitor_q3_points,
                        'visitor_q4_points' => (int) $boxscoreGameInfo->visitor_q4_points,
                        'visitor_ot_points' => (int) $boxscoreGameInfo->visitor_ot_points,
                        'home_q1_points' => (int) $boxscoreGameInfo->home_q1_points,
                        'home_q2_points' => (int) $boxscoreGameInfo->home_q2_points,
                        'home_q3_points' => (int) $boxscoreGameInfo->home_q3_points,
                        'home_q4_points' => (int) $boxscoreGameInfo->home_q4_points,
                        'home_ot_points' => (int) $boxscoreGameInfo->home_ot_points,
                        'game_2gm' => (int) $playerStats->gameFieldGoalsMade,
                        'game_2ga' => (int) $playerStats->gameFieldGoalsAttempted,
                        'game_ftm' => (int) $playerStats->gameFreeThrowsMade,
                        'game_fta' => (int) $playerStats->gameFreeThrowsAttempted,
                        'game_3gm' => (int) $playerStats->gameThreePointersMade,
                        'game_3ga' => (int) $playerStats->gameThreePointersAttempted,
                        'game_orb' => (int) $playerStats->gameOffensiveRebounds,
                        'game_drb' => (int) $playerStats->gameDefensiveRebounds,
                        'game_ast' => (int) $playerStats->gameAssists,
                        'game_stl' => (int) $playerStats->gameSteals,
                        'game_tov' => (int) $playerStats->gameTurnovers,
                        'game_blk' => (int) $playerStats->gameBlocks,
                        'game_pf' => (int) $playerStats->gamePersonalFouls,
                    ]);
                    $gameLinesProcessed++;
                } else {
                    $playerUuid = UuidGenerator::generateUuid();
                    $playerTeamID = ScoFileParser::isHomeTeamSlot($i)
                        ? $boxscoreGameInfo->home_teamid
                        : $boxscoreGameInfo->visitor_teamid;
                    $this->repository->insertPlayerBoxscore(
                        $boxscoreGameInfo->gameDate,
                        $playerUuid,
                        $name,
                        $playerStats->position,
                        (int) $playerStats->playerID,
                        $boxscoreGameInfo->visitor_teamid,
                        $boxscoreGameInfo->home_teamid,
                        $boxscoreGameInfo->game_of_that_day,
                        (int) $boxscoreGameInfo->attendance,
                        (int) $boxscoreGameInfo->capacity,
                        (int) $boxscoreGameInfo->visitor_wins,
                        (int) $boxscoreGameInfo->visitor_losses,
                        (int) $boxscoreGameInfo->home_wins,
                        (int) $boxscoreGameInfo->home_losses,
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
}
