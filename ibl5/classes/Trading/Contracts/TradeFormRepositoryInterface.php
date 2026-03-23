<?php

declare(strict_types=1);

namespace Trading\Contracts;

/**
 * TradeFormRepositoryInterface - Contract for trade form UI data queries
 *
 * Handles queries needed by the trade offer and review forms:
 * team rosters, draft picks, team lists, and roster counts.
 * Extracted from TradingRepositoryInterface to follow single-responsibility principle.
 *
 * @phpstan-type TradingPlayerRow array{pos: string, name: string, pid: int, ordinal: ?int, cy: ?int, cy1: ?int, cy2: ?int, cy3: ?int, cy4: ?int, cy5: ?int, cy6: ?int}
 * @phpstan-type TradingDraftPickRow array{pickid: int, ownerofpick: string, teampick: string, teampick_id: int, year: string, round: string, notes: ?string, created_at: string, updated_at: string}
 * @phpstan-type TeamWithCityRow array{teamid: int, team_name: string, team_city: string, color1: string, color2: string}
 */
interface TradeFormRepositoryInterface
{
    /**
     * Get team players eligible for trading display
     *
     * Returns active (non-retired) players for a team, ordered by ordinal.
     * Excludes buyout/cash placeholder records whose names start with '|'.
     * Includes position, name, contract year data needed by trade form.
     *
     * @param int $teamId Team ID
     * @return list<TradingPlayerRow> Player rows
     */
    public function getTeamPlayersForTrading(int $teamId): array;

    /**
     * Get team draft picks for trading display
     *
     * Returns all draft picks owned by a team, ordered by year and round.
     *
     * @param int $teamId Team ID (owner_tid value)
     * @return list<TradingDraftPickRow> Draft pick rows with teampick team ID
     */
    public function getTeamDraftPicksForTrading(int $teamId): array;

    /**
     * Get all teams with city, name, colors and ID for trading UI
     *
     * @return list<TeamWithCityRow> Team rows ordered by city
     */
    public function getAllTeamsWithCity(): array;

    /**
     * Count active roster players for a team
     *
     * Excludes retired players, cash placeholders (ordinal >= 100000),
     * and buyout/cash records whose names start with '|'.
     *
     * During the offseason (Playoffs/Draft/Free Agency), also excludes players
     * whose contracts have expired (next-year salary is $0), since these players
     * are effectively free agents even though they remain assigned to the team.
     *
     * @param int $teamId Team ID
     * @param bool $isOffseason Whether to apply offseason contract expiry filtering
     * @return int Number of active players on the team's roster
     */
    public function getTeamPlayerCount(int $teamId, bool $isOffseason = false): int;
}
