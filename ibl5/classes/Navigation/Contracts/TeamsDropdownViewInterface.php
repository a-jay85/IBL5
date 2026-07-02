<?php

declare(strict_types=1);

namespace Navigation\Contracts;

/**
 * Renders the Teams mega-menu/list for both desktop and mobile navigation.
 *
 * @phpstan-import-type NavTeamsData from \Navigation\NavigationConfig
 * @see \Navigation\Views\TeamsDropdownView
 */
interface TeamsDropdownViewInterface
{
    /**
     * Render the desktop Teams mega-menu with 2x2 conference/division grid.
     *
     * @param NavTeamsData $teamsData
     */
    public function renderDesktop(array $teamsData): string;

    /**
     * Render the mobile Teams collapsible section with division sub-headers.
     *
     * @param NavTeamsData $teamsData
     */
    public function renderMobile(array $teamsData, ?int $userTeamId): string;
}
