<?php

declare(strict_types=1);

namespace FreeAgency\Contracts;

/**
 * Interface for the FreeAgency read-path orchestrator
 *
 * Assembles data needed by views from repositories, calculators, and domain objects.
 * Views receive pre-computed data arrays and never touch the database.
 */
interface FreeAgencyServiceInterface
{
    /**
     * Assemble all data needed by the main Free Agency page
     *
     * Returns cap metrics and team data needed by FreeAgencyView::render().
     * The view uses this data to render the four main tables:
     * - Players under contract
     * - Contract offers
     * - Team free agents
     * - All other free agents
     *
     * @param \Team $team Team object
     * @param \Season $season Current season
     * @return array{
     *     capMetrics: array{totalSalaries: array<int, int>, softCapSpace: array<int, int>, hardCapSpace: array<int, int>, rosterSpots: array<int, int>},
     *     team: \Team,
     *     season: \Season,
     *     allOtherPlayers: list<array<string, mixed>>
     * }
     */
    public function getMainPageData(\Team $team, \Season $season): array;

    /**
     * Assemble all data needed by the negotiation page
     *
     * Loads the player, calculates cap metrics (excluding current offer),
     * fetches demands, existing offer, and salary limits needed by
     * FreeAgencyNegotiationView::render().
     *
     * @param int $playerID Player ID to negotiate with
     * @param \Team $team Team making the offer
     * @param \Season $season Current season
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
    public function getNegotiationData(int $playerID, \Team $team, \Season $season): array;

    /**
     * Get existing offer from a team to a player, with defaults
     *
     * Returns the offer data with integer values, or all-zeros if no offer exists.
     *
     * @param string $teamName Team name
     * @param string $playerName Player name
     * @return array<string, int> Offer with keys offer1-6, all integers
     */
    public function getExistingOffer(string $teamName, string $playerName): array;
}
