<?php

declare(strict_types=1);

namespace GameBoxscore\Contracts;

/**
 * Service interface for the GameBoxscore module.
 *
 * Provides the trust boundary between raw `$_GET` input and the Repository:
 * validates the requested date and game number, normalizes/types the raw
 * Repository rows into a single view-model, splits players by team, and
 * computes each team's totals row.
 *
 * @phpstan-type GameBoxscorePlayerRow array{pid: int, pos: string, name: string, min: int, fgm: int, fga: int, ftm: int, fta: int, tpm: int, tpa: int, pts: int, orb: int, reb: int, ast: int, stl: int, blk: int, tov: int, pf: int}
 * @phpstan-type GameBoxscoreTeamHeader array{teamId: int, name: string, city: string, color1: string, color2: string, score: int}
 * @phpstan-type GameBoxscoreTotals array{min: int, fgm: int, fga: int, ftm: int, fta: int, tpm: int, tpa: int, pts: int, orb: int, reb: int, ast: int, stl: int, blk: int, tov: int, pf: int}
 * @phpstan-type GameBoxscoreViewModel array{found: bool, date: string, gameOfThatDay: int, awayTeam: GameBoxscoreTeamHeader, homeTeam: GameBoxscoreTeamHeader, awayPlayers: list<GameBoxscorePlayerRow>, homePlayers: list<GameBoxscorePlayerRow>, awayTotals: GameBoxscoreTotals, homeTotals: GameBoxscoreTotals}
 */
interface GameBoxscoreServiceInterface
{
    /**
     * Build the view-model for one game's boxscore from raw request input.
     * Returns a not-found model (`found` => false) for invalid input or an
     * unknown game; never throws and never queries the Repository on invalid input.
     *
     * @return GameBoxscoreViewModel
     */
    public function getBoxscore(mixed $rawDate, mixed $rawGame): array;

    /**
     * Narrow a raw `team` request value to a positive team id.
     *
     * @param mixed $rawTeam Raw request value, e.g. $_GET['team'] ?? null
     * @return int Positive team id, or 0 when the value is missing or malformed
     */
    public function parseSelectedTeamId(mixed $rawTeam): int;
}
