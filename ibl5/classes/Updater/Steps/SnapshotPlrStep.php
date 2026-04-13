<?php

declare(strict_types=1);

namespace Updater\Steps;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use PlrParser\Contracts\PlrParserServiceInterface;
use PlrParser\PlrImportMode;
use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Snapshot current-season player stats into ibl_plr_snapshots.
 *
 * Auto-detects the snapshot phase: 'end-of-season' when a champion has been
 * determined, 'mid-season' otherwise. This replaces the PLR snapshot logic
 * that was previously inside EndOfSeasonImportStep, adding mid-season support.
 *
 * IBL-only — Olympics league does not use this step.
 */
final class SnapshotPlrStep implements PipelineStepInterface
{
    public function __construct(
        private readonly PlrParserServiceInterface $plrService,
        private readonly JsbImportRepositoryInterface $jsbRepo,
        private readonly int $seasonEndingYear,
        private readonly string $plrFilePath,
    ) {
    }

    public function getLabel(): string
    {
        return 'Player snapshot';
    }

    public function execute(): StepResult
    {
        if (!file_exists($this->plrFilePath)) {
            return StepResult::skipped($this->getLabel(), 'PLR file not found');
        }

        $phase = $this->jsbRepo->hasChampionForSeason($this->seasonEndingYear)
            ? 'end-of-season'
            : 'mid-season';

        $result = $this->plrService->processPlrFileForYear(
            $this->plrFilePath,
            $this->seasonEndingYear,
            PlrImportMode::Snapshot,
            $phase,
            'current-season',
        );

        return StepResult::success(
            $this->getLabel(),
            $phase . ': ' . $result->summary(),
        );
    }
}
