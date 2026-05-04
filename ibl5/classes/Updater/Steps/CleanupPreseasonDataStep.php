<?php

declare(strict_types=1);

namespace Updater\Steps;

use Boxscore\BoxscoreRepository;
use League\LeagueContext;
use Season\Season;
use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Clean up preseason data on the first HEAT sim.
 *
 * Preseason games are stored with Sep-Oct dates. When the phase transitions
 * to HEAT, stale preseason data must be cleared so the HEAT pipeline can
 * re-import fresh data from JSB files.
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
        if ($this->season->phase !== 'HEAT') {
            return StepResult::skipped($this->getLabel(), 'Not HEAT phase');
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
        $cleaned[] = 'box scores (Sep-Oct)';

        $this->cleanupRepo->deletePreseasonSimDates($this->season->beginningYear);
        $cleaned[] = 'sim dates';

        $this->cleanupRepo->deletePreseasonJsbData($this->season->endingYear);
        $cleaned[] = 'team awards, JSB history, transactions, season records, PLR snapshots';

        $this->season->reloadSimDates();

        return StepResult::success(
            $this->getLabel(),
            'Cleaned: ' . implode(', ', $cleaned),
        );
    }
}
