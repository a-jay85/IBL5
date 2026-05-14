<?php

declare(strict_types=1);

namespace Updater\Steps;

use Boxscore\BoxscoreProcessor;
use Boxscore\BoxscoreView;
use Updater\Contracts\JsbSourceResolverInterface;
use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Step 8: Process boxscores from .sco file.
 *
 * Reads .sco data via the archive-first resolver. Skips if no .sco source is available.
 */
class ProcessBoxscoresStep implements PipelineStepInterface
{
    public function __construct(
        private readonly BoxscoreProcessor $processor,
        private readonly BoxscoreView $view,
        private readonly JsbSourceResolverInterface $sourceResolver,
    ) {
    }

    public function getLabel(): string
    {
        return 'Boxscores processed';
    }

    public function execute(): StepResult
    {
        $data = $this->sourceResolver->getContents('sco');
        if ($data === null) {
            return StepResult::skipped('Boxscores', 'No IBL5.sco file found (skipped)');
        }

        $scoResult = $this->processor->processScoData($data, 0, '');
        $inlineHtml = $this->view->renderParseLog($scoResult);

        return StepResult::success($this->getLabel(), collapsibleLog: true, inlineHtml: $inlineHtml);
    }
}
