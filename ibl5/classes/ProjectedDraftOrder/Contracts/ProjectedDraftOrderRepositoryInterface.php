<?php

declare(strict_types=1);

namespace ProjectedDraftOrder\Contracts;

/**
 * @phpstan-type StandingsRow array{tid: int, team_name: string, wins: int, losses: int, pct: float, conference: string, division: string, confWins: int|null, confLosses: int|null, divWins: int|null, divLosses: int|null, clinchedDivision: int|null, color1: string, color2: string}
 * @phpstan-type GameRow array{Visitor: int, VScore: int, Home: int, HScore: int}
 * @phpstan-type PickOwnershipRow array{ownerofpick: string, teampick: string, round: int, notes: string|null}
 * @phpstan-type PointDifferentialRow array{tid: int, pointsFor: float, pointsAgainst: float}
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
}
