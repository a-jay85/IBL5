<?php

declare(strict_types=1);

namespace Trading\Contracts;

/**
 * Interface for the Trading read-path orchestrator
 *
 * Assembles data needed by views from repositories and domain objects.
 * Views receive pre-computed data arrays and never touch the database.
 */
interface TradingServiceInterface
{
    /**
     * Assemble all data needed by the trade offer form
     *
     * Returns both teams' players, draft picks, salary data, and season info
     * needed by TradingView::renderTradeOfferForm().
     *
     * @param string $username Logged-in user's username
     * @param string $partnerTeam Partner team name
     * @return array{
     *     userTeam: string,
     *     userTeamId: int,
     *     partnerTeam: string,
     *     partnerTeamId: int,
     *     userPlayers: list<array<string, mixed>>,
     *     userPicks: list<array<string, mixed>>,
     *     userFutureSalary: array{player: array<int, int>, hold: array<int, int>},
     *     partnerPlayers: list<array<string, mixed>>,
     *     partnerPicks: list<array<string, mixed>>,
     *     partnerFutureSalary: array{player: array<int, int>, hold: array<int, int>},
     *     seasonEndingYear: int,
     *     seasonPhase: string,
     *     cashStartYear: int,
     *     cashEndYear: int,
     *     userTeamColor1: string,
     *     userTeamColor2: string,
     *     partnerTeamColor1: string,
     *     partnerTeamColor2: string
     * }
     */
    public function getTradeOfferPageData(string $username, string $partnerTeam): array;

    /**
     * Assemble all data needed by the trade review page
     *
     * Returns pending trade offers with item details (players, picks, cash)
     * needed by TradingView::renderTradeReview().
     *
     * @param string $username Logged-in user's username
     * @return array{
     *     userTeam: string,
     *     userTeamId: int,
     *     tradeOffers: array<int, array{
     *         from: string,
     *         to: string,
     *         approval: string,
     *         oppositeTeam: string,
     *         hasHammer: bool,
     *         items: array<array{description: string, notes: string|null}>
     *     }>,
     *     teams: list<array{name: string, city: string, fullName: string, teamid: int, color1: string, color2: string}>
     * }
     */
    public function getTradeReviewPageData(string $username): array;

    /**
     * Calculate future salary commitments for a set of players
     *
     * Extracts the salary calculation logic that was previously in UIHelper.
     * Computes per-year salary totals and player counts for up to 6 future years.
     *
     * @param list<array<string, mixed>> $players Array of player rows from repository
     * @param \Season $season Season object for phase-based contract year adjustment
     * @return array{player: array<int, int>, hold: array<int, int>} Future salary data:
     *         - 'player': Salary totals by future year index (0-5)
     *         - 'hold': Player counts with salary by future year index (0-5)
     */
    public function calculateFutureSalaries(array $players, \Season $season): array;
}
