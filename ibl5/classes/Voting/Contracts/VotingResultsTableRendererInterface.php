<?php

declare(strict_types=1);

namespace Voting\Contracts;

/**
 * VotingResultsTableRendererInterface - Voting results HTML rendering
 *
 * Renders voting results tables using legacy module styling.
 *
 * @phpstan-import-type VoteTable from VotingResultsServiceInterface
 */
interface VotingResultsTableRendererInterface
{
    /**
     * Renders multiple voting result tables
     *
     * Takes an array of table data and renders each as an HTML table
     * with title and styled rows.
     *
     * @param list<VoteTable> $tables Array of tables
     * @return string HTML output for all tables
     *
     * **HTML Structure:**
     * For each table:
     * - <h2> with centered title
     * - <table class="sortable"> with styling
     * - Header row: "Player" | "Votes"
     * - Data rows: Alternating background colors
     *
     * **Styling:**
     * - Table width: min(100%, 420px)
     * - Border-collapse: collapse
     * - Alternating row backgrounds (#f8f9fb for odd rows)
     * - Cell padding: 0.35-0.4rem
     *
     * **Behaviors:**
     * - Names are HTML-escaped for XSS protection
     * - Empty tables array returns empty string
     * - Missing rows key treated as empty array
     */
    public function renderTables(array $tables): string;
}
