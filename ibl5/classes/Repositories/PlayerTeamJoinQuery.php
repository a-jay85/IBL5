<?php

declare(strict_types=1);

namespace Repositories;

/**
 * Shared SELECT + JOIN prefix for player rows enriched with their team's
 * name and colors (backlog 7.18 — dedups the identical prefix previously
 * copy-pasted across PlayerRepository and PlayerLookupRepository).
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
            LEFT JOIN `ibl_team_info` t ON p.teamid = t.teamid";
    }
}
