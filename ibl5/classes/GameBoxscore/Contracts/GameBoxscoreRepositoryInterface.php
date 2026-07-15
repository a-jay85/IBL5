<?php

declare(strict_types=1);

namespace GameBoxscore\Contracts;

/**
 * Repository interface for the GameBoxscore module.
 *
 * Provides raw, unnormalized read access to a single game's box score data:
 * the game header row (teams, scores, colors) and the per-player stat rows
 * for both teams. Row typing and display-default normalization are owned by
 * the Service layer, not this repository.
 *
 * @see \BaseMysqliRepository For base repository behavior
 */
interface GameBoxscoreRepositoryInterface
{
    /**
     * Get the game header row for a single game.
     *
     * @param string $date Game date (YYYY-MM-DD)
     * @param int $gameOfThatDay Ordinal for the game on that date (e.g. 1 for the first game)
     * @return array<string, int|float|string|null>|null Raw game-header row, or null if no game exists for ($date, $gameOfThatDay).
     */
    public function getGameInfo(string $date, int $gameOfThatDay): ?array;

    /**
     * Get the per-player stat rows for both teams in a single game.
     *
     * @param string $date Game date (YYYY-MM-DD)
     * @param int $gameOfThatDay Ordinal for the game on that date
     * @param int $awayTeamId Visiting team ID
     * @param int $homeTeamId Home team ID
     * @return list<array<string, int|float|string|null>> Raw player-stat rows for both teams (empty list if none).
     */
    public function getPlayerRows(string $date, int $gameOfThatDay, int $awayTeamId, int $homeTeamId): array;
}
