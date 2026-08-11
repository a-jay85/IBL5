<?php

declare(strict_types=1);

namespace SimRecap;

/**
 * Which season phases sim recaps run in.
 *
 * Single source of truth for the gate. Enabling another phase later
 * (Preseason, HEAT, Playoffs) is a one-line change to ENABLED_PHASES.
 */
final class RecapPhasePolicy
{
    /** @var list<string> Season phases during which sim recaps are generated. */
    public const ENABLED_PHASES = ['Regular Season'];

    public static function isEnabled(string $phase): bool
    {
        return in_array($phase, self::ENABLED_PHASES, true);
    }
}
