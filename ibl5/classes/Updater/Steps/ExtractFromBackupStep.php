<?php

declare(strict_types=1);

namespace Updater\Steps;

use BulkImport\BackupArchiveLocator;
use BulkImport\Contracts\BackupArchiveLocatorInterface;
use Season\Season;
use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Step 0: Extract JSB files from the latest backup archive.
 *
 * Looks for the most recently uploaded backup in the current season's
 * backups directory and extracts all JSB files to the base path.
 * Subsequent pipeline steps then read from those extracted files.
 *
 * If no backup is found, the step is skipped and existing files at
 * the base path are used (backwards compatible with manual uploads).
 *
 * Also auto-renames mis-named backup archives to the standardized
 * {season}_{NN}_{phase}.{ext} convention.
 */
final class ExtractFromBackupStep implements PipelineStepInterface
{
    /** @var list<string> JSB file extensions to extract (all types now read directly from archive by JsbSourceResolver) */
    private const EXTENSIONS = [];

    public function __construct(
        private readonly BackupArchiveLocatorInterface $locator,
        private readonly Season $season,
        private readonly string $basePath,
        private readonly ?string $backupDir = null,
        private readonly bool $isOlympics = false,
    ) {
    }

    public function getLabel(): string
    {
        return 'Backup extracted';
    }

    public function execute(): StepResult
    {
        $seasonLabel = BackupArchiveLocator::seasonLabel(
            $this->season->beginningYear,
            $this->season->endingYear,
        );
        $backupDir = $this->backupDir ?? ($this->basePath . '/backups/' . $seasonLabel);

        $archivePath = $this->locator->findLatestArchive($backupDir);
        if ($archivePath === null) {
            return StepResult::skipped(
                $this->getLabel(),
                'No backup found in backups/' . $seasonLabel
                    . '/ — using existing files',
            );
        }

        $missingExtensions = [];
        $extractedCount = $this->extractFiles($archivePath, $missingExtensions);
        $renameMessage = $this->autoRenameIfNeeded($archivePath, $backupDir);

        $archiveName = basename($archivePath);
        $detail = sprintf(
            'Extracted %d files from %s',
            $extractedCount,
            $renameMessage ?? $archiveName,
        );

        /** @var list<string> $messages */
        $messages = [];
        if ($renameMessage !== null) {
            $messages[] = $renameMessage;
        }

        $foundMessage = sprintf(
            '%d of %d file types found in archive',
            $extractedCount,
            count(self::EXTENSIONS),
        );
        if ($missingExtensions !== []) {
            $foundMessage .= ' (missing: .' . implode(', .', $missingExtensions) . ')';
        }
        $messages[] = $foundMessage;

        return StepResult::success($this->getLabel(), $detail, messages: $messages);
    }

    /**
     * Extract JSB files from the archive to the base path.
     *
     * All file types are now read directly from archive by JsbSourceResolver,
     * so EXTENSIONS is empty and this always returns 0.
     *
     * @param list<string> $missingExtensions Populated with extensions not found in archive
     * @return int Number of files successfully extracted
     */
    private function extractFiles(string $archivePath, array &$missingExtensions): int
    {
        return 0;
    }

    /**
     * Rename the archive to the standardized convention if mis-named.
     *
     * @return string|null Rename description, or null if no rename needed
     */
    private function autoRenameIfNeeded(string $archivePath, string $backupDir): ?string
    {
        if ($this->locator->isProperlyNamed($archivePath)) {
            return null;
        }

        $originalName = basename($archivePath);
        $extension = strtolower(pathinfo($archivePath, PATHINFO_EXTENSION));
        $seasonLabel = BackupArchiveLocator::seasonLabel(
            $this->season->beginningYear,
            $this->season->endingYear,
        );
        if ($this->isOlympics) {
            $existing = glob($backupDir . '/' . $seasonLabel . '_*');
            $nextSeq = count($existing !== false ? $existing : []) + 1;
            $newName = sprintf('%s_%02d.%s', $seasonLabel, $nextSeq, $extension);
        } else {
            $newName = $this->locator->generateStandardizedName(
                $backupDir,
                $extension,
                $seasonLabel,
                $this->season->phase,
                $this->season->getPhaseSpecificSimNumber(),
            );
        }

        $newPath = $backupDir . '/' . $newName;

        if (file_exists($newPath)) {
            return 'Rename skipped (target exists): ' . $newName;
        }

        $renamed = rename($archivePath, $newPath);
        if (!$renamed) {
            return 'Rename failed: ' . $originalName . ' → ' . $newName;
        }

        return $newName . ' (renamed from ' . $originalName . ')';
    }
}
