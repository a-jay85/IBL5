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
        $archivePath = $this->locator->findLatestArchive($this->seasonBackupDir);
        if ($archivePath !== null) {
            $filename = $this->filePrefix . '.' . $extension;
            $contents = $this->extractor->extractToString($archivePath, $filename);
            if ($contents !== false) {
                return $contents;
            }
        }

        $diskPath = $this->basePath . '/' . $this->filePrefix . '.' . $extension;
        if (is_file($diskPath)) {
            $contents = file_get_contents($diskPath);
            return $contents !== false ? $contents : null;
        }

        return null;
    }
}
