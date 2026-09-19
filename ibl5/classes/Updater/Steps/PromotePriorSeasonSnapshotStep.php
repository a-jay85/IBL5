<?php

declare(strict_types=1);

namespace Updater\Steps;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use PlrParser\Contracts\PlrParserRepositoryInterface;
use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Promotes the prior season's 'mid-season' snapshots to 'end-of-season' once that
 * season's champion appears in ibl_jsb_history. IBL-only — Olympics does not run
 * end-of-season snapshots. Idempotent: a second call inserts 0 rows.
 */
final class PromotePriorSeasonSnapshotStep implements PipelineStepInterface
{
    public function __construct(
        private readonly PlrParserRepositoryInterface $snapshotRepo,
        private readonly JsbImportRepositoryInterface $jsbRepo,
        private readonly int $seasonEndingYear,
    ) {
    }

    public function getLabel(): string
    {
        return 'Prior-season snapshot promotion';
    }

    public function execute(): StepResult
    {
        $priorYear = $this->seasonEndingYear - 1;

        if (!$this->jsbRepo->hasChampionForSeason($priorYear)) {
            return StepResult::skipped($this->getLabel(), 'No champion recorded for ' . $priorYear);
        }

        $promoted = $this->snapshotRepo->promotePriorSeasonSnapshots($priorYear);

        if ($promoted === 0) {
            return StepResult::success($this->getLabel(), $priorYear . ': already promoted');
        }

        return StepResult::success(
            $this->getLabel(),
            sprintf('%d: promoted %d snapshot rows to end-of-season', $priorYear, $promoted),
        );
    }
}
