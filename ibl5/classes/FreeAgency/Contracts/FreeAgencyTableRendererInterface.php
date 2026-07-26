<?php

declare(strict_types=1);

namespace FreeAgency\Contracts;

use Player\Player;
use Team\Team;
use Season\Season;

/**
 * Interface for the shared table markup helpers used by all four FA section renderers
 *
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 * @phpstan-type CapMetrics array{totalSalaries: array<int, int>, softCapSpace: array<int, int>, hardCapSpace: array<int, int>, rosterSpots: array<int, int>}
 */
interface FreeAgencyTableRendererInterface
{
    /**
     * Render colgroups for table column organization
     *
     * @return string HTML colgroup elements
     */
    public function renderColgroups(bool $showTeamColumn = true, bool $showOptionsColumn = true): string;

    /**
     * Render table header
     *
     * @param string $title Table title to display in header
     * @param bool $showBirdRightsNote Whether to show the Bird Rights note
     * @param Team $team Team object for name display
     * @return string HTML table header
     */
    public function renderTableHeader(string $title, bool $showBirdRightsNote, Team $team, bool $showTeamColumn = true, bool $showOptionsColumn = true, ?Season $season = null): string;

    /**
     * @param array{color1: string, color2: string}|null $teamColors
     */
    public function renderTeamCell(Player $player, ?array $teamColors = null): string;

    /**
     * Render player ratings cells
     *
     * @param Player $player
     * @return string HTML table cells
     */
    public function renderPlayerRatings(Player $player): string;

    /**
     * Render player preferences cells
     *
     * @param Player $player
     * @return string HTML table cells
     */
    public function renderPlayerPreferences(Player $player): string;

    /**
     * Render player demands cells
     *
     * @param array<string, int> $demands
     * @return string HTML table cells
     */
    public function renderPlayerDemands(array $demands): string;

    /**
     * Render cap space footer rows
     *
     * @param Team $team Team object
     * @param CapMetrics $capMetrics Cap metrics from service
     * @return string HTML table rows
     */
    public function renderCapSpaceFooter(Team $team, array $capMetrics): string;
}
