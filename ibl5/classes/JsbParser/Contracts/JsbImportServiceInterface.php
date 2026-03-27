<?php

declare(strict_types=1);

namespace JsbParser\Contracts;

use JsbParser\JsbImportResult;
use JsbParser\PlrOrdinalMap;
use Season\Season;

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
     * @param Season $season Current season object for year resolution
     * @return JsbImportResult Summary of import results
     */
    public function processCurrentSeason(string $basePath, Season $season, string $filePrefix = 'IBL5'): JsbImportResult;

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
     * Process an .awa file and upsert stat leader awards into ibl_awards.
     *
     * Parses the binary .awa file, resolves PIDs to player names via a .car file,
     * and inserts award rows like "Scoring Leader (1st)" through "(5th)".
     *
     * @param string $awaPath Path to the .awa file
     * @param string $carPath Path to the .car file for PID→name resolution
     * @param int|null $filterYear If set, only import awards for this season year
     * @return JsbImportResult Summary of import results
     */
    public function processAwaFile(string $awaPath, string $carPath, ?int $filterYear = null): JsbImportResult;

    /**
     * Process an .rcb file and upsert records into ibl_rcb_alltime_records and ibl_rcb_season_records.
     *
     * @param string $filePath Path to the .rcb file
     * @param int $seasonYear Season year for current season records
     * @param string|null $sourceLabel Label for the source_file column
     * @return JsbImportResult Summary of import results
     */
    public function processRcbFile(string $filePath, int $seasonYear, ?string $sourceLabel = null): JsbImportResult;

    /**
     * Process a .plb file and upsert depth chart snapshots into ibl_plb_snapshots.
     *
     * @param string $filePath Path to the .plb file
     * @param PlrOrdinalMap $map Ordinal map for resolving player identity
     * @param int $seasonYear Season ending year
     * @param int $simNumber Archive sequence number
     * @param string $sourceArchive Archive basename without extension
     * @return JsbImportResult Summary of import results
     */
    public function processPlbFile(
        string $filePath,
        PlrOrdinalMap $map,
        int $seasonYear,
        int $simNumber,
        string $sourceArchive,
    ): JsbImportResult;

    /**
     * Process a .dra file and upsert records into ibl_jsb_draft_results.
     *
     * @param string $filePath Path to the .dra file
     * @return JsbImportResult Summary of import results
     */
    public function processDraFile(string $filePath): JsbImportResult;

    /**
     * Process a .ret file and upsert records into ibl_jsb_retired_players.
     *
     * @param string $filePath Path to the .ret file
     * @param int $retirementYear Season ending year when retirements occurred
     * @return JsbImportResult Summary of import results
     */
    public function processRetFile(string $filePath, int $retirementYear): JsbImportResult;

    /**
     * Process a .hof file and upsert records into ibl_jsb_hall_of_fame.
     *
     * @param string $filePath Path to the .hof file
     * @return JsbImportResult Summary of import results
     */
    public function processHofFile(string $filePath): JsbImportResult;
}
