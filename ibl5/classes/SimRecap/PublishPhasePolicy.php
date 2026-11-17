<?php

declare(strict_types=1);

namespace SimRecap;

/**
 * Phase gate for the Discord publisher. Only Regular Season sims are posted.
 *
 * Kept apart from RecapPhasePolicy::ENABLED_PHASES on purpose: the recap writer
 * also runs for HEAT, the publisher must not.
 */
final class PublishPhasePolicy
{
    /** @var list<string> */
    public const PUBLISH_PHASES = ['Regular Season'];

    public static function isPublishable(string $phase): bool
    {
        return in_array($phase, self::PUBLISH_PHASES, true);
    }
}
