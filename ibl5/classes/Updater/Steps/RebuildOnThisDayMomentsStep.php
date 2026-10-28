<?php

declare(strict_types=1);

namespace Updater\Steps;

use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Rebuild ibl_on_this_day_moments from box-score history.
 *
 * A full replace inside one transaction: a failed rebuild keeps the previous
 * rows, so the homepage card and the Discord post still read last night's set.
 *
 * Skips when the updater run has already used more than the budget of its
 * 300 s time limit, so RefreshRecordHoldersStep (which runs after it) keeps
 * its own window.
 *
 * IBL-only — Olympics league does not use this step.
 */
final class RebuildOnThisDayMomentsStep implements PipelineStepInterface
{
    public const DEFAULT_BUDGET_SECONDS = 120.0;

    /**
     * @param \Closure(): \OnThisDay\Contracts\OnThisDayMomentBuilderInterface $builderFactory
     */
    public function __construct(
        private readonly \Closure $builderFactory,
        private readonly \Clock\ClockInterface $clock,
        private readonly float $startedAt,
        private readonly float $budgetSeconds = self::DEFAULT_BUDGET_SECONDS,
    ) {
    }

    public static function fromDb(\mysqli $db, \Clock\ClockInterface $clock, float $startedAt): self
    {
        return new self(
            static fn (): \OnThisDay\Contracts\OnThisDayMomentBuilderInterface
                => \OnThisDay\OnThisDayFactory::createRebuilder($db),
            $clock,
            $startedAt,
        );
    }

    public function getLabel(): string
    {
        return 'on this day moments rebuilt';
    }

    public function execute(): StepResult
    {
        $elapsed = $this->clock->microtime() - $this->startedAt;
        if ($elapsed > $this->budgetSeconds) {
            return StepResult::skipped(
                $this->getLabel(),
                sprintf(
                    'updater already ran %.1f s, over the %.0f s budget; stored moments from the last rebuild stay in place',
                    $elapsed,
                    $this->budgetSeconds,
                ),
            );
        }

        try {
            $before = $this->clock->microtime();
            $rows = ($this->builderFactory)()->rebuild();
        } catch (\Throwable $e) {
            return StepResult::failure($this->getLabel(), $e->getMessage());
        }

        return StepResult::success(
            $this->getLabel(),
            sprintf('%d moments in %.1f s', $rows, $this->clock->microtime() - $before),
        );
    }
}
