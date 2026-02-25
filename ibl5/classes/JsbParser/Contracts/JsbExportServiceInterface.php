<?php

declare(strict_types=1);

namespace JsbParser\Contracts;

use JsbParser\PlrWriteResult;

/**
 * Interface for the JSB file export orchestrator.
 *
 * Coordinates reading database state and writing it to .plr and .trn files.
 */
interface JsbExportServiceInterface
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

    /**
     * Export trade transactions to a .trn file.
     *
     * Generates a complete .trn file from completed trade data in the database.
     *
     * @param string $outputPath Path for the output .trn file
     * @return PlrWriteResult Summary of records written
     * @throws \RuntimeException If file operations fail
     */
    public function exportTrnFile(string $outputPath): PlrWriteResult;
}
