<?php

declare(strict_types=1);

namespace Updater\SeasonRollover;

/**
 * Runs one season-rollover pass: detect from the uploaded archive, then write
 * the new season and advance cash-consideration contract years.
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

        $this->applier->apply($decision);
        $advanced = $this->cashAdvancer->advance((int) $decision->targetYear);

        return new SeasonRolloverRunResult($decision, $advanced);
    }
}
