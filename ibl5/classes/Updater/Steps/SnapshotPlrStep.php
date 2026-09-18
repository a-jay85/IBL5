<?php

declare(strict_types=1);

namespace Updater\Steps;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use PlrParser\Contracts\PlrParserServiceInterface;
use PlrParser\PlrImportMode;
use Updater\Contracts\JsbSourceResolverInterface;
use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Snapshot current-season player stats into `ibl_plr_snapshots`.
 *
 * Auto-detects the snapshot phase: 'end-of-season' when a champion has been
 * determined, 'mid-season' otherwise. This replaces the PLR snapshot logic
 * that was previously inside EndOfSeasonImportStep, adding mid-season support.
 *
 * During the Playoffs phase it also writes a 'playoffs' snapshot. Offseason runs
 * keep overwriting the season's 'mid-season' row, so 'playoffs' is the last
 * snapshot of the season that nothing overwrites later.
 *
 * IBL-only — Olympics league does not use this step.
 */
final class SnapshotPlrStep implements PipelineStepInterface
{
    public function __construct(
        private readonly PlrParserServiceInterface $plrService,
        private readonly JsbImportRepositoryInterface $jsbRepo,
        private readonly int $seasonEndingYear,
        private readonly JsbSourceResolverInterface $sourceResolver,
        private readonly string $seasonPhase,
    ) {
    }

    public function getLabel(): string
    {
        return 'Player snapshot';
    }

    public function execute(): StepResult
    {
        $data = $this->sourceResolver->getContents('plr');
        if ($data === null) {
            return StepResult::skipped($this->getLabel(), 'PLR file not found');
        }

        $phases = [
            $this->jsbRepo->hasChampionForSeason($this->seasonEndingYear)
                ? 'end-of-season'
                : 'mid-season',
        ];
        if ($this->seasonPhase === 'Playoffs') {
            $phases[] = 'playoffs';
        }

        $details = [];
        foreach ($phases as $phase) {
            $result = $this->plrService->processPlrDataForYear(
                $data,
                $this->seasonEndingYear,
                PlrImportMode::Snapshot,
                $phase,
                'current-season',
            );
            $details[] = $phase . ': ' . $result->summary();
        }

        return StepResult::success(
            $this->getLabel(),
            implode('; ', $details),
        );
    }
}
