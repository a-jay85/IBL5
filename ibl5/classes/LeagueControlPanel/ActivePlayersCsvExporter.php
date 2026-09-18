<?php

declare(strict_types=1);

namespace LeagueControlPanel;

use LeagueControlPanel\Contracts\LeagueControlPanelRepositoryInterface;

/**
 * Writes a CSV of every non-retired player name to a temp directory and
 * resolves previously written exports for re-download.
 *
 * Output format: one quoted name per line, LF line endings, no header row,
 * no BOM — matches the phpMyAdmin export the admins used before.
 */
class ActivePlayersCsvExporter
{
    private const FILENAME_PREFIX = 'iblhoops_ibl5_';
    private const FILENAME_PATTERN = '/^iblhoops_ibl5_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.csv$/';

    private LeagueControlPanelRepositoryInterface $repository;
    private string $exportDir;

    public function __construct(LeagueControlPanelRepositoryInterface $repository, ?string $exportDir = null)
    {
        $this->repository = $repository;
        $this->exportDir = $exportDir ?? sys_get_temp_dir() . '/ibl5-exports';
    }

    /**
     * Write the CSV and return its filename (not the full path).
     *
     * @throws \RuntimeException When the file cannot be written
     */
    public function export(\DateTimeImmutable $now): string
    {
        if (!is_dir($this->exportDir) && !mkdir($this->exportDir, 0700, true) && !is_dir($this->exportDir)) {
            throw new \RuntimeException('Could not create export directory.');
        }

        $filename = self::FILENAME_PREFIX . $now->format('Y-m-d_H-i-s') . '.csv';
        $csv = self::buildCsv($this->repository->getActivePlayerNames());

        if (file_put_contents($this->exportDir . '/' . $filename, $csv) === false) {
            throw new \RuntimeException('Could not write export file.');
        }

        return $filename;
    }

    /**
     * Full path of a previous export, or null when the name is not a valid
     * export filename or the file no longer exists.
     */
    public function resolvePath(string $filename): ?string
    {
        if (preg_match(self::FILENAME_PATTERN, $filename) !== 1) {
            return null;
        }

        $path = $this->exportDir . '/' . $filename;
        return is_file($path) ? $path : null;
    }

    /**
     * @param list<string> $names
     */
    public static function buildCsv(array $names): string
    {
        $csv = '';
        foreach ($names as $name) {
            $csv .= '"' . str_replace('"', '""', $name) . "\"\n";
        }
        return $csv;
    }
}
