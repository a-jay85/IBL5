<?php

declare(strict_types=1);

namespace Player;

use Player\Views\TeamColorHelper;
use Repositories\Contracts\TeamIdentityRepositoryInterface;

/**
 * Builds a team color scheme from the team identity row. Controllers call this
 * once and pass the array down; card views and CardBaseStyles never see the repo.
 *
 * @phpstan-type ColorScheme array{primary: string, secondary: string, gradient_start: string, gradient_mid: string, gradient_end: string, border: string, border_rgb: string, accent: string, text: string, text_muted: string}
 */
final class TeamColorSchemeResolver
{
    /**
     * Scheme for every card, page, and menu: always looks the row up, teamid 0
     * included, so a free agent gets the seeded Free Agents colors everywhere.
     *
     * @return ColorScheme
     */
    public static function forTeam(TeamIdentityRepositoryInterface $teamRepo, int $teamid): array
    {
        return self::fromRow($teamRepo->getTeamColorRow($teamid));
    }

    /**
     * @param array{color1: string|null, color2: string|null}|null $row Null when no row exists
     * @return ColorScheme
     */
    private static function fromRow(?array $row): array
    {
        $colors = TeamColorHelper::resolveTeamColors($row);
        return TeamColorHelper::generateColorScheme($colors['color1'], $colors['color2']);
    }
}
