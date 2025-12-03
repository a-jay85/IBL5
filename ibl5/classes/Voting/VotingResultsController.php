<?php

declare(strict_types=1);

namespace Voting;

use Voting\Contracts\VotingResultsControllerInterface;

/**
 * @see VotingResultsControllerInterface
 */
class VotingResultsController implements VotingResultsControllerInterface
{
    public function __construct(
        private readonly VotingResultsService $service,
        private readonly VotingResultsTableRenderer $renderer,
        private readonly \Season $season
    ) {
    }

    /**
     * @see VotingResultsControllerInterface::render()
     */
    public function render(): string
    {
        if ($this->isRegularSeason()) {
            return $this->renderAllStarView();
        }

        return $this->renderEndOfYearView();
    }

    /**
     * @see VotingResultsControllerInterface::renderAllStarView()
     */
    public function renderAllStarView(): string
    {
        return $this->renderer->renderTables($this->service->getAllStarResults());
    }

    /**
     * @see VotingResultsControllerInterface::renderEndOfYearView()
     */
    public function renderEndOfYearView(): string
    {
        return $this->renderer->renderTables($this->service->getEndOfYearResults());
    }

    private function isRegularSeason(): bool
    {
        return $this->season->phase === 'Regular Season';
    }
}
