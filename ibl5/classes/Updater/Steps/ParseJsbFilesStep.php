<?php

declare(strict_types=1);

namespace Updater\Steps;

use JsbParser\JsbImportService;
use JsbParser\JsbImportResult;
use Updater\Contracts\JsbSourceResolverInterface;
use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Step 10: Parse JSB engine files (.car, .trn, .his, .asw, .rcb).
 *
 * Reads each file from the resolver (archive-first, disk-fallback) and
 * imports via JsbImportService process*Data() methods.
 */
class ParseJsbFilesStep implements PipelineStepInterface
{
    public function __construct(
        private readonly JsbImportService $service,
        private readonly JsbSourceResolverInterface $sourceResolver,
        private readonly int $seasonYear,
    ) {
    }

    public function getLabel(): string
    {
        return 'JSB files parsed';
    }

    public function execute(): StepResult
    {
        $result = new JsbImportResult();

        // Process .trn first (trade data helps with player ID resolution)
        $trnData = $this->sourceResolver->getContents('trn');
        if ($trnData !== null) {
            $trnResult = $this->service->processTrnData($trnData, 'current-season');
            $result->merge($trnResult);
            $result->addMessage('TRN: ' . $trnResult->summary());
        }

        // Process .car (uses trade data for mid-season splits)
        $carData = $this->sourceResolver->getContents('car');
        if ($carData !== null) {
            $carResult = $this->service->processCarData($carData, $this->seasonYear);
            $result->merge($carResult);
            $result->addMessage('CAR: ' . $carResult->summary());
        }

        // Process .his
        $hisData = $this->sourceResolver->getContents('his');
        if ($hisData !== null) {
            $hisResult = $this->service->processHisData($hisData, 'current-season');
            $result->merge($hisResult);
            $result->addMessage('HIS: ' . $hisResult->summary());
        }

        // Process .asw
        $aswData = $this->sourceResolver->getContents('asw');
        if ($aswData !== null) {
            $aswResult = $this->service->processAswData($aswData, $this->seasonYear);
            $result->merge($aswResult);
            $result->addMessage('ASW: ' . $aswResult->summary());
        }

        // Process .rcb (Record Book)
        $rcbData = $this->sourceResolver->getContents('rcb');
        if ($rcbData !== null) {
            $rcbResult = $this->service->processRcbData($rcbData, $this->seasonYear, 'current-season');
            $result->merge($rcbResult);
            $result->addMessage('RCB: ' . $rcbResult->summary());
        }

        return StepResult::success(
            $this->getLabel(),
            $result->summary(),
            messages: $result->messages,
            messageErrorCount: $result->errors,
        );
    }
}
