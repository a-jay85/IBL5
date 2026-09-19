<?php

declare(strict_types=1);

namespace Updater\SeasonRollover;

use BulkImport\BackupArchiveLocator;
use BulkImport\Contracts\ArchiveExtractorInterface;
use BulkImport\Contracts\BackupArchiveLocatorInterface;
use LeagueConfig\LgeFileParser;

/**
 * Reads the most-recent backup archive for the current or next season and decides
 * whether an automatic season rollover is needed.
 *
 * The class has no knowledge of the database, $_POST, or any Season object.
 * Callers own skip-condition checks; this class only reads the filesystem and decides.
 */
final class SeasonRolloverDetector
{
    /** Phase name used when the .lge season_number and archive filename both fail to identify the phase. */
    public const DEFAULT_PHASE = 'Preseason';

    public function __construct(
        private readonly BackupArchiveLocatorInterface $locator,
        private readonly ArchiveExtractorInterface $extractor,
        private readonly string $basePath,
        private readonly string $filePrefix,
    ) {
    }

    /**
     * Detect whether a season rollover is required based on the latest backup archive.
     *
     * The next-season backup folder is checked first; if it contains an archive, that
     * archive wins — a freshly-uploaded new-season file lands in a folder the current
     * season settings would never look at, so the dual-folder peek is the whole point.
     */
    public function detect(int $currentBeginningYear, int $currentEndingYear): SeasonRolloverDecisionResult
    {
        $currentDir = $this->basePath . '/backups/' . BackupArchiveLocator::seasonLabel($currentBeginningYear, $currentEndingYear);
        $nextDir    = $this->basePath . '/backups/' . BackupArchiveLocator::seasonLabel($currentEndingYear, $currentEndingYear + 1);

        $archivePath = $this->locator->findLatestArchive($nextDir) ?? $this->locator->findLatestArchive($currentDir);

        if ($archivePath === null) {
            return SeasonRolloverDecisionResult::noOp(sprintf(
                'No backup archive found in %s or %s; skipping rollover detection.',
                $nextDir,
                $currentDir,
            ));
        }

        $contents = $this->extractor->extractToString($archivePath, $this->filePrefix . '.lge');

        if ($contents === false) {
            return SeasonRolloverDecisionResult::noOp(sprintf(
                'Archive %s contains no %s.lge; skipping rollover detection.',
                basename($archivePath),
                $this->filePrefix,
            ));
        }

        $meta = LgeFileParser::parseSeasonMetadata($contents);

        $phase = $meta['phase'];
        if ($phase === 'Unknown') {
            $parsed = $this->extractor->parseArchiveName(basename($archivePath));
            $phase  = $parsed !== null
                ? (BackupArchiveLocator::phaseFromSlug($parsed['phase']) ?? self::DEFAULT_PHASE)
                : self::DEFAULT_PHASE;
        }

        return SeasonRolloverDecision::decide($currentEndingYear, $meta['season_ending_year'], $phase);
    }
}
