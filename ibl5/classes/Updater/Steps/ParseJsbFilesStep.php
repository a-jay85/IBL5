<?php

declare(strict_types=1);

namespace Updater\Steps;

use JsbParser\JsbImportService;
use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Step 10: Parse JSB engine files (.car, .trn, .his, .asw, .rcb).
 *
 * Wraps JsbImportService::processCurrentSeason() and captures output.
 */
class ParseJsbFilesStep implements PipelineStepInterface
{
    public function __construct(
        private readonly JsbImportService $service,
        private readonly string $basePath,
        private readonly \Season $season,
    ) {
    }

    public function getLabel(): string
    {
        return 'JSB files parsed';
    }

    public function execute(): StepResult
    {
        ob_start();
        $jsbResult = $this->service->processCurrentSeason($this->basePath, $this->season);
        $log = (string) ob_get_clean();

        return StepResult::success(
            $this->getLabel(),
            $jsbResult->summary(),
            capturedLog: $log,
            messages: $jsbResult->messages,
            messageErrorCount: $jsbResult->errors,
        );
    }
}
