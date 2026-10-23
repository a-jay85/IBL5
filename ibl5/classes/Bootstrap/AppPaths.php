<?php

declare(strict_types=1);

namespace Bootstrap;

/**
 * Typed path constants for the application.
 */
final class AppPaths
{
    private static ?string $root = null;

    /**
     * Get the ibl5/ root directory path.
     *
     * Falls back to dirname(__DIR__, 2) which resolves to ibl5/ from classes/Bootstrap/.
     */
    public static function root(): string
    {
        if (self::$root === null) {
            self::$root = dirname(__DIR__, 2);
        }

        return self::$root;
    }

    /**
     * Resolve the config/ directory.
     *
     * Lookup is CWD-relative first, then falls back to an absolute path:
     *   1. 'config'      when running from ibl5/ (normal app context)
     *   2. 'ibl5/config' when running from the project root or tests
     *   3. root() . '/config' as the absolute fallback
     *
     * The first two branches return relative paths on purpose. Callers
     * concatenate a filename onto the result, so the return value is
     * byte-identical to the two private copies it replaces.
     */
    public static function configDir(): string
    {
        if (is_dir('config')) {
            return 'config';
        }

        if (is_dir('ibl5/config')) {
            return 'ibl5/config';
        }

        return self::root() . '/config';
    }
}
