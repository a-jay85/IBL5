<?php

declare(strict_types=1);

namespace Updater;

use BulkImport\Contracts\ArchiveExtractorInterface;
use BulkImport\Contracts\BackupArchiveLocatorInterface;
use Updater\Contracts\JsbSourceResolverInterface;

/**
 * Resolves JSB file contents: archive-first, disk-fallback.
 *
 * The archive path is resolved lazily on each getContents() call because
 * ExtractFromBackupStep may rename the archive file between steps.
 */
final class JsbSourceResolver implements JsbSourceResolverInterface
{
    private ?SourceProvenance $lastSource = null;

    public function __construct(
        private readonly BackupArchiveLocatorInterface $locator,
        private readonly ArchiveExtractorInterface $extractor,
        private readonly string $seasonBackupDir,
        private readonly string $basePath,
        private readonly string $filePrefix,
    ) {
    }

    /** @see JsbSourceResolverInterface::getContents() */
    public function getContents(string $extension): ?string
    {
        // Reset stale provenance from an earlier extension so a failed read
        // cannot leave a previous extension's source name visible to the caller.
        $this->lastSource = null;

        $selection = $this->locator->describeSelection($this->seasonBackupDir);
        $archivePath = $selection?->path;

        if ($archivePath !== null) {
            $filename = $this->filePrefix . '.' . $extension;
            $contents = $this->extractor->extractToString($archivePath, $filename);
            if ($contents !== false) {
                $parsed = $this->extractor->parseArchiveName(basename($archivePath));
                $this->lastSource = new SourceProvenance(
                    kind: SourceProvenance::KIND_ARCHIVE,
                    name: basename($archivePath),
                    declaredSeason: $parsed['season'] ?? null,
                    declaredSeasonEndingYear: $parsed['ending_year'] ?? null,
                    declaredPhase: $parsed['phase'] ?? null,
                    selectionWarnings: $selection->warnings(),
                );

                return $contents;
            }
        }

        $diskPath = $this->basePath . '/' . $this->filePrefix . '.' . $extension;
        if (is_file($diskPath)) {
            $contents = file_get_contents($diskPath);
            if ($contents !== false) {
                $this->lastSource = new SourceProvenance(
                    kind: SourceProvenance::KIND_DISK,
                    name: basename($diskPath),
                    selectionWarnings: [],
                );

                return $contents;
            }
        }

        return null;
    }

    /** @see JsbSourceResolverInterface::describeLastSource() */
    public function describeLastSource(): ?SourceProvenance
    {
        return $this->lastSource;
    }
}
