<?php

declare(strict_types=1);

namespace Updater\Steps;

use PlrParser\PlrParserService;
use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Step 2: Parse player file (.plr).
 *
 * Skips if the .plr file is missing.
 */
class ParsePlayerFileStep implements PipelineStepInterface
{
    public function __construct(
        private readonly PlrParserService $service,
        private readonly string $plrPath,
    ) {
    }

    public function getLabel(): string
    {
        return 'Player file';
    }

    public function execute(): StepResult
    {
        if (!is_file($this->plrPath)) {
            return StepResult::skipped($this->getLabel(), 'No IBL5.plr file found (skipped)');
        }

        $plrResult = $this->service->processPlrFile($this->plrPath);

        return StepResult::success($this->getLabel() . ' parsed', $plrResult->summary());
    }
}
