<?php

declare(strict_types=1);

namespace JsbParser\Contracts;

use JsbParser\JsbImportResult;

/**
 * Interface for the JSB import orchestration service.
 *
 * Coordinates parsing of JSB files, player/team ID resolution, and database storage.
 */
interface JsbImportServiceInterface
{
    /**
     * Process all JSB files for the current season from the base path.
     *
     * @param string $basePath Path to the ibl5 directory containing IBL5.car, IBL5.trn, etc.
     * @param \Season $season Current season object for year resolution
     * @return JsbImportResult Summary of import results
     */
    public function processCurrentSeason(string $basePath, \Season $season): JsbImportResult;

    /**
     * Process a .car file and upsert records into ibl_hist.
     *
     * @param string $filePath Path to the .car file
     * @param int|null $filterYear If set, only import records for this season year
     * @return JsbImportResult Summary of import results
     */
    public function processCarFile(string $filePath, ?int $filterYear = null): JsbImportResult;

    /**
     * Process a .trn file and upsert records into ibl_jsb_transactions.
     *
     * @param string $filePath Path to the .trn file
     * @param string|null $sourceLabel Label for the source_file column
     * @return JsbImportResult Summary of import results
     */
    public function processTrnFile(string $filePath, ?string $sourceLabel = null): JsbImportResult;

    /**
     * Process a .his file and upsert records into ibl_jsb_history.
     *
     * @param string $filePath Path to the .his file
     * @param string|null $sourceLabel Label for the source_file column
     * @return JsbImportResult Summary of import results
     */
    public function processHisFile(string $filePath, ?string $sourceLabel = null): JsbImportResult;

    /**
     * Process an .asw file and upsert records into ibl_jsb_allstar_* tables.
     *
     * @param string $filePath Path to the .asw file
     * @param int $seasonYear Season year for the All-Star data
     * @return JsbImportResult Summary of import results
     */
    public function processAswFile(string $filePath, int $seasonYear): JsbImportResult;

    /**
     * Process an .rcb file and upsert records into ibl_rcb_alltime_records and ibl_rcb_season_records.
     *
     * @param string $filePath Path to the .rcb file
     * @param int $seasonYear Season year for current season records
     * @param string|null $sourceLabel Label for the source_file column
     * @return JsbImportResult Summary of import results
     */
    public function processRcbFile(string $filePath, int $seasonYear, ?string $sourceLabel = null): JsbImportResult;
}
