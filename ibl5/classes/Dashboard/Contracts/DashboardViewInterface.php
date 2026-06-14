<?php

declare(strict_types=1);

namespace Dashboard\Contracts;

/**
 * View interface for the GM Dashboard module.
 *
 * Renders the aggregated dashboard data as compact, escaped section cards.
 *
 * @phpstan-import-type DashboardData from DashboardServiceInterface
 */
interface DashboardViewInterface
{
    /**
     * Render the dashboard as an HTML string.
     *
     * @param DashboardData $dashboardData Aggregated, owner-scoped dashboard sections.
     * @return string Rendered HTML.
     */
    public function render(array $dashboardData): string;
}
