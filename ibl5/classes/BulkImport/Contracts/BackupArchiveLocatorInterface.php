<?php

declare(strict_types=1);

namespace BulkImport\Contracts;

/**
 * Locates and validates backup archives in season directories.
 */
interface BackupArchiveLocatorInterface
{
    /**
     * Find the most recent archive in a season's backup directory.
     *
     * @param string $seasonBackupDir Full path to the season backup directory
     * @return string|null Full path to the archive, or null if none found
     */
    public function findLatestArchive(string $seasonBackupDir): ?string;

    /**
     * Check if an archive follows the standardized naming convention.
     *
     * @param string $archivePath Full path to the archive file
     */
    public function isProperlyNamed(string $archivePath): bool;

    /**
     * Generate the standardized name for a backup based on season state.
     *
     * @param string $seasonBackupDir Directory containing existing archives
     * @param string $archiveExtension File extension without dot
     * @param string $seasonLabel Season label (e.g. "25-26")
     * @param string $phase Current season phase
     * @param int $phaseSimNumber Sim number within the current phase
     * @return string New filename (not path)
     */
    public function generateStandardizedName(
        string $seasonBackupDir,
        string $archiveExtension,
        string $seasonLabel,
        string $phase,
        int $phaseSimNumber,
    ): string;
}
