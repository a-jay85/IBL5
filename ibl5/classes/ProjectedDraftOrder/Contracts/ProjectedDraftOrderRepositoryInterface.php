<?php

declare(strict_types=1);

namespace ProjectedDraftOrder\Contracts;

/**
 * @phpstan-type StandingsRow array{teamid: int, team_name: string, wins: int, losses: int, pct: float, conference: string, division: string, conf_wins: int|null, conf_losses: int|null, div_wins: int|null, div_losses: int|null, clinched_division: int|null, color1: string, color2: string}
 * @phpstan-type GameRow array{visitor_teamid: int, visitor_score: int, home_teamid: int, home_score: int}
 * @phpstan-type PickOwnershipRow array{ownerofpick: string, teampick: string, round: int, notes: string|null}
 * @phpstan-type PointDifferentialRow array{teamid: int, pointsFor: float, pointsAgainst: float}
 * @see \ProjectedDraftOrder\ProjectedDraftOrderRepository
 */
interface ProjectedDraftOrderRepositoryInterface
{
    /**
     * Get all teams with standings and team info colors.
     *
     * @return list<StandingsRow>
     */
    public function getAllTeamsWithStandings(): array;

    /**
     * Get all played games for a season (for head-to-head tiebreaker).
     *
     * @return list<GameRow>
     */
    public function getPlayedGames(int $seasonYear): array;

    /**
     * Get draft pick ownership for a year, rounds 1 and 2.
     *
     * @return list<PickOwnershipRow>
     */
    public function getPickOwnership(int $draftYear): array;

    /**
     * Get point differentials per team for a season (tiebreaker #5).
     *
     * @return list<PointDifferentialRow>
     */
    public function getPointDifferentials(int $seasonYear): array;

    /**
     * Check if the draft order has been finalized via ibl_settings.
     */
    public function isDraftOrderFinalized(): bool;

    /**
     * Save the final draft order (both rounds) to ibl_draft and mark as finalized.
     *
     * @param list<array{round: int, pick: int, team: string, teamid: int}> $picks
     */
    public function saveFinalDraftOrder(int $year, array $picks): void;

    /**
     * Fetch saved draft order from ibl_draft for a given year and round.
     *
     * @return list<array{pick: int, team: string, teamid: int, player: string}>
     */
    public function getFinalDraftOrder(int $year, int $round = 1): array;

    /**
     * Check if any player has been drafted for the given year.
     */
    public function isDraftStarted(int $year): bool;

    /**
     * Upsert the IBL Draft Lottery Winners award for the team that owns the #1 pick.
     */
    public function upsertLotteryWinnerAward(int $year, string $teamName): void;
}
