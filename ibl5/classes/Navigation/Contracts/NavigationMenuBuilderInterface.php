<?php

declare(strict_types=1);

namespace Navigation\Contracts;

/**
 * Builds navigation menu data structures (no HTML).
 *
 * @phpstan-import-type NavLink from \Navigation\NavigationConfig
 * @phpstan-import-type NavMenuData from \Navigation\NavigationConfig
 */
interface NavigationMenuBuilderInterface
{
    /**
     * Get the main navigation menu structure (Season, Stats, History, Community).
     *
     * @return array<string, NavMenuData>
     */
    public function getMenuStructure(): array;

    /**
     * Get the My Team menu based on login state and current league.
     *
     * @return NavMenuData|null
     */
    public function getMyTeamMenu(): ?array;

    /**
     * Get the My Team menu with non-logout account links folded in.
     *
     * The nav suppresses the standalone Account dropdown when a My Team menu
     * exists, so account links (e.g. Notification Settings) are folded into the
     * My Team menu to keep them reachable. Returns null when there is no My Team
     * menu (guests / teamless users keep the standalone Account dropdown).
     *
     * @return NavMenuData|null
     */
    public function getMyTeamMenuWithAccountLinks(): ?array;

    /**
     * Get account menu items based on login state.
     *
     * @return list<array{label: string, url: string, noBoost?: bool}>
     */
    public function getAccountMenu(): array;
}
