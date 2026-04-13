<?php

declare(strict_types=1);

namespace BulkImport;

use BulkImport\Contracts\ArchiveExtractorInterface;
use JsbParser\JsbImportResult;
use JsbParser\PlayerIdResolver;
use PlrParser\PlrOrdinalMap;

/**
 * Orchestrates bulk import of JSB files from season archives.
 *
 * Builds the import plan (entries), extracts files from archives,
 * dispatches to FileTypeHandler, and aggregates results.
 */
final class BulkImportRunner
{
    public function __construct(
        private readonly ArchiveExtractorInterface $extractor,
        private readonly FileTypeHandler $handler,
        private readonly PlayerIdResolver $resolver,
        private readonly \mysqli $db,
    ) {
    }

    /**
     * Run the bulk import pipeline.
     *
     * @param list<JsbFileType> $fileTypes File types to process (pre-sorted by importOrder)
     */
    public function run(
        string $backupsDir,
        bool $isProduction,
        array $fileTypes,
        bool $dryRun,
        bool $verify,
        ?string $seasonFilter,
    ): BulkImportSummary {
        $summary = new BulkImportSummary();

        foreach ($fileTypes as $fileType) {
            echo "\n" . str_repeat('=', 50) . "\n";
            echo "Processing " . $fileType->label() . ($verify ? ' (verify)' : '') . "\n";
            echo str_repeat('=', 50) . "\n\n";

            $entries = $this->buildEntries($fileType, $backupsDir, $isProduction, $seasonFilter);

            if ($entries === []) {
                echo "  No entries found\n";
                continue;
            }

            if ($dryRun) {
                $this->printDryRun($fileType, $entries);
                continue;
            }

            $this->processEntries($fileType, $entries, $verify, $isProduction, $summary);
        }

        return $summary;
    }

    /**
     * Build the list of entries to process for a given file type.
     *
     * @return list<ImportEntry>
     */
    private function buildEntries(
        JsbFileType $fileType,
        string $backupsDir,
        bool $isProduction,
        ?string $seasonFilter,
    ): array {
        if ($isProduction) {
            return $this->buildEntriesFromArchives($fileType, $backupsDir, $seasonFilter);
        }

        return $this->buildEntriesFromLocalDirs($fileType, $backupsDir, $seasonFilter);
    }

    /**
     * Build entries from production-style archive directories.
     * Structure: backups/{season-label}/*.zip
     *
     * @return list<ImportEntry>
     */
    private function buildEntriesFromArchives(
        JsbFileType $fileType,
        string $backupsDir,
        ?string $seasonFilter,
    ): array {
        /** @var list<string>|false $seasonDirs */
        $seasonDirs = glob($backupsDir . '/*', GLOB_ONLYDIR);
        if ($seasonDirs === false || $seasonDirs === []) {
            return [];
        }
        sort($seasonDirs);

        $entries = [];

        foreach ($seasonDirs as $dirPath) {
            $seasonLabel = basename($dirPath);

            if ($seasonFilter !== null && $seasonLabel !== $seasonFilter) {
                continue;
            }

            $endingYear = $this->extractor->seasonLabelToEndingYear($seasonLabel);
            if ($endingYear === 0) {
                echo "  WARNING: Cannot parse season label '{$seasonLabel}', skipping\n";
                continue;
            }

            if ($fileType->iterationMode() === 'snapshot') {
                // Snapshot types: one entry per archive
                $archives = $this->extractor->findAllArchives($dirPath);
                foreach ($archives as $archive) {
                    $plrMap = null;
                    if ($fileType === JsbFileType::Plb) {
                        // PlrOrdinalMap is built once per season, reused across entries
                        // (handled below in processPlbSeason)
                    }

                    $entries[] = new ImportEntry(
                        path: $dirPath,
                        label: basename($archive['path']),
                        year: $archive['ending_year'],
                        phase: $archive['phase'],
                        archivePath: $archive['path'],
                        sourceLabel: pathinfo(basename($archive['path']), PATHINFO_FILENAME),
                        simNumber: $archive['seq'],
                    );
                }
            } elseif ($fileType === JsbFileType::Sco) {
                // .sco: emit both HEAT and season-end entries
                $heatArchive = $this->extractor->findHeatEndArchive($dirPath);
                if ($heatArchive !== null) {
                    $entries[] = new ImportEntry(
                        path: $dirPath,
                        label: basename($heatArchive),
                        year: $endingYear,
                        phase: 'HEAT',
                        archivePath: $heatArchive,
                        sourceLabel: pathinfo(basename($heatArchive), PATHINFO_FILENAME),
                    );
                }

                $finalsArchive = $this->extractor->findLastArchive($dirPath);
                if ($finalsArchive !== null) {
                    $entries[] = new ImportEntry(
                        path: $dirPath,
                        label: basename($finalsArchive),
                        year: $endingYear,
                        phase: 'Regular Season/Playoffs',
                        archivePath: $finalsArchive,
                        sourceLabel: pathinfo(basename($finalsArchive), PATHINFO_FILENAME),
                    );
                }
            } else {
                // Standard cumulative: one entry per season (final archive)
                $archive = $this->extractor->findLastArchive($dirPath);
                if ($archive === null) {
                    continue;
                }

                $entries[] = new ImportEntry(
                    path: $dirPath,
                    label: $seasonLabel,
                    year: $endingYear,
                    phase: 'Regular Season/Playoffs',
                    archivePath: $archive,
                    sourceLabel: pathinfo(basename($archive), PATHINFO_FILENAME),
                );
            }
        }

        return $entries;
    }

