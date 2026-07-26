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
interface FreeAgencyOtherFreeAgentsSectionViewInterface
{
    /**
     * @param list<Player> $allOtherPlayers Pre-built Player objects from service
     * @param array<int, array{color1: string, color2: string}> $teamColorsByTeamId
     */
    public function render(Team $team, Season $season, array $allOtherPlayers, array $teamColorsByTeamId): string;
}
