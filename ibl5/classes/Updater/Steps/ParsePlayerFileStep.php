<?php

declare(strict_types=1);

namespace Updater\Steps;

use PlrParser\Contracts\PlrParserServiceInterface;
use Updater\Contracts\JsbSourceResolverInterface;
use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Step 2: Parse player file (.plr).
 *
 * Skips if the .plr contents are unavailable from the JSB source resolver.
 */
class ParsePlayerFileStep implements PipelineStepInterface
{
    public function __construct(
        private readonly PlrParserServiceInterface $service,
        private readonly JsbSourceResolverInterface $sourceResolver,
    ) {
    }

    public function getLabel(): string
    {
        return 'Player file';
    }

    public function execute(): StepResult
    {
        $data = $this->sourceResolver->getContents('plr');
        if ($data === null) {
            return StepResult::skipped($this->getLabel(), 'No IBL5.plr file found (skipped)');
        }

        $plrResult = $this->service->processPlrData($data);

        return StepResult::success($this->getLabel() . ' parsed', $plrResult->summary());
    }
}
