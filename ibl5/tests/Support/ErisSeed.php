<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Seed policy for Eris property tests. Eris seeds from microtime when
 * ERIS_SEED is unset; this pins a fixed default so CI runs are deterministic.
 */
final class ErisSeed
{
    public const DEFAULT_SEED = 20261120;

    public const SHRINK_SECONDS = 2;

    public static function resolve(string|false $raw): int
    {
        if ($raw === false || $raw === '') {
            return self::DEFAULT_SEED;
        }
        if (ctype_digit($raw) === false) {
            throw new \InvalidArgumentException("ERIS_SEED must be a non-negative integer, got '{$raw}'");
        }
        return (int) $raw;
    }
}
