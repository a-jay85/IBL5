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
     * Trading-card scheme: teamid <= 0 (free agents, unsigned) gets the default
     * gold scheme without a lookup.
     *
     * @return ColorScheme
     */
    public static function forTradingCard(TeamIdentityRepositoryInterface $teamRepo, int $teamid): array
    {
        if ($teamid <= 0) {
            return TeamColorHelper::getDefaultColorScheme();
        }
        return self::fromRow($teamRepo->getTeamColorRow($teamid));
    }

    /**
     * Page/menu/stats-card scheme: always looks the row up, teamid 0 included.
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
