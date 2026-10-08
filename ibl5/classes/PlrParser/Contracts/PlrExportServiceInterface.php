<?php

declare(strict_types=1);

namespace PlrParser\Contracts;

use PlrParser\PlrWriteResult;

/**
 * Exports database player state into an existing .plr file (read-modify-write).
 */
interface PlrExportServiceInterface
{
    /**
     * Export database state to a .plr file using read-modify-write.
     *
     * Reads the existing .plr file, compares each player's database values
     * to the file values, and writes only the fields that differ.
     *
     * @param string $inputPath Path to the existing .plr file (read baseline)
     * @param string $outputPath Path for the output .plr file (NEVER the same as input)
     * @return PlrWriteResult Summary of changes made
     * @throws \RuntimeException If file operations fail
     */
    public function exportPlrFile(string $inputPath, string $outputPath): PlrWriteResult;
}
