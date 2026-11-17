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
     * Write IBL5_export.trn: JSB's IBL5.trn plus the site's trades it does not hold yet.
     *
     * Every record already in the input stays byte-identical. Each new trade becomes one
     * type-2 record per giving team, appended after the existing records, and the header
     * count goes up by the number appended. A trade already in the file (matched on season
     * year and items, in any order) is skipped. A trade that cannot be written, such as one
     * naming an unknown team or a pick with no `ibl_draft_picks` row, gets a WARNING line.
     *
     * @param string $inputPath JSB's current IBL5.trn
     * @param string $outputPath Path for IBL5_export.trn
     * @param string $seasonStartDate ISO date string (e.g., '2025-07-01') for season filtering
     * @param int $seasonBeginningYear Season beginning year, which JSB stores in every record
     * @param \DateTimeImmutable $jsbDate League date stamped on new records (the day after the last sim)
     * @return PlrWriteResult Messages, warnings and errors; nothing is written on an error
     * @throws \RuntimeException If the output file cannot be written
     */
    public function exportTrnFile(
        string $inputPath,
        string $outputPath,
        string $seasonStartDate,
        int $seasonBeginningYear,
        \DateTimeImmutable $jsbDate,
    ): PlrWriteResult;
}
