<?php

declare(strict_types=1);

namespace Team\Contracts;

/**
 * SplitStatsRepositoryInterface - Contract for querying per-game averages
 * filtered by game context (home/road, wins/losses, month, opponent, etc.)
 *
 * @phpstan-type SplitStatsRow array{name: string, pos: string, pid: int, games: int, gameMINavg: string|null, gameFGMavg: string|null, gameFGAavg: string|null, gameFGPavg: string|null, gameFTMavg: string|null, gameFTAavg: string|null, gameFTPavg: string|null, game3GMavg: string|null, game3GAavg: string|null, game3GPavg: string|null, gameORBavg: string|null, gameREBavg: string|null, gameASTavg: string|null, gameSTLavg: string|null, gameTOVavg: string|null, gameBLKavg: string|null, gamePFavg: string|null, gamePTSavg: string|null}
 */
interface SplitStatsRepositoryInterface
{
    /**
     * Get per-game averages for a team's players filtered by a split criterion
     *
     * @param int $teamID Team ID (must be > 0)
     * @param int $seasonEndingYear The ending year of the current season
     * @param string $splitKey A validated split key (e.g. 'home', 'road', 'wins', 'month_11', 'vs_5')
     * @return list<SplitStatsRow> Player rows with per-game averages
     */
    public function getSplitStats(int $teamID, int $seasonEndingYear, string $splitKey): array;

    /**
     * Get all valid split key strings for parameter validation
     *
     * @return list<string>
     */
    public function getValidSplitKeys(): array;

    /**
     * Get a human-readable label for a split key
     *
     * @param string $splitKey A validated split key
     * @return string Human-readable label (e.g. "Home", "vs. Miami")
     */
    public function getSplitLabel(string $splitKey): string;
}
