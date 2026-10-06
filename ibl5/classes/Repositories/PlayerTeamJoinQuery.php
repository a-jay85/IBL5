<?php

declare(strict_types=1);

namespace Repositories;

/**
 * Shared SELECT + JOIN prefix for player rows enriched with their team's
 * name and colors (backlog 7.18 — dedups the identical prefix previously
 * copy-pasted across PlayerRepository and PlayerLookupRepository).
 *
 * Also owns the single player-to-team JOIN clause (backlog 13.12);
 * PlayerTeamJoinRatchetTest blocks new copies.
 */
trait PlayerTeamJoinQuery
{
    /**
     * Shared SELECT + JOIN prefix for player rows enriched with their team's
     * name and colors. Callers append their own WHERE / LIMIT.
     */
    private function playerWithTeamSelect(): string
    {
        return "SELECT p.*, t.team_name AS teamname, t.color1, t.color2
            FROM `ibl_plr` p
            " . $this->playerTeamLeftJoin();
    }

    /**
     * LEFT JOIN from player alias `p` to team alias `t`. Keeps players whose
     * teamid has no ibl_team_info row. Callers alias ibl_plr as `p`.
     *
     * @return 'LEFT JOIN `ibl_team_info` t ON p.teamid = t.teamid'
     */
    private function playerTeamLeftJoin(): string
    {
        return 'LEFT JOIN `ibl_team_info` t ON p.teamid = t.teamid';
    }

    /**
     * INNER JOIN from player alias `p` to team alias `t`. Drops players whose
     * teamid has no ibl_team_info row. Written as bare `JOIN` to match the
     * existing League / LeagueStarters SQL byte for byte.
     *
     * @return 'JOIN `ibl_team_info` t ON p.teamid = t.teamid'
     */
    private function playerTeamInnerJoin(): string
    {
        return 'JOIN `ibl_team_info` t ON p.teamid = t.teamid';
    }
}
