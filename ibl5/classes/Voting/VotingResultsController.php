<?php

declare(strict_types=1);

namespace Voting;

/**
 * Coordinates fetching and rendering of voting results based on the season phase.
 */
final class VotingResultsController
{
    public function __construct(
        private readonly VotingResultsProvider $provider,
        private readonly VotingResultsTableRenderer $renderer,
        private readonly \Season $season
    ) {
    }

    /**
     * Renders the appropriate voting results for the active season phase.
     */
    public function render(): string
    {
        if ($this->isRegularSeason()) {
            return $this->renderAllStarView();
        }

        return $this->renderEndOfYearView();
    }

    /**
     * Renders All-Star voting results regardless of season phase.
     */
    public function renderAllStarView(): string
    {
        return $this->renderer->renderTables($this->provider->getAllStarResults());
    }

    /**
     * Renders end-of-year awards voting results regardless of season phase.
     */
    public function renderEndOfYearView(): string
    {
        return $this->renderer->renderTables($this->provider->getEndOfYearResults());
    }

    private function isRegularSeason(): bool
    {
        return $this->season->phase === 'Regular Season';
    }
}
