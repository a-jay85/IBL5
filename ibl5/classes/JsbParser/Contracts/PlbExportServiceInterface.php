<?php

declare(strict_types=1);

namespace JsbParser\Contracts;

/**
 * Builds IBL5_export.plb in the slot order of the exported .plr.
 */
interface PlbExportServiceInterface
{
    /**
     * Write an export .plb whose slots follow the relocated export .plr.
     *
     * Moved players carry their source slot values, vacated slots are cleared,
     * and charted teams get the GM's DB values. Every other byte of the source
     * .plb is copied unchanged. The source files are never modified.
     *
     * @param string $sourcePlrPath The last-sim .plr the export was built from
     * @param string $exportPlrPath The exported .plr (post-relocation slot order)
     * @param string $sourcePlbPath The last-sim .plb
     * @param string $outputPlbPath Where to write the export .plb
     */
    public function exportPlbFile(
        string $sourcePlrPath,
        string $exportPlrPath,
        string $sourcePlbPath,
        string $outputPlbPath,
    ): \PlrParser\PlrWriteResult;
}
