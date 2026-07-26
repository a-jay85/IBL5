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
interface FreeAgencyUnderContractSectionViewInterface
{
    /**
     * Render players under contract table
     *
     * @param Team $team Team object
     * @param Season $season Season object
     * @param CapMetrics $capMetrics Cap metrics from service
     * @param list<array{player: Player, contractAction: 'rookie_option'|'extension'|null}> $players Pre-built contracted player entries with their resolved contract action
     * @param list<array{player: Player, label: string}> $cashPlayers Pre-built cash consideration players
     * @return string HTML table
     */
    public function render(Team $team, Season $season, array $capMetrics, array $players, array $cashPlayers): string;
}
