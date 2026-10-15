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
            /** @var PlayerStats $playerStats */
            $playerStats = PlayerStats::withBoxscoreInfoLine($this->db, ScoFileParser::extractPlayerSlot($line, $i));
            $name = $this->decodeSlotName($playerStats->name);
            if ($name === '') {
                continue;
            }

            if ((int) $playerStats->playerID === 0) {
                $name = $this->resolveTeamTotalName($name, !$visitorTeamTotalSeen, $visitorTeamName, $homeTeamName);
                $visitorTeamTotalSeen = true;
                $this->repository->insertTeamBoxscore(
                    $this->buildTeamBoxscoreRow($boxscoreGameInfo, $playerStats, $name),
                );
            } else {
                $this->insertPlayerBoxscoreRow($boxscoreGameInfo, $playerStats, $name, $i);
            }
            $gameLinesProcessed++;
        }

        return $gameLinesProcessed;
    }

    /**
     * Convert a slot's ISO-8859-1 name to UTF-8, keeping the raw name if conversion fails
     */
    private function decodeSlotName(string $rawName): string
    {
        $name = mb_convert_encoding($rawName, 'UTF-8', 'ISO-8859-1');
        if ($name === false) {
            $name = $rawName;
        }

        return $name;
    }

    /**
     * The first team-total row of a game line is the visitor's, the second is the home's.
     * A non-null override replaces the parsed name for that side.
     */
    private function resolveTeamTotalName(
        string $parsedName,
        bool $isVisitorTotal,
        ?string $visitorTeamName,
        ?string $homeTeamName,
    ): string {
        if ($isVisitorTotal) {
            return $visitorTeamName ?? $parsedName;
        }

        return $homeTeamName ?? $parsedName;
    }

    /**
     * @return array{
     *     game_date: string, name: string, game_of_that_day: int, visitor_teamid: int, home_teamid: int,
     *     attendance: int, capacity: int, visitor_wins: int, visitor_losses: int, home_wins: int, home_losses: int,
     *     visitor_q1_points: int, visitor_q2_points: int, visitor_q3_points: int, visitor_q4_points: int, visitor_ot_points: int,
     *     home_q1_points: int, home_q2_points: int, home_q3_points: int, home_q4_points: int, home_ot_points: int,
     *     game_2gm: int, game_2ga: int, game_ftm: int, game_fta: int, game_3gm: int, game_3ga: int,
     *     game_orb: int, game_drb: int, game_ast: int, game_stl: int, game_tov: int, game_blk: int, game_pf: int
     * }
     */
    private function buildTeamBoxscoreRow(Boxscore $boxscoreGameInfo, PlayerStats $playerStats, string $name): array
    {
        return [
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
        ];
    }

    private function insertPlayerBoxscoreRow(
        Boxscore $boxscoreGameInfo,
        PlayerStats $playerStats,
        string $name,
        int $slot,
    ): void {
        $playerUuid = UuidGenerator::generateUuid();
        $playerTeamID = ScoFileParser::isHomeTeamSlot($slot)
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
    }
}
