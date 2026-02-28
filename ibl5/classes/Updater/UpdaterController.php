<?php

declare(strict_types=1);

namespace Updater;

use Updater\Contracts\PipelineStepInterface;
use Updater\Contracts\UpdaterControllerInterface;
use Updater\Contracts\UpdaterViewInterface;

/**
 * Orchestrates the full update pipeline.
 *
 * Wires all dependencies, registers pipeline steps, and manages
 * progressive output via callbacks that echo+flush each step's result.
 */
class UpdaterController implements UpdaterControllerInterface
{
    public function __construct(
        private readonly UpdaterService $service,
        private readonly UpdaterViewInterface $view,
    ) {
    }

    /**
     * @see UpdaterControllerInterface::run()
     */
    public function run(): void
    {
        echo $this->view->renderSectionOpen('Pipeline');
        flush();

        $this->service->run(
            function (PipelineStepInterface $step): void {
                echo $this->view->renderStepStart($this->getStepProgressLabel($step));
                flush();
            },
            function (StepResult $result): void {
                $this->renderStepResult($result);
                flush();
            },
        );

        echo $this->view->renderSectionClose();
        flush();

        echo $this->view->renderSummary(
            $this->service->getSuccessCount(),
            $this->service->getErrorCount(),
        );
        flush();
    }

    /**
     * Map step labels to in-progress descriptions.
     */
    private function getStepProgressLabel(PipelineStepInterface $step): string
    {
        return match ($step->getLabel()) {
            'League config' => 'Importing league config (.lge)...',
            'Player file' => 'Parsing player file (.plr)...',
            'Schedule updated' => 'Updating schedule...',
            'Standings updated' => 'Updating standings...',
            'Power rankings updated' => 'Updating power rankings...',
            'Extension attempts reset' => 'Resetting extension attempts...',
            'Saved depth charts updated' => 'Updating saved depth charts...',
            'Boxscores processed' => 'Processing boxscores (.sco)...',
            'All-Star games processed' => 'Processing All-Star games...',
            'JSB files parsed' => 'Parsing JSB engine files...',
            default => $step->getLabel() . '...',
        };
    }

    /**
     * Render a completed step result with all its optional components.
     */
    private function renderStepResult(StepResult $result): void
    {
        if ($result->success) {
            echo $this->view->renderStepComplete($result->label, $result->detail);

            if ($result->inlineHtml !== '') {
                echo $this->view->renderInlineHtml($result->inlineHtml);
            }

            if ($result->capturedLog !== '') {
                echo $this->view->renderLog($result->capturedLog);
            }

            if ($result->messages !== [] || $result->messageErrorCount > 0) {
                echo $this->view->renderMessageLog($result->messages, $result->messageErrorCount);
            }
        } else {
            echo $this->view->renderStepError($result->label, $result->errorMessage);
        }
    }
}
