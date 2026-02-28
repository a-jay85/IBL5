<?php

declare(strict_types=1);

namespace Updater\Steps;

use Boxscore\BoxscoreProcessor;
use Boxscore\BoxscoreView;
use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Step 8: Process boxscores from .sco file.
 *
 * Skips if the .sco file is missing.
 */
class ProcessBoxscoresStep implements PipelineStepInterface
{
    public function __construct(
        private readonly BoxscoreProcessor $processor,
        private readonly BoxscoreView $view,
        private readonly string $scoPath,
    ) {
    }

    public function getLabel(): string
    {
        return 'Boxscores processed';
    }

    public function execute(): StepResult
    {
        if (!is_file($this->scoPath)) {
            return StepResult::skipped('Boxscores', 'No IBL5.sco file found (skipped)');
        }

        $scoResult = $this->processor->processScoFile($this->scoPath, 0, '');
        $inlineHtml = $this->view->renderParseLog($scoResult);

        return StepResult::success($this->getLabel(), inlineHtml: $inlineHtml);
    }
}
