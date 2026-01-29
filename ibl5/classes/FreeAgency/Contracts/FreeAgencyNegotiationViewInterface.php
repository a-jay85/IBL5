<?php

declare(strict_types=1);

namespace FreeAgency\Contracts;

/**
 * Interface for rendering the Free Agency negotiation page
 *
 * Pure renderer that receives pre-computed data from FreeAgencyService.
 * Renders the contract offer form where teams make or amend offers to free agents.
 * Displays player ratings, demands, cap constraints, and offer input fields.
 */
interface FreeAgencyNegotiationViewInterface
{
    /**
     * Render the complete negotiation page from pre-computed data
     *
     * Outputs a comprehensive negotiation interface showing:
     * - Player position and name
     * - Player ratings (statistical abilities)
     * - Player image
     * - Player demands for all 6 contract years
     * - Cap space information (soft cap, hard cap, roster spots)
     * - Contract offer input fields (6 years)
     * - Max contract offer buttons (pre-calculated max salaries by year)
     * - Exception offer buttons (MLE, LLE, Veteran Minimum)
     * - Existing offer display (if already offered to this player)
     * - Delete offer button (if offer exists)
     *
     * @param array $negotiationData Pre-computed data from FreeAgencyService::getNegotiationData()
     * @return string Complete HTML negotiation form
     */
    public function render(array $negotiationData): string;
}
