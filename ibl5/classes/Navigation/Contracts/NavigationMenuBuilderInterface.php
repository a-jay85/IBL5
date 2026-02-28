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
     * Get account menu items based on login state.
     *
     * @return list<array{label: string, url: string}>
     */
    public function getAccountMenu(): array;
}
