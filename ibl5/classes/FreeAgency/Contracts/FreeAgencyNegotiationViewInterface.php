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
     * Outputs a card-based negotiation interface showing:
     * - Error banner (if redirected from failed offer submission)
     * - Player info card (position, name, image, ratings)
     * - Demands + custom offer card (demands display, 6-year input fields)
     * - Quick offer presets card (max contract, MLE, LLE, vet min buttons)
     * - Notes/reminders card (cap rules, raise limits)
     * - Delete offer button (if offer exists)
     *
     * @param array $negotiationData Pre-computed data from FreeAgencyService::getNegotiationData()
     * @param string|null $error Error message from PRG redirect (validation failure)
     * @return string Complete HTML negotiation form
     */
    public function render(array $negotiationData, ?string $error = null): string;
}
