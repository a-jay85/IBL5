<?php

declare(strict_types=1);

namespace Migration;

/**
 * MigrationFileResolver - Filesystem operations for migration discovery
 *
 * Scans the migrations directory for `.sql` and `.php` files and returns
 * them in a deterministic sort order:
 *   1. Numbered files (001_*, 002_*) by natural order
 *   2. Non-numbered files (add_*, fix-*, etc.) alphabetically
 *   3. Timestamp-based files (20260226_*) by natural order
 */
class MigrationFileResolver
{
    private string $migrationsDir;

    public function __construct(string $migrationsDir)
    {
        $this->migrationsDir = rtrim($migrationsDir, '/');
    }

    /**
     * Get all available migration filenames, sorted in execution order.
     *
     * @return list<string> Migration filenames (without path)
     */
    public function getAvailableMigrations(): array
    {
        if (!is_dir($this->migrationsDir)) {
            return [];
        }

        $files = scandir($this->migrationsDir);

        if ($files === false) {
            return [];
        }

        $migrations = [];

        foreach ($files as $file) {
            if ($this->isMigrationFile($file)) {
                $migrations[] = $file;
            }
        }

        usort($migrations, static function (string $a, string $b): int {
            $aCategory = self::getCategory($a);
            $bCategory = self::getCategory($b);

            if ($aCategory !== $bCategory) {
                return $aCategory <=> $bCategory;
            }

            return strnatcasecmp($a, $b);
        });

        return $migrations;
    }

    /**
     * Get the full filesystem path for a migration filename.
     */
    public function getFullPath(string $filename): string
    {
        return $this->migrationsDir . '/' . $filename;
    }

    /**
     * Check if a file is a migration file (.sql or .php).
     */
    private function isMigrationFile(string $filename): bool
    {
        if ($filename === '.' || $filename === '..') {
            return false;
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return $extension === 'sql' || $extension === 'php';
    }

    /**
     * Determine the sort category for a migration filename.
     *
     * Categories:
     *   0 = Numbered prefix (001_, 002_, ... 999_)
     *   1 = Non-numbered (add_*, fix-*, create_*, etc.)
     *   2 = Timestamp-based (20240101_*, 8+ digits)
     */
    private static function getCategory(string $filename): int
    {
        // Timestamp-based: starts with 8+ digits followed by underscore
        if (preg_match('/^\d{8,}_/', $filename) === 1) {
            return 2;
        }

        // Numbered: starts with 1-3 digits followed by underscore
        if (preg_match('/^\d{1,3}_/', $filename) === 1) {
            return 0;
        }

        // Everything else: non-numbered
        return 1;
    }
}
