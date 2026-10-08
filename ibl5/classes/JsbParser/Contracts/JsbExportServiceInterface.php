<?php

declare(strict_types=1);

namespace JsbParser\Contracts;

use PlrParser\PlrWriteResult;

/**
 * Interface for the JSB .trn trade export.
 *
 * The .plr export contract is PlrParser\Contracts\PlrExportServiceInterface.
 */
interface JsbExportServiceInterface
{
    /**
     * Export trade transactions to a .trn file.
     *
     * Generates a complete .trn file from completed trade data in the database,
     * filtered to only include trades from the current season.
     *
     * @param string $outputPath Path for the output .trn file
     * @param string $seasonStartDate ISO date string (e.g., '2025-07-01') for season filtering
     * @return PlrWriteResult Summary of records written
     * @throws \RuntimeException If file operations fail
     */
    public function exportTrnFile(string $outputPath, string $seasonStartDate): PlrWriteResult;
}
