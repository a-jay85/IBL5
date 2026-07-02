<?php

declare(strict_types=1);

namespace Voting\Contracts;

/**
 * VotingResultsViewInterface - Voting results HTML rendering
 *
 * Renders voting results tables using legacy module styling.
 *
 * @phpstan-import-type VoteTable from VotingResultsServiceInterface
 */
interface VotingResultsViewInterface
{
    /**
     * Renders multiple voting result tables
     *
     * Takes an array of table data and renders each as an HTML table
     * with title and styled rows.
     *
     * @param list<VoteTable> $tables Array of tables
     * @param string $pageTitle Optional page-level heading; when non-empty, emits an <h1> above the award tables.
     * @return string HTML output for all tables
     *
     * **HTML Structure:**
     * When $pageTitle is non-empty: <h1 class="ibl-title"> above all tables.
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
    public function renderTables(array $tables, string $pageTitle = ''): string;
}