    /**
     * Build entries from local pre-extracted directories.
     * Structure: fullLeagueBackups/{dirName}/IBL5.ext
     *
     * @return list<ImportEntry>
     */
    private function buildEntriesFromLocalDirs(
        JsbFileType $fileType,
        string $backupsDir,
        ?string $seasonFilter,
    ): array {
        // For archive-based local imports (snapshot types and archive-aware processing),
        // use the backups/ subdirectory which has the standard archive structure
        $archiveDir = $backupsDir . '/backups';
        if ($fileType->iterationMode() === 'snapshot' && is_dir($archiveDir)) {
            return $this->buildEntriesFromArchives($fileType, $archiveDir, $seasonFilter);
        }

        // .sco also benefits from archive-based processing for HEAT + finals
        if ($fileType === JsbFileType::Sco && is_dir($archiveDir)) {
            return $this->buildEntriesFromArchives($fileType, $archiveDir, $seasonFilter);
        }

        // Pre-extracted directories
        /** @var list<string>|false $dirs */
        $dirs = glob($backupsDir . '/*', GLOB_ONLYDIR);
        if ($dirs === false || $dirs === []) {
            return [];
        }

        /** @var list<ImportEntry> $entries */
        $entries = [];

        foreach ($dirs as $dirPath) {
            $dirName = basename($dirPath);

            // Skip the backups/ subdirectory (handled separately)
            if ($dirName === 'backups') {
                continue;
            }

            $parsed = self::parseFolderName($dirName);
            if ($parsed['year'] === null || $parsed['phase'] === null) {
                continue;
            }

            if ($seasonFilter !== null) {
                // Convert year to season label for comparison
                $startYear = $parsed['year'] - 1;
                $endDigits = substr((string) $parsed['year'], 2);
                $startDigits = substr((string) $startYear, 2);
                $label = $startDigits . '-' . $endDigits;
                if ($label !== $seasonFilter) {
                    continue;
                }
            }

            $filePath = $dirPath . '/IBL5.' . $fileType->value;
            if (!file_exists($filePath)) {
                continue;
            }

            $entries[] = new ImportEntry(
                path: $dirPath,
                label: $dirName,
                year: $parsed['year'],
                phase: $parsed['phase'],
                archivePath: null,
                sourceLabel: $dirName,
            );
        }

        // Sort by year, then phase priority
        usort($entries, static function (ImportEntry $a, ImportEntry $b): int {
            if ($a->year !== $b->year) {
                return $a->year <=> $b->year;
            }
            $phaseOrder = ['Preseason' => 0, 'HEAT' => 1, 'Regular Season/Playoffs' => 2];
            return ($phaseOrder[$a->phase] ?? 9) <=> ($phaseOrder[$b->phase] ?? 9);
        });

        // Deduplicate: for cumulative types, keep only the last entry per year
        if ($fileType->iterationMode() === 'cumulative' && $fileType !== JsbFileType::Sco) {
            /** @var array<int, ImportEntry> $deduped */
            $deduped = [];
            foreach ($entries as $entry) {
                $deduped[$entry->year] = $entry;
            }
            return array_values($deduped);
        }

        return $entries;
    }

