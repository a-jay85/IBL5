<?php

declare(strict_types=1);

namespace Updater\Steps;

use Boxscore\BoxscoreRepository;
use League\LeagueContext;
use Season\Season;
use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Clean up preseason data on the first Regular Season sim.
 *
 * Preseason uses real season dates (same Nov-Dec range as Regular Season).
 * When the phase transitions to Regular Season, stale preseason data must
 * be cleared so the RS pipeline can re-import fresh data from JSB files.
 *
 * IBL-only — Olympics does not have a preseason phase.
 */
final class CleanupPreseasonDataStep implements PipelineStepInterface
{
    private PreseasonCleanupRepository $cleanupRepo;

    public function __construct(
        private readonly BoxscoreRepository $boxscoreRepo,
        private readonly Season $season,
        \mysqli $db,
        ?LeagueContext $leagueContext = null,
    ) {
        $this->cleanupRepo = new PreseasonCleanupRepository($db, $leagueContext);
    }

    public function getLabel(): string
    {
        return 'Preseason data cleaned';
    }

    public function execute(): StepResult
    {
        if ($this->season->phase !== 'Regular Season') {
            return StepResult::skipped($this->getLabel(), 'Not Regular Season phase');
        }

        if ($this->cleanupRepo->hasRegularSeasonSimDates($this->season)) {
            return StepResult::skipped($this->getLabel(), 'Not first Regular Season sim');
        }

        if (!$this->cleanupRepo->hasPreseasonBoxScores($this->season->beginningYear)) {
            return StepResult::skipped($this->getLabel(), 'No preseason data to clean');
        }

        return $this->cleanPreseasonData();
    }

    private function cleanPreseasonData(): StepResult
    {
        $cleaned = [];

        $this->boxscoreRepo->deletePreseasonBoxScores($this->season->beginningYear);
        $cleaned[] = 'box scores (Nov-Dec)';

        $this->cleanupRepo->deletePreseasonJsbData($this->season->endingYear);
        $cleaned[] = 'team awards, JSB history, transactions, season records, PLR snapshots';

        return StepResult::success(
            $this->getLabel(),
            'Cleaned: ' . implode(', ', $cleaned),
        );
    }
}
