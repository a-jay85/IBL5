<?php

declare(strict_types=1);

namespace Season;

use League\LeagueContext;

/**
 * Request-scoped Season accessor.
 *
 * When enabled (only by Bootstrap\SharedSeasonBootstrap in non-CLI web/API
 * requests), get() builds one Season per (db connection, argument league) and
 * returns that same instance for the rest of the request. When disabled (the
 * default: CLI, PHPUnit, the Updater), get() returns a fresh Season on every
 * call, exactly like `new Season(...)`.
 *
 * Settings writers call reset() after each write so a same-request read
 * rebuilds. scripts/updateAllTheThings.php calls disable() before it builds
 * its first Season, because it writes settings, schedule, and sim dates and
 * then reads them back in one process.
 */
final class SharedSeason
{
    private static bool $enabled = false;

    private static ?\mysqli $db = null;

    /** @var array<string, Season> keyed by argument league ('' when no context) */
    private static array $instances = [];

    public static function get(\mysqli $db, ?LeagueContext $leagueContext = null): Season
    {
        if (!self::$enabled) {
            return new Season($db, $leagueContext);
        }

        if (self::$db !== $db) {
            self::$db = $db;
            self::$instances = [];
        }

        $key = $leagueContext?->getCurrentLeague() ?? '';

        return self::$instances[$key] ??= new Season($db, $leagueContext);
    }

    public static function enable(): void
    {
        self::$enabled = true;
    }

    /** Turn the memo off and drop any instance already cached. */
    public static function disable(): void
    {
        self::$enabled = false;
        self::reset();
    }

    /** Drop cached instances; leave the enabled flag as it is. */
    public static function reset(): void
    {
        self::$db = null;
        self::$instances = [];
    }

    public static function isEnabled(): bool
    {
        return self::$enabled;
    }
}
