<?php

declare(strict_types=1);

namespace Navigation\Contracts;

/**
 * Renders the desktop navigation bar: dropdown menus, teams mega-menu,
 * login/account dropdown, and dev switch.
 *
 * @phpstan-import-type NavLink from \Navigation\NavigationConfig
 * @phpstan-import-type NavMenuData from \Navigation\NavigationConfig
 * @see \Navigation\Views\DesktopNavView
 */
interface DesktopNavViewInterface
{
    /**
     * Render the complete desktop navigation section.
     *
     * @param array<string, NavMenuData> $menus
     * @param NavMenuData|null $myTeamMenu
     * @param list<array{label: string, url: string, noBoost?: bool}> $accountMenu
     */
    public function render(array $menus, ?array $myTeamMenu, array $accountMenu): string;

    /**
     * Render the dev switch button (only for admin on localhost/production).
     */
    public function renderDevSwitch(): string;
}
