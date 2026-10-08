<?php

declare(strict_types=1);

namespace Updater\SeasonRollover;

/**
 * Runs one season-rollover pass: detect from the uploaded archive, then
 * advance cash-consideration contract years, then write the new season.
 *
 * State-free by design: holds only the three collaborators, reads no request
 * or session data, renders nothing. The admin, POST and CSRF guards and all
 * output stay in scripts/updateAllTheThings.php.
 */
final class SeasonRolloverRunner
{
    public function __construct(
        private readonly SeasonRolloverDetector $detector,
        private readonly SeasonRolloverApplier $applier,
        private readonly CashConsiderationsYearAdvancer $cashAdvancer,
    ) {
    }

    public function run(int $currentBeginningYear, int $currentEndingYear): SeasonRolloverRunResult
    {
        $decision = $this->detector->detect($currentBeginningYear, $currentEndingYear);
        if (!$decision->shouldWrite()) {
            return new SeasonRolloverRunResult($decision, null);
        }

        // Advance cash first. A missing or bad marker throws here, before the
        // season year is written, so the next run still detects the rollover.
        // A retry after a later failure is safe: advance() returns 0 once the
        // marker equals the target year.
        $advanced = $this->cashAdvancer->advance((int) $decision->targetYear);
        $this->applier->apply($decision);

        return new SeasonRolloverRunResult($decision, $advanced);
    }
}
