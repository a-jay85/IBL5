<?php

declare(strict_types=1);

namespace BulkImport;

use BulkImport\Contracts\ArchiveExtractorInterface;
use BulkImport\Contracts\BackupArchiveLocatorInterface;

/**
 * Locates and validates backup archives in season directories.
 *
 * Handles both properly-named archives ({season}_{NN}_{phase}.{ext})
 * and mis-named uploads (e.g. IBL2526Sim15.zip) by falling back to
 * modification time when no standardized name is found.
 */
class BackupArchiveLocator implements BackupArchiveLocatorInterface
{
    /** @var array<string, string> Maps Season::$phase to naming convention slug */
    private const PHASE_SLUG_MAP = [
        'Preseason' => 'preseason',
        'HEAT' => 'heat',
        'Regular Season' => 'reg-sim',
        'Playoffs' => 'playoffs',
        'Draft' => 'offseason-postdraft',
        'Free Agency' => 'offseason-postfa',
    ];

    public function __construct(
        private readonly ArchiveExtractorInterface $extractor,
    ) {
    }

    /**
     * Find the most recent archive in a season's backup directory.
     *
     * Returns the archive with the newest modification time, handling both
     * properly-named and mis-named uploads.
     *
     * @param string $seasonBackupDir Full path to the season backup directory
     * @return string|null Full path to the archive, or null if none found
     */
    public function findLatestArchive(string $seasonBackupDir): ?string
    {
        if (!is_dir($seasonBackupDir)) {
            return null;
        }

        $archives = $this->listArchives($seasonBackupDir);
        if ($archives === []) {
            return null;
        }

        $latest = null;
        $latestMtime = 0;

        foreach ($archives as $path) {
            $mtime = filemtime($path);
            if ($mtime !== false && $mtime > $latestMtime) {
                $latestMtime = $mtime;
                $latest = $path;
            }
        }

        return $latest;
    }

    /**
     * Build the season label from beginning and ending years.
     *
     * @return string E.g., "25-26" for season ending 2026
     */
    public static function seasonLabel(int $beginningYear, int $endingYear): string
    {
        return sprintf('%02d-%02d', $beginningYear % 100, $endingYear % 100);
    }

    /**
     * Check if an archive follows the standardized naming convention.
     *
     * @param string $archivePath Full path to the archive file
     */
    public function isProperlyNamed(string $archivePath): bool
    {
        return $this->extractor->parseArchiveName(basename($archivePath)) !== null;
    }

    /**
     * Generate the standardized name for a backup based on current season state.
     *
     * @param string $seasonBackupDir Directory containing existing archives (for sequence numbering)
     * @param string $archiveExtension File extension without dot (e.g. 'zip', 'rar')
     * @param string $seasonLabel Season label (e.g. "25-26")
     * @param string $phase Current season phase (e.g. "Regular Season")
     * @param int $phaseSimNumber Sim number within the current phase
     * @return string New filename (not path), e.g., "25-26_15_reg-sim15.zip"
     */
    public function generateStandardizedName(
        string $seasonBackupDir,
        string $archiveExtension,
        string $seasonLabel,
        string $phase,
        int $phaseSimNumber,
    ): string {
        $nextSeq = $this->countProperlyNamedArchives($seasonBackupDir) + 1;
        $phaseSlug = $this->buildPhaseSlug($phase, $phaseSimNumber);

        return sprintf('%s_%02d_%s.%s', $seasonLabel, $nextSeq, $phaseSlug, $archiveExtension);
    }

    /**
     * Build the phase slug from season phase and sim number.
     *
     * Regular Season gets a precise sim number (e.g., "reg-sim15").
     * Other phases use generic slugs.
     */
    private function buildPhaseSlug(string $phase, int $phaseSimNumber): string
    {
        $baseSlug = self::PHASE_SLUG_MAP[$phase] ?? 'unknown';

        if ($phase === 'Regular Season') {
            return sprintf('reg-sim%02d', $phaseSimNumber);
        }

        return $baseSlug;
    }

    /**
     * Count archives in a directory that follow the standardized naming convention.
     */
    private function countProperlyNamedArchives(string $seasonBackupDir): int
    {
        if (!is_dir($seasonBackupDir)) {
            return 0;
        }

        $count = 0;
        foreach ($this->listArchives($seasonBackupDir) as $path) {
            if ($this->extractor->parseArchiveName(basename($path)) !== null) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * List all archive files (zip/rar) in a directory.
     *
     * @return list<string> Full paths, sorted alphabetically
     */
    private function listArchives(string $dir): array
    {
        /** @var list<string>|false $files */
        $files = glob($dir . '/*.{zip,rar,ZIP,RAR}', GLOB_BRACE);
        if ($files === false || $files === []) {
            return [];
        }

        sort($files);

        return $files;
    }
}
