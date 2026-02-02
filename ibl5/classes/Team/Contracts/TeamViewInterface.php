<?php

declare(strict_types=1);

namespace Team\Contracts;

/**
 * TeamViewInterface - Contract for Team module HTML rendering
 *
 * Pure renderer that receives pre-computed data from TeamService.
 * Composes the full team page layout from pre-rendered sub-components.
 */
interface TeamViewInterface
{
    /**
     * Render the complete team page HTML from pre-computed data
     *
     * @param array $pageData Pre-computed data from TeamService::getTeamPageData()
     * @return string Complete HTML page content (not including header/footer)
     */
    public function render(array $pageData): string;
}
