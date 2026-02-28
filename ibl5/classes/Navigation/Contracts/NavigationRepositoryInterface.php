<?php

declare(strict_types=1);

namespace Navigation\Contracts;

/**
 * Database operations for the Navigation module.
 *
 * @phpstan-import-type NavTeamsData from \Navigation\NavigationConfig
 */
interface NavigationRepositoryInterface
{
    /**
     * Resolve a user's team ID from their username.
     * Looks up the team name from nuke_users, then the team ID from ibl_team_info.
     */
    public function resolveTeamId(string $username): ?int;

    /**
     * Fetch teams organized by conference and division for navigation mega-menu.
     *
     * @return NavTeamsData|null
     */
    public function getTeamsData(): ?array;
}
