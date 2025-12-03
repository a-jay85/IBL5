<?php

declare(strict_types=1);

namespace FreeAgency\Contracts;

/**
 * Interface for rendering the Free Agency main page
 * 
 * Renders comprehensive display of team's free agency status including
 * players under contract, pending offers, and available free agents.
 */
interface FreeAgencyDisplayHelperInterface
{
    /**
     * Render the complete Free Agency main page
     * 
     * Outputs the primary Free Agency interface showing:
     * - Team logo
     * - Players under contract table (all contract years, salary cap allocation)
     * - Pending contract offers table (team has made offers to these players)
     * - Team free agents table (free agents on this team)
     * - Other free agents table (all other available free agents)
     * - Cap metrics (soft cap space, hard cap space, available roster spots)
     * 
     * Each table includes:
     * - Sortable columns (via sorttable.js JavaScript plugin)
     * - All relevant player stats and contract info
     * - Navigation links to player and negotiation pages
     * 
     * Uses output buffering (ob_start/ob_get_clean) for HTML generation.
     * All output is properly escaped with htmlspecialchars().
     * 
     * @return string Complete HTML page content (not including HTML/body tags)
     *                Ready to be embedded in template
     */
    public function renderMainPage(): string;
}
