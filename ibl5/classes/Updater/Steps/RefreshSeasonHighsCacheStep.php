<?php

declare(strict_types=1);

namespace Updater\Steps;

use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Delete, then rewarm, the Records "This Season" Season Highs cache keys after a sim.
 *
 * Writes only to DatabaseCache rows, so no transaction is needed; a cache refresh
 * that fails must not roll back the import that preceded it.
 *
 * Invalidates first. A budget skip or any failure leaves the keys deleted, so the
 * Records page cold-computes post-sim data instead of serving a pre-sim entry.
 *
 * IBL-only: Olympics keeps the lazy 900 s behavior.
 */
final class RefreshSeasonHighsCacheStep implements PipelineStepInterface
{
    /** Warm only while at least 60 s of the shared 300 s updater budget is left. */
    public const DEFAULT_BUDGET_SECONDS = 240.0;

    public function __construct(
        private readonly \SeasonHighs\SeasonHighsCacheWarmer $warmer,
        private readonly \Clock\ClockInterface $clock,
        private readonly float $startedAt,
        private readonly float $budgetSeconds = self::DEFAULT_BUDGET_SECONDS,
    ) {
    }

    public static function fromDb(
        \mysqli $db,
        \Season\Season $season,
        ?\League\LeagueContext $leagueContext,
        \Clock\ClockInterface $clock,
        float $startedAt,
    ): self {
        $repository = new \SeasonHighs\CachedSeasonHighsRepository(
            new \SeasonHighs\SeasonHighsRepository($db, $leagueContext),
            new \Cache\DatabaseCache($db),
            $leagueContext !== null ? $leagueContext->getCurrentLeague() : \League\LeagueContext::LEAGUE_IBL
        );

        return new self(new \SeasonHighs\SeasonHighsCacheWarmer($repository, $season), $clock, $startedAt);
    }

    public function getLabel(): string
    {
        return 'Season Highs cache refreshed';
    }

    public function execute(): StepResult
    {
        try {
            $deleted = $this->warmer->invalidate();
        } catch (\Throwable $e) {
            return StepResult::failure($this->getLabel(), 'Season Highs cache invalidation failed: ' . $e->getMessage());
        }

        $elapsed = $this->clock->microtime() - $this->startedAt;
        if ($elapsed > $this->budgetSeconds) {
            return StepResult::skipped(
                $this->getLabel(),
                sprintf('Season Highs cache warm skipped after %.1f s; %d keys invalidated', $elapsed, $deleted),
            );
        }

        try {
            $written = $this->warmer->rebuild();
        } catch (\Throwable $e) {
            $this->cleanupAfterFailure();
            return StepResult::failure($this->getLabel(), 'Season Highs cache warm failed: ' . $e->getMessage());
        }

        return StepResult::success($this->getLabel(), sprintf('Season Highs cache warmed: %d keys', $written));
    }

    /** A partial warm deletes the full expected key set, written keys included, so the page cold-computes. */
    private function cleanupAfterFailure(): void
    {
        try {
            $this->warmer->invalidate();
        } catch (\Throwable) {
            // The invalidate-first call already removed every pre-sim key; any key written since holds post-sim data.
        }
    }
}
