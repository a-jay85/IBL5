<?php

declare(strict_types=1);

namespace Updater\Steps;

use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Post the top "on this day" moment for the current sim to Discord, once per sim.
 *
 * Never fails the pipeline: a history lookup or cache problem skips the step,
 * and a Discord problem is reported as a successful step with a note. The sim
 * is claimed right before posting, after the line is built, so only a failed
 * Discord call loses the post; it is not retried on re-run.
 */
class PostOnThisDayStep implements PipelineStepInterface
{
    public function __construct(
        private readonly \OnThisDay\Contracts\OnThisDayServiceInterface $service,
        private readonly \OnThisDay\Contracts\SimPostClaimInterface $claims,
        private readonly \Season\Contracts\SeasonQueryRepositoryInterface $seasonQuery,
        private readonly \OnThisDay\Contracts\MomentPosterInterface $poster,
        private readonly \OnThisDay\MomentFormatter $formatter = new \OnThisDay\MomentFormatter(),
        private readonly \OnThisDay\OnThisDayCalendar $calendar = new \OnThisDay\OnThisDayCalendar(),
    ) {
    }

    public function getLabel(): string
    {
        return 'On this day posted';
    }

    public function execute(): StepResult
    {
        $label = $this->getLabel();

        try {
            $phase = $this->seasonQuery->getSeasonPhase();
            if (!\OnThisDay\OnThisDayPhasePolicy::isEnabled($phase)) {
                $reason = $phase !== ''
                    ? "On this day posts are disabled during the {$phase} phase."
                    : 'On this day posts are disabled — no season phase is set.';
                return StepResult::skipped($label, $reason);
            }

            $sim = $this->seasonQuery->getLastSimDatesArray()['sim'];
            if ($sim <= 0) {
                return StepResult::skipped($label, 'No sim dates recorded — nothing to post.');
            }

            $selection = $this->service->getMomentsForSim();
            if ($selection->isEmpty()) {
                return StepResult::skipped($label, "No notable moment within 7 days of {$selection->centerDate}.");
            }

            $top = $selection->moments[0];
            $line = $this->formatter->discordLine(
                $top,
                $this->calendar->seasonsAgo($selection->currentSeasonYear, $top->seasonYear) ?? 0,
            );

            $sim = $selection->sim;
            if ($this->claims->claimSimPost($sim) === false) {
                return StepResult::skipped($label, "Already posted for sim {$sim}.");
            }
        } catch (\Throwable $e) {
            return StepResult::skipped($label, 'On this day skipped: ' . $e->getMessage());
        }

        try {
            $this->poster->post($line);
        } catch (\Throwable $e) {
            return StepResult::success($label, "Discord post failed for sim {$sim}: {$e->getMessage()} (sim unaffected).");
        }

        return StepResult::success($label, "Posted sim {$sim}: {$line}");
    }
}