    /**
     * Process all entries for a file type.
     *
     * @param list<ImportEntry> $entries
     */
    private function processEntries(
        JsbFileType $fileType,
        array $entries,
        bool $verify,
        bool $isProduction,
        BulkImportSummary $summary,
    ): void {
        // .plb needs a PlrOrdinalMap per season, built from the HEAT .plr
        if ($fileType === JsbFileType::Plb) {
            $this->processPlbEntries($entries, $verify, $summary);
            return;
        }

        foreach ($entries as $i => $entry) {
            $num = $i + 1;
            echo sprintf(
                "[%d/%d] %s (%d %s)\n",
                $num,
                count($entries),
                $entry->label,
                $entry->year,
                $entry->phase,
            );

            $filePath = $this->resolveFilePath($fileType, $entry);
            if ($filePath === null) {
                echo "        File not found\n";
                continue;
            }

            try {
                $result = $this->executeWithVerifySupport($fileType, $filePath, $entry, $verify);

                echo "        {$result->summary()}\n";
                foreach ($result->messages as $msg) {
                    echo "        {$msg}\n";
                }

                $summary->addResult($result);
            } catch (\Throwable $e) {
                echo "        ERROR: {$e->getMessage()}\n";
                $summary->addError($e->getMessage());
            } finally {
                $this->cleanupExtracted($filePath, $entry);
            }

            $this->resolver->clearCache();
        }
    }

    /**
     * Process .plb entries with per-season PlrOrdinalMap building.
     *
     * @param list<ImportEntry> $entries
     */
    private function processPlbEntries(
        array $entries,
        bool $verify,
        BulkImportSummary $summary,
    ): void {
        // Group entries by season
        /** @var array<int, list<ImportEntry>> $byYear */
        $byYear = [];
        foreach ($entries as $entry) {
            $byYear[$entry->year][] = $entry;
        }

        $totalEntries = count($entries);
        $processed = 0;

        foreach ($byYear as $year => $seasonEntries) {
            echo sprintf("\n  Season ending %d (%d archives)\n", $year, count($seasonEntries));

            // Build PlrOrdinalMap from HEAT .plr
            $map = $this->buildPlrOrdinalMap($seasonEntries[0]->path);

            foreach ($seasonEntries as $entry) {
                $processed++;
                echo sprintf(
                    "  [%d/%d] %s\n",
                    $processed,
                    $totalEntries,
                    $entry->label,
                );

                $entryWithMap = new ImportEntry(
                    path: $entry->path,
                    label: $entry->label,
                    year: $entry->year,
                    phase: $entry->phase,
                    archivePath: $entry->archivePath,
                    sourceLabel: $entry->sourceLabel,
                    plrMap: $map,
                    simNumber: $entry->simNumber,
                );

                $filePath = $this->resolveFilePath(JsbFileType::Plb, $entryWithMap);
                if ($filePath === null) {
                    echo "        IBL5.plb not found\n";
                    continue;
                }

                try {
                    $result = $this->executeWithVerifySupport(
                        JsbFileType::Plb,
                        $filePath,
                        $entryWithMap,
                        $verify,
                    );

                    echo "        {$result->summary()}\n";
                    $summary->addResult($result);
                } catch (\Throwable $e) {
                    echo "        ERROR: {$e->getMessage()}\n";
                    $summary->addError($e->getMessage());
                } finally {
                    $this->cleanupExtracted($filePath, $entryWithMap);
                }
            }
        }
    }

    /**
     * Build a PlrOrdinalMap from the HEAT-end .plr in a season directory.
     */
    private function buildPlrOrdinalMap(string $seasonDir): PlrOrdinalMap
    {
        $heatArchive = $this->extractor->findHeatEndArchive($seasonDir);
        if ($heatArchive === null) {
            echo "    WARNING: No HEAT archive found — PIDs will be NULL\n";
            return PlrOrdinalMap::empty();
        }

        $tmpDir = sys_get_temp_dir() . '/ibl5_plb_' . bin2hex(random_bytes(8));
        if (!mkdir($tmpDir, 0700, true)) {
            return PlrOrdinalMap::empty();
        }

        $plrPath = $this->extractor->extractSingleFile(
            $heatArchive,
            $this->extractor->jsbFilename('plr'),
            $tmpDir,
        );

        if ($plrPath === false) {
            echo "    WARNING: IBL5.plr not found in HEAT archive\n";
            if (is_dir($tmpDir)) {
                rmdir($tmpDir);
            }
            return PlrOrdinalMap::empty();
        }

        try {
            $map = PlrOrdinalMap::fromPlrFile($plrPath);
            echo sprintf("    PLR ordinal map: %d players from %s\n", $map->count(), basename($heatArchive));
            return $map;
        } catch (\Throwable $e) {
            echo "    WARNING: PLR parse failed: {$e->getMessage()}\n";
            return PlrOrdinalMap::empty();
        } finally {
            $this->extractor->cleanupTemp($plrPath);
            if (is_dir($tmpDir)) {
                rmdir($tmpDir);
            }
        }
    }

