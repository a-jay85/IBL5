<?php

declare(strict_types=1);

namespace OnThisDay;

/**
 * Which season phases post "on this day" to Discord.
 *
 * These are the phases whose sims advance ibl_sim_dates over real games, so
 * "on this day" has a live league date. Preseason, Draft, and Free Agency are
 * excluded.
 */
final class OnThisDayPhasePolicy
{
    /** @var list<string> Season phases during which the Discord post runs. */
    public const ENABLED_PHASES = ['HEAT', 'Regular Season', 'Playoffs'];

    public static function isEnabled(string $phase): bool
    {
        return in_array($phase, self::ENABLED_PHASES, true);
    }
}
