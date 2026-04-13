<?php

declare(strict_types=1);

namespace BulkImport;

use Boxscore\BoxscoreProcessor;
use JsbParser\Contracts\JsbImportServiceInterface;
use JsbParser\JsbImportResult;
use LeagueConfig\LeagueConfigService;
use PlrParser\Contracts\PlrParserServiceInterface;
use PlrParser\PlrImportMode;

/**
 * Dispatches file processing to the correct service based on JsbFileType.
 *
 * Receives an already-extracted file path and delegates to the appropriate
 * parser/service. The caller (BulkImportRunner) handles extraction, temp
 * directory management, and cleanup.
 */
final class FileTypeHandler
{
    public function __construct(
        private readonly JsbImportServiceInterface $jsbService,
        private readonly BoxscoreProcessor $boxscoreProcessor,
        private readonly PlrParserServiceInterface $plrService,
        private readonly LeagueConfigService $lgeService,
    ) {
    }

    /**
     * Process a single extracted file through the correct service.
     *
     * @param string $filePath Path to the extracted file on disk
     * @param ImportEntry $entry Metadata about the file (year, phase, source label, etc.)
     */
    public function process(JsbFileType $type, string $filePath, ImportEntry $entry): JsbImportResult
    {
        return match ($type) {
            JsbFileType::Trn => $this->jsbService->processTrnFile($filePath, $entry->sourceLabel),
            JsbFileType::Car => $this->jsbService->processCarFile($filePath, null),
            JsbFileType::His => $this->jsbService->processHisFile($filePath, $entry->sourceLabel),
            JsbFileType::Asw => $this->jsbService->processAswFile($filePath, $entry->year),
            JsbFileType::Awa => $this->processAwa($filePath, $entry),
            JsbFileType::Rcb => $this->jsbService->processRcbFile($filePath, $entry->year, $entry->sourceLabel),
            JsbFileType::Sco => $this->processSco($filePath, $entry),
            JsbFileType::Dra => $this->jsbService->processDraFile($filePath),
            JsbFileType::Ret => $this->jsbService->processRetFile($filePath, $entry->year),
            JsbFileType::Hof => $this->jsbService->processHofFile($filePath),
            JsbFileType::Lge => $this->processLge($filePath),
            JsbFileType::Plr => $this->processPlr($filePath, $entry),
            JsbFileType::Plb => $this->processPlb($filePath, $entry),
        };
    }

    /**
     * .awa requires a companion .car file in the same directory for PID resolution.
     * The Runner extracts both files to the same temp dir.
     */
    private function processAwa(string $awaPath, ImportEntry $entry): JsbImportResult
    {
        $carPath = dirname($awaPath) . '/IBL5.car';

        if (!file_exists($carPath)) {
            $result = new JsbImportResult();
            $result->addError('Companion IBL5.car not found for .awa processing');
            return $result;
        }

        return $this->jsbService->processAwaFile($awaPath, $carPath);
    }

    /**
     * .sco calls processScoFile for regular games, and additionally
     * processAllStarGames for season-end (non-HEAT) entries.
     */
    private function processSco(string $filePath, ImportEntry $entry): JsbImportResult
    {
        $scoResult = $this->boxscoreProcessor->processScoFile(
            $filePath,
            $entry->year,
            $entry->phase,
            skipSimDates: true,
        );
        $result = JsbImportResult::fromScoResult($scoResult);

        // Process All-Star games only in season-end archives
        if ($entry->phase === 'Regular Season/Playoffs') {
            $allStarResult = $this->boxscoreProcessor->processAllStarGames($filePath, $entry->year);
            if (isset($allStarResult['messages'])) {
                foreach ($allStarResult['messages'] as $msg) {
                    $result->addMessage("All-Star: {$msg}");
                }
            }
        }

        return $result;
    }

    /**
     * .lge uses LeagueConfigService and bridges its array result to JsbImportResult.
     */
    private function processLge(string $filePath): JsbImportResult
    {
        $lgeResult = $this->lgeService->processLgeFile($filePath);
        $result = new JsbImportResult();

        if ($lgeResult['success']) {
            $result->addInserted($lgeResult['teams_stored']);
        } else {
            $result->addError($lgeResult['error'] ?? 'Unknown error');
        }

        return $result;
    }

    /**
     * .plr uses PlrParserService in snapshot mode and bridges PlrParseResult to JsbImportResult.
     */
    private function processPlr(string $filePath, ImportEntry $entry): JsbImportResult
    {
        $plrResult = $this->plrService->processPlrFileForYear(
            $filePath,
            $entry->year,
            PlrImportMode::Snapshot,
            $entry->phase,
            $entry->sourceLabel,
        );

        $result = new JsbImportResult();
        $result->addInserted($plrResult->playersUpserted);

        foreach ($plrResult->messages as $msg) {
            $result->addMessage($msg);
        }

        return $result;
    }

    /**
     * .plb uses JsbImportService with the pre-built PlrOrdinalMap from the entry.
     */
    private function processPlb(string $filePath, ImportEntry $entry): JsbImportResult
    {
        $map = $entry->plrMap ?? \PlrParser\PlrOrdinalMap::empty();

        return $this->jsbService->processPlbFile(
            $filePath,
            $map,
            $entry->year,
            $entry->simNumber ?? 0,
            $entry->sourceLabel,
        );
    }
}
