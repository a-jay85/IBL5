<?php

declare(strict_types=1);

namespace Updater\Steps;

use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Enqueue the current sim for recap generation, and notify the admin running
 * the updater with a link into the recap viewer.
 *
 * Registered as the last step of the non-Olympics pipeline (unit 3b). Two
 * notified states, both carrying a link: a new sim was queued, or there was no
 * new sim and the link points at the last generated recap.
 */
class QueueSimSummaryStep implements PipelineStepInterface
{
    public function __construct(
        private readonly \SimRecap\SimSummaryRepository $summaries,
        private readonly \Season\SeasonQueryRepository $seasonQuery,
    ) {
    }

    public function getLabel(): string
    {
        return 'Sim recap queued';
    }

    public function execute(): StepResult
    {
        $sim = $this->seasonQuery->getLastSimDatesArray()['sim'];

        if ($sim <= 0) {
            return StepResult::skipped($this->getLabel(), 'No sim dates recorded — nothing to queue.');
        }

        if ($this->summaries->queuePendingIfAbsent($sim)) {
            return StepResult::success(
                $this->getLabel(),
                "Queued sim {$sim} for recap generation.",
                inlineHtml: $this->queuedHtml($sim),
            );
        }

        return StepResult::success(
            $this->getLabel(),
            "Sim {$sim} already has a summary row.",
            inlineHtml: $this->noNewSimHtml(),
        );
    }

    /**
     * State (a): a new sim was queued. The copy sets the expectation honestly —
     * queuing is not generation; the write-up is produced later by the agent.
     */
    private function queuedHtml(int $sim): string
    {
        $safeSim = self::escape((string) $sim);

        return 'Sim ' . $safeSim . ' has been queued for recap generation — the write-up is produced'
            . ' shortly by the recap agent. '
            . $this->viewerLink($sim, 'View sim ' . $safeSim . ' in the recap viewer');
    }

    /**
     * State (b): no new sim to recap. Links to the last generated (done) recap.
     * With no done recap anywhere, renders text only — no anchor to a sim that
     * does not exist.
     */
    private function noNewSimHtml(): string
    {
        $lastDone = $this->lastGeneratedSim();

        if ($lastDone === null) {
            return 'No new sim to recap this run, and no recap has been generated yet.';
        }

        $safeSim = self::escape((string) $lastDone);

        return 'No new sim to recap this run. '
            . $this->viewerLink($lastDone, 'View the last generated recap (sim ' . $safeSim . ')');
    }

    /**
     * The highest sim whose status is done, or null if none is done yet.
     *
     * listAll() is already ORDER BY sim DESC, so the first done row is the most
     * recent generated recap. Reuses unit 2's read rather than widening the
     * frozen repository interface with a latestDoneSim() method.
     */
    private function lastGeneratedSim(): ?int
    {
        foreach ($this->summaries->listAll() as $row) {
            if (($row['status'] ?? null) !== 'done') {
                continue;
            }
            $sim = $row['sim'] ?? null;
            if (is_int($sim)) {
                return $sim;
            }
        }

        return null;
    }

    /**
     * A viewer anchor. The href is escaped even though its only dynamic part is
     * an int today — the markup does not enforce that, so the escape is the
     * guard if the value ever stops being an int. $label is pre-escaped by the
     * caller.
     */
    private function viewerLink(int $sim, string $label): string
    {
        $href = self::escape(\SimRecap\SimSummaryLink::path($sim));

        return '<a href="' . $href . '">' . $label . '</a>.';
    }

    private static function escape(string $value): string
    {
        return \Security\HtmlSanitizer::e($value);
    }
}
