<?php

declare(strict_types=1);

namespace Updater\Steps;

use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

final class GenerateSeasonAwardsStep implements PipelineStepInterface
{
    public function __construct(
        private readonly string $seasonPhase,
        private readonly int $seasonEndingYear,
        private readonly int $eoyVotesCast,
        private readonly int $totalRealTeams,
        private readonly bool $awardsAlreadyGenerated,
        private readonly bool $leadersHtmExists,
    ) {
    }

    public function getLabel(): string
    {
        return 'Season awards';
    }

    public function execute(): StepResult
    {
        if ($this->awardsAlreadyGenerated) {
            return StepResult::success(
                $this->getLabel(),
                'Season awards already generated for ' . $this->seasonEndingYear,
            );
        }

        if ($this->seasonPhase !== 'Playoffs') {
            return StepResult::skipped($this->getLabel(), 'Only available during Playoffs phase');
        }

        $threshold = (int) ceil($this->totalRealTeams * 0.75);
        if ($this->eoyVotesCast < $threshold) {
            return StepResult::skipped(
                $this->getLabel(),
                'Voting not yet complete (' . $this->eoyVotesCast . '/' . $this->totalRealTeams . ' votes submitted)',
            );
        }

        if (!$this->leadersHtmExists) {
            return StepResult::skipped(
                $this->getLabel(),
                'Leaders.htm not found — upload sim backup before generating awards',
            );
        }

        return StepResult::success($this->getLabel(), inlineHtml: $this->renderForm());
    }

    private function renderForm(): string
    {
        ob_start();
        ?>
<div class="ibl-card sco-parse-result">
    <div class="ibl-card__header">
        <h2 class="ibl-card__title">Season Awards</h2>
        <div class="ibl-card__subtitle"><?= $this->eoyVotesCast ?>/<?= $this->totalRealTeams ?> EOY votes submitted</div>
    </div>
    <div class="ibl-card__body">
        <form method="POST" action="/ibl5/leagueControlPanel.php">
            <button type="submit" name="action" value="generate_awards" class="ibl-btn ibl-btn--primary">Generate Season Awards</button>
        </form>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }
}
