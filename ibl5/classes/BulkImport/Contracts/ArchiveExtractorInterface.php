<?php

declare(strict_types=1);

namespace BulkImport\Contracts;

interface ArchiveExtractorInterface
{
    /**
     * Extract a single file from an archive to a temporary directory.
     *
     * @return string|false Path to extracted file, or false on failure
     */
    public function extractSingleFile(string $archivePath, string $filename, string $targetDir): string|false;

    /**
     * Remove a temporary file created by extractSingleFile().
     */
    public function cleanupTemp(string $tempPath): void;

    /**
     * Detect archive format from file extension.
     *
     * @return 'zip'|'rar'
     */
    public function detectFormat(string $archivePath): string;

    /**
     * Parse a standardized archive filename into season metadata.
     *
     * Expected format: {season}_{NN}_{phase-detail}.{ext}
     * Example: "00-01_06_reg-sim01.zip" → season="00-01", seq=6, phase="reg-sim01", ending_year=2001
     *
     * @return array{season: string, seq: int, phase: string, ending_year: int}|null
     */
    public function parseArchiveName(string $filename): ?array;

    /**
     * Find the archive with the highest sequence number in a season directory.
     *
     * @return string|null Full path to the archive, or null if none found
     */
    public function findLastArchive(string $seasonDir): ?string;

    /**
     * Find the last HEAT-phase archive in a season directory.
     *
     * Looks for archives with phase slugs containing 'heat-end', 'heat-wb',
     * 'heat-finals', 'post-heat', or 'heat-lb' (in priority order).
     *
     * @return string|null Full path to the archive, or null if none found
     */
    public function findHeatEndArchive(string $seasonDir): ?string;

    /**
     * Compute the IBL season ending year from a season label.
     *
     * "88-89" → 1989, "00-01" → 2001, "06-07" → 2007
     */
    public function seasonLabelToEndingYear(string $seasonLabel): int;
}
