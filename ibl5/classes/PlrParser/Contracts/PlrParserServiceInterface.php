<?php

declare(strict_types=1);

namespace PlrParser\Contracts;

use PlrParser\PlrImportMode;
use PlrParser\PlrParseResult;

/**
 * Service interface for PLR file processing.
 */
interface PlrParserServiceInterface
{
    /**
     * Process a .plr file — parse all players, upsert into DB, assign team names.
     *
     * @param string $filePath Absolute path to the .plr file
     * @return PlrParseResult Result summary
     */
    public function processPlrFile(string $filePath): PlrParseResult;

    /**
     * Process a .plr file with an explicit ending year, bypassing the Season dependency.
     *
     * In Live mode: upserts players + historical stats (same as processPlrFile).
     * In Snapshot mode: upserts rating snapshots into ibl_plr_snapshots.
     *
     * @param string $filePath Absolute path to the .plr file
     * @param int $endingYear Season ending year for draft year calculation
     * @param PlrImportMode $mode Import mode (Live or Snapshot)
     * @param string|null $snapshotPhase Phase label for snapshots (e.g. 'end-of-season')
     * @param string|null $sourceArchive Source archive filename for snapshots
     * @return PlrParseResult Result summary
     */
    public function processPlrFileForYear(
        string $filePath,
        int $endingYear,
        PlrImportMode $mode,
        ?string $snapshotPhase = null,
        ?string $sourceArchive = null,
    ): PlrParseResult;
}
