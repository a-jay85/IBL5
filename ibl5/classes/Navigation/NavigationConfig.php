<?php

declare(strict_types=1);

namespace Navigation;

/**
 * Value object holding all configuration needed to render the navigation bar.
 * Replaces 10 constructor parameters with a single typed object.
 *
 * @phpstan-type NavLink array{label?: string, url?: string, external?: bool, badge?: string, rawHtml?: string}
 * @phpstan-type NavMenuData array{links: list<NavLink>, icon?: string}
 * @phpstan-type NavTeamsData array<string, array<string, list<array{teamid: int, team_name: string, team_city: string}>>>
 */
final class NavigationConfig
{
    /** @var NavTeamsData|null */
    public readonly ?array $teamsData;

    /**
     * @param NavTeamsData|null $teamsData
     */
    public function __construct(
        public readonly bool $isLoggedIn,
        public readonly ?string $username,
        public readonly string $currentLeague,
        public readonly ?int $teamId = null,
        ?array $teamsData = null,
        public readonly string $seasonPhase = '',
        public readonly string $allowWaivers = '',
        public readonly string $showDraftLink = '',
        public readonly ?string $serverName = null,
        public readonly ?string $requestUri = null,
    ) {
        $this->teamsData = $teamsData;
    }
}
