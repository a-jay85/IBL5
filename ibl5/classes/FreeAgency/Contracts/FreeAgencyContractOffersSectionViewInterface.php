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
interface FreeAgencyContractOffersSectionViewInterface
{
    /**
     * Render contract offers table
     *
     * @param Team $team Team object
     * @param Season $season Season object
     * @param CapMetrics $capMetrics Cap metrics from service
     * @param list<array{player: Player, offer: array<string, int>}> $offerPlayers Pre-built offer data
     * @return string HTML table
     */
    public function render(Team $team, Season $season, array $capMetrics, array $offerPlayers): string;
}
