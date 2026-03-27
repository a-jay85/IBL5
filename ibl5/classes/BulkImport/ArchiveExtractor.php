<?php

declare(strict_types=1);

namespace BulkImport;

use BulkImport\Contracts\ArchiveExtractorInterface;
use ZipArchive;

/**
 * Extracts individual files from JSB backup archives (zip/rar).
 *
 * Handles the standardized archive naming convention:
 *   {season}_{NN}_{phase-detail}.{ext}
 * e.g. "00-01_06_reg-sim01.zip"
 */
final class ArchiveExtractor implements ArchiveExtractorInterface
{
    private const JSB_FILE_PREFIX = 'IBL5';

    /** @var list<string> HEAT phase slugs in priority order (most definitive first) */
    private const HEAT_END_SLUGS = [
        'heat-end',
        'heat-finals',
        'post-heat',
        'heat-wb',
        'heat-lb',
    ];

    public function extractSingleFile(string $archivePath, string $filename, string $targetDir): string|false
    {
        $format = $this->detectFormat($archivePath);

        if ($format === 'rar') {
            return $this->extractFromRar($archivePath, $filename, $targetDir);
        }

        return $this->extractFromZip($archivePath, $filename, $targetDir);
    }

    public function cleanupTemp(string $tempPath): void
    {
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }
    }

    public function detectFormat(string $archivePath): string
    {
        $ext = strtolower(pathinfo($archivePath, PATHINFO_EXTENSION));

        return $ext === 'rar' ? 'rar' : 'zip';
    }

    public function parseArchiveName(string $filename): ?array
    {
        // Strip extension
        $basename = pathinfo($filename, PATHINFO_FILENAME);

        // Expected: {season}_{NN}_{phase}
        // season = "00-01" or "88-89" etc.
        if (preg_match('/^(\d{2}-\d{2})_(\d{2})_(.+)$/', $basename, $matches) !== 1) {
            return null;
        }

        $seasonLabel = $matches[1];
        $seq = (int) $matches[2];
        $phase = $matches[3];
        $endingYear = $this->seasonLabelToEndingYear($seasonLabel);

        return [
            'season' => $seasonLabel,
            'seq' => $seq,
            'phase' => $phase,
            'ending_year' => $endingYear,
        ];
    }

    public function findLastArchive(string $seasonDir): ?string
    {
        $archives = $this->listArchives($seasonDir);
        if ($archives === []) {
            return null;
        }

        $best = null;
        $bestSeq = -1;

        foreach ($archives as $path) {
            $parsed = $this->parseArchiveName(basename($path));
            if ($parsed === null) {
                continue;
            }
            if ($parsed['seq'] > $bestSeq) {
                $bestSeq = $parsed['seq'];
                $best = $path;
            }
        }

        return $best;
    }

    public function findHeatEndArchive(string $seasonDir): ?string
    {
        $archives = $this->listArchives($seasonDir);
        if ($archives === []) {
            return null;
        }

        // Try each HEAT slug in priority order
        foreach (self::HEAT_END_SLUGS as $slug) {
            foreach ($archives as $path) {
                $parsed = $this->parseArchiveName(basename($path));
                if ($parsed === null) {
                    continue;
                }
                if ($parsed['phase'] === $slug) {
                    return $path;
                }
            }
        }

        // Fallback: find the last archive with any 'heat' in the phase
        $bestHeat = null;
        $bestSeq = -1;

        foreach ($archives as $path) {
            $parsed = $this->parseArchiveName(basename($path));
            if ($parsed === null) {
                continue;
            }
            if (str_contains($parsed['phase'], 'heat') && $parsed['seq'] > $bestSeq) {
                $bestSeq = $parsed['seq'];
                $bestHeat = $path;
            }
        }

        return $bestHeat;
    }

    public function seasonLabelToEndingYear(string $seasonLabel): int
    {
        // "88-89" → take the second part "89"
        $parts = explode('-', $seasonLabel);
        if (count($parts) !== 2) {
            return 0;
        }

        $endPart = (int) $parts[1];

        return $endPart >= 50 ? 1900 + $endPart : 2000 + $endPart;
    }

    /**
     * Build the JSB filename for a given extension.
     *
     * @param string $extension File extension without dot (e.g. 'plr', 'sco')
     */
    public function jsbFilename(string $extension): string
    {
        return self::JSB_FILE_PREFIX . '.' . $extension;
    }

    /**
     * List all archive files (zip/rar) in a directory.
     *
     * @return list<string> Full paths, sorted alphabetically
     */
    private function listArchives(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        /** @var list<string>|false $files */
        $files = glob($dir . '/*.{zip,rar}', GLOB_BRACE);
        if ($files === false || $files === []) {
            return [];
        }

        sort($files);

        return $files;
    }

    private function extractFromZip(string $archivePath, string $filename, string $targetDir): string|false
    {
        $zip = new ZipArchive();
        $result = $zip->open($archivePath, ZipArchive::RDONLY);
        if ($result !== true) {
            return false;
        }

        $index = $zip->locateName($filename);
        if ($index === false) {
            $zip->close();
            return false;
        }

        $targetPath = $targetDir . '/' . basename($filename);
        $stream = $zip->getStream($filename);
        if ($stream === false) {
            $zip->close();
            return false;
        }

        $contents = stream_get_contents($stream);
        fclose($stream);
        $zip->close();

        if ($contents === false) {
            return false;
        }

        if (file_put_contents($targetPath, $contents) === false) {
            return false;
        }

        return $targetPath;
    }

    private function extractFromRar(string $archivePath, string $filename, string $targetDir): string|false
    {
        $targetPath = $targetDir . '/' . basename($filename);

        // Try unrar first, then 7z as fallback
        $unrarBin = $this->findBinary('unrar');
        if ($unrarBin !== null) {
            $cmd = sprintf(
                '%s p -inul %s %s > %s 2>/dev/null',
                escapeshellarg($unrarBin),
                escapeshellarg($archivePath),
                escapeshellarg($filename),
                escapeshellarg($targetPath)
            );
            exec($cmd, $output, $exitCode);
            if ($exitCode === 0 && file_exists($targetPath) && filesize($targetPath) > 0) {
                return $targetPath;
            }
        }

        $sevenZipBin = $this->findBinary('7z');
        if ($sevenZipBin !== null) {
            $cmd = sprintf(
                '%s e -so %s %s > %s 2>/dev/null',
                escapeshellarg($sevenZipBin),
                escapeshellarg($archivePath),
                escapeshellarg($filename),
                escapeshellarg($targetPath)
            );
            exec($cmd, $output, $exitCode);
            if ($exitCode === 0 && file_exists($targetPath) && filesize($targetPath) > 0) {
                return $targetPath;
            }
        }

        return false;
    }

    private function findBinary(string $name): ?string
    {
        $cmd = sprintf('which %s 2>/dev/null', escapeshellarg($name));
        $result = exec($cmd, $output, $exitCode);

        return ($exitCode === 0 && $result !== false && $result !== '') ? $result : null;
    }
}
