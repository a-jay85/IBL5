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
}
