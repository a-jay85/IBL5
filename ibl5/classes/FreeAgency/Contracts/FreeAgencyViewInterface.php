<?php

declare(strict_types=1);

namespace FreeAgency\Contracts;

/**
 * Interface for rendering the Free Agency main page
 *
 * Pure renderer that receives pre-computed data from FreeAgencyService.
 * Renders comprehensive display of team's free agency status including
 * players under contract, pending offers, and available free agents.
 */
interface FreeAgencyViewInterface
{
    /**
     * Render the complete Free Agency main page from pre-computed data
     *
     * Outputs the primary Free Agency interface showing:
     * - Result banner (if redirected from offer/delete action)
     * - Team logo
     * - Players under contract table (all contract years, salary cap allocation)
     * - Pending contract offers table (team has made offers to these players)
     * - Team free agents table (free agents on this team)
     * - Other free agents table (all other available free agents)
     * - Cap metrics (soft cap space, hard cap space, available roster spots)
     *
     * @param array $mainPageData Pre-computed data from FreeAgencyService::getMainPageData()
     * @param string|null $result PRG result code (e.g. 'offer_success', 'deleted', 'already_signed')
     * @return string Complete HTML page content (not including HTML/body tags)
     */
    public function render(array $mainPageData, ?string $result = null): string;
}
