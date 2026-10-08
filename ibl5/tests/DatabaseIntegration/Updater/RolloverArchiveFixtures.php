<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\Updater;

use BulkImport\ArchiveExtractor;
use BulkImport\BackupArchiveLocator;
use LeagueConfig\LgeFileParser;
use Updater\SeasonRollover\SeasonRolloverDetector;

/**
 * Real-ZIP archive fixtures shared by the season-rollover integration tests.
 *
 * Builds IBL5.lge archives under a per-test tmpDir and wires a real
 * SeasonRolloverDetector against it.
 *
 * @phpstan-require-extends \Tests\DatabaseIntegration\DatabaseTestCase
 */
trait RolloverArchiveFixtures
{
    private string $tmpDir;

    private function createRolloverTmpDir(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/ibl_rollover_' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
    }

    private function removeRolloverTmpDir(): void
    {
        if (isset($this->tmpDir) && is_dir($this->tmpDir)) {
            $this->removeTree($this->tmpDir);
        }
    }

    /**
     * Build an IBL5.lge buffer with the season-info block at SEASON_INFO_OFFSET.
     *
     * Layout (from LgeFileParser::parseSeasonMetadata):
     *   offset+0  : year (4 chars)         → beginYear
     *   offset+4  : season_number (4 chars) → '0' maps to phase='Unknown' (non-'1', non-'2')
     *   offset+8  : field1 (2 chars)        → unused padding
     *   offset+10 : team_count (2 chars)    → teamCount
     *
     * season_ending_year = beginYear + 1 (computed by parser, not stored).
     * Using season_number=0 triggers the 'Unknown' branch in the detector,
     * which falls back to the archive filename's phase slug.
     */
    private function buildLgeBuffer(int $beginYear, int $seasonNumber = 0, int $teamCount = 8): string
    {
        $offset = LgeFileParser::SEASON_INFO_OFFSET;
        return str_repeat(' ', $offset)
            . str_pad((string) $beginYear, 4)
            . str_pad((string) $seasonNumber, 4)
            . str_pad('', 2)
            . str_pad((string) $teamCount, 2);
    }

    /**
     * Create season-backup subdirectories under $this->tmpDir/backups/.
     *
     * @param list<string> $labels e.g. ['25-26', '26-27']
     */
    private function makeBackupDirs(array $labels): void
    {
        foreach ($labels as $label) {
            mkdir($this->tmpDir . '/backups/' . $label, 0777, true);
        }
    }

    /**
     * Write a ZIP archive at $this->tmpDir/backups/$seasonLabel/$name.zip
     * containing a single entry 'IBL5.lge' built from $beginYear.
     * season_ending_year = beginYear + 1 (as LgeFileParser computes it).
     */
    private function createArchive(string $seasonLabel, string $name, int $beginYear): void
    {
        $zipPath    = $this->tmpDir . '/backups/' . $seasonLabel . '/' . $name . '.zip';
        $lgeContent = $this->buildLgeBuffer($beginYear);

        $zip    = new \ZipArchive();
        $opened = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($opened !== true) {
            self::fail(sprintf('Failed to create ZIP at %s: ZipArchive error %d', $zipPath, $opened));
        }
        $zip->addFromString('IBL5.lge', $lgeContent);
        $zip->close();
    }

    /**
     * Build a SeasonRolloverDetector wired with real production classes.
     */
    private function buildDetector(): SeasonRolloverDetector
    {
        $extractor = new ArchiveExtractor();
        $locator   = new BackupArchiveLocator($extractor);
        return new SeasonRolloverDetector($locator, $extractor, $this->tmpDir, 'IBL5');
    }

    /**
     * Read a setting value directly from ibl_settings (league='ibl').
     * Uses a raw prepared statement so a repository that silently no-ops
     * cannot make this assertion pass when the write did not happen.
     */
    private function readSetting(string $key): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT setting_value FROM `ibl_settings` WHERE setting_key = ? AND league = 'ibl'"
        );
        self::assertNotFalse($stmt, 'Failed to prepare readSetting: ' . $this->db->error);
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $result = $stmt->get_result();
        $row    = $result->fetch_assoc();
        $stmt->close();

        return is_array($row) ? (string) $row['setting_value'] : null;
    }

    /**
     * Recursively remove a directory tree.
     */
    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        /** @var list<string>|false $items */
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeTree($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
