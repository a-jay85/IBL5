<?php

declare(strict_types=1);

namespace Navigation\Contracts;

/**
 * Renders the mobile sliding panel navigation: user greeting, accordion
 * sections, teams list, and login form.
 *
 * @phpstan-import-type NavLink from \Navigation\NavigationConfig
 * @phpstan-import-type NavMenuData from \Navigation\NavigationConfig
 * @see \Navigation\Views\MobileNavView
 */
interface MobileNavViewInterface
{
    /**
     * Render the complete mobile navigation panel.
     *
     * @param array<string, NavMenuData> $menus
     * @param NavMenuData|null $myTeamMenu
     * @param list<array{label: string, url: string, noBoost?: bool}> $accountMenu
     */
    public function render(array $menus, ?array $myTeamMenu, array $accountMenu): string;
}
