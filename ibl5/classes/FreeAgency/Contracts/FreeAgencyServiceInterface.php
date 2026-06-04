<?php

declare(strict_types=1);

namespace FreeAgency\Contracts;

use Team\Team;
use Season\Season;

/**
 * Interface for the FreeAgency read-path orchestrator
 *
 * Assembles data needed by views from repositories, calculators, and domain objects.
 * Views receive pre-computed data arrays and never touch the database.
 *
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 */
interface FreeAgencyServiceInterface
{
    /**
     * Assemble all data needed by the main Free Agency page
     *
     * Returns cap metrics, team data, and pre-built player collections needed by
     * FreeAgencyView::render(). The view receives pre-built Player objects and
     * never queries the database for the team-specific tables.
     *
     * @param Team $team Team object
     * @param Season $season Current season
     * @return array{
     *     capMetrics: array{totalSalaries: array<int, int>, softCapSpace: array<int, int>, hardCapSpace: array<int, int>, rosterSpots: array<int, int>},
     *     team: Team,
     *     season: Season,
     *     allOtherPlayers: list<\Player\Player>,
     *     teamColorsByTeamId: array<int, array{color1: string, color2: string}>,
     *     playersUnderContract: list<array{player: \Player\Player, contractAction: 'rookie_option'|'extension'|null}>,
     *     unsignedFreeAgents: list<\Player\Player>,
     *     offerPlayers: list<array{player: \Player\Player, offer: array<string, int>}>,
     *     cashPlayers: list<array{player: \Player\Player, label: string}>
     * }
     */
    public function getMainPageData(Team $team, Season $season): array;

    /**
     * Assemble all data needed by the negotiation page
     *
     * Loads the player, calculates cap metrics (excluding current offer),
     * fetches demands, existing offer, and salary limits needed by
     * FreeAgencyOfferView::render().
     *
     * @param int $playerID Player ID to negotiate with
     * @param Team $team Team making the offer
     * @param Season $season Current season
     * @return array{
     *     player: \Player\Player,
     *     capMetrics: array{totalSalaries: array<int, int>, softCapSpace: array<int, int>, hardCapSpace: array<int, int>, rosterSpots: array<int, int>},
     *     demands: array<string, int>,
     *     existingOffer: array<string, int>,
     *     amendedCapSpace: int,
     *     hasExistingOffer: bool,
     *     veteranMinimum: int,
     *     maxContract: int
     * }
     */
    public function getNegotiationData(int $playerID, Team $team, Season $season): array;

    /**
     * Get existing offer from a team to a player, with defaults
     *
     * Returns the offer data with integer values, or all-zeros if no offer exists.
     *
     * @param int $teamid Team ID
     * @param int $pid Player ID
     * @return array<string, int> Offer with keys offer1-6, all integers
     */
    public function getExistingOffer(int $teamid, int $pid): array;
}
