<?php

declare(strict_types=1);

namespace FreeAgency\Contracts;

use Player\Player;
use Team\Team;
use Season\Season;

/**
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 * @phpstan-type CapMetrics array{totalSalaries: array<int, int>, softCapSpace: array<int, int>, hardCapSpace: array<int, int>, rosterSpots: array<int, int>}
 */
interface FreeAgencyTeamFreeAgentsSectionViewInterface
{
    /**
     * Render team free agents table
     *
     * @param Team $team Team object
     * @param Season $season Season object
     * @param CapMetrics $capMetrics Cap metrics from service
     * @param list<Player> $unsignedPlayers Pre-built unsigned free agent players
     * @return string HTML table
     */
    public function render(Team $team, Season $season, array $capMetrics, array $unsignedPlayers): string;
}