    /**
     * Resolve a file path: extract from archive or return local path.
     */
    private function resolveFilePath(JsbFileType $fileType, ImportEntry $entry): ?string
    {
        if ($entry->archivePath !== null) {
            return $this->extractFromArchive($fileType, $entry->archivePath);
        }

        $path = $entry->path . '/IBL5.' . $fileType->value;
        return file_exists($path) ? $path : null;
    }

    /**
     * Extract a file from an archive into a temp directory.
     */
    private function extractFromArchive(JsbFileType $fileType, string $archivePath): ?string
    {
        $tmpDir = sys_get_temp_dir() . '/ibl5_import_' . bin2hex(random_bytes(8));
        if (!mkdir($tmpDir, 0700, true)) {
            echo "        ERROR: Could not create temp directory\n";
            return null;
        }

        $filename = $this->extractor->jsbFilename($fileType->value);
        $extracted = $this->extractor->extractSingleFile($archivePath, $filename, $tmpDir);
        if ($extracted === false) {
            rmdir($tmpDir);
            return null;
        }

        // .awa also needs companion .car for PID resolution
        if ($fileType === JsbFileType::Awa) {
            $this->extractor->extractSingleFile(
                $archivePath,
                $this->extractor->jsbFilename('car'),
                $tmpDir,
            );
        }

        return $extracted;
    }

    /**
     * Execute a file type handler with optional verify-mode transaction wrapping.
     */
    private function executeWithVerifySupport(
        JsbFileType $fileType,
        string $filePath,
        ImportEntry $entry,
        bool $verify,
    ): JsbImportResult {
        if (!$verify) {
            return $this->handler->process($fileType, $filePath, $entry);
        }

        $this->db->begin_transaction();
        try {
            $result = $this->handler->process($fileType, $filePath, $entry);
            return $result;
        } finally {
            $this->db->rollback();
        }
    }

    /**
     * Clean up temp files created by archive extraction.
     */
    private function cleanupExtracted(string $filePath, ImportEntry $entry): void
    {
        // Only clean up files extracted from archives (not pre-existing local files)
        if ($entry->archivePath === null) {
            return;
        }

        $this->extractor->cleanupTemp($filePath);

        $tmpDir = dirname($filePath);

        // Clean up companion .car if extracted for .awa
        $companionCar = $tmpDir . '/IBL5.car';
        if (file_exists($companionCar)) {
            unlink($companionCar);
        }

        if (is_dir($tmpDir)) {
            @rmdir($tmpDir);
        }
    }

    /**
     * Print dry-run output for a file type.
     *
     * @param list<ImportEntry> $entries
     */
    private function printDryRun(JsbFileType $fileType, array $entries): void
    {
        echo str_pad('Entry', 45) . str_pad('Year', 8) . "Phase\n";
        echo str_repeat('-', 80) . "\n";

        foreach ($entries as $entry) {
            echo str_pad($entry->label, 45)
                . str_pad((string) $entry->year, 8)
                . $entry->phase . "\n";
        }

        echo sprintf("\nTotal: %d entries for %s\n", count($entries), $fileType->label());
    }

    /**
     * Extract season ending year and phase from a pre-extracted directory name.
     *
     * Handles legacy naming like "IBL0607HEATend", "IBL0102Sim15", etc.
     *
     * @return array{year: int|null, phase: string|null}
     */
    public static function parseFolderName(string $name): array
    {
        $year = null;
        $phase = null;

        if (preg_match('/(\d{2})(\d{2})/', $name, $yearMatch) === 1) {
            $endPart = (int) $yearMatch[2];
            $year = $endPart >= 50 ? 1900 + $endPart : 2000 + $endPart;
        }

        $lower = strtolower($name);
        if (str_contains($lower, 'preseason')) {
            $phase = 'Preseason';
        } elseif (str_contains($lower, 'heat')) {
            $phase = 'HEAT';
        } elseif (
            str_contains($lower, 'playoff')
            || str_contains($lower, 'finals')
            || str_contains($lower, 'season')
            || preg_match('/sim\d*/i', $name) === 1
        ) {
            $phase = 'Regular Season/Playoffs';
        }

        return ['year' => $year, 'phase' => $phase];
    }
}
