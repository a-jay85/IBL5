<?php

declare(strict_types=1);

namespace Trading\Contracts;

/**
 * Interface for rendering Trading module pages
 *
 * Pure renderer that receives pre-computed data from TradingService.
 * All methods return HTML strings via output buffering.
 */
interface TradingViewInterface
{
    /**
     * Render the two-team trade offer form
     *
     * Displays both teams' rosters with checkboxes, cap totals,
     * cash exchange inputs, and submit button.
     *
     * @param array<string, mixed> $pageData Pre-computed data from TradingService::getTradeOfferPageData()
     * @return string Complete HTML for trade offer form
     */
    public function renderTradeOfferForm(array $pageData): string;

    /**
     * Render the trade review page with pending offers
     *
     * Displays pending trade offer cards with item details and
     * accept/reject buttons, plus team selection links and waivers links.
     *
     * @param array<string, mixed> $pageData Pre-computed data from TradingService::getTradeReviewPageData()
     * @return string Complete HTML for trade review page
     */
    public function renderTradeReview(array $pageData): string;

    /**
     * Render the "trades not allowed" message
     *
     * @param \Season $season Season object for checking waiver status
     * @return string HTML message about trading being closed
     */
    public function renderTradesClosed(\Season $season): string;

    /**
     * Render team selection links for trading partner selection
     *
     * @param list<array{name: string, city: string, fullName: string, teamid: int, color1: string, color2: string}> $teams Array of team data from TradingService
     * @return string HTML table of team links
     */
    public function renderTeamSelectionLinks(array $teams): string;
}
