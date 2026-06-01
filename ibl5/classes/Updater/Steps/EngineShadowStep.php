<?php

declare(strict_types=1);

namespace Updater\Steps;

use EngineBundle\EmptyRosterException;
use EngineBundle\EmptyScheduleException;
use EngineBundle\EngineBundleService;
use EngineRunner\Contracts\EngineRunnerInterface;
use EngineShadow\EngineShadowLoader;
use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * SHADOW step: run the native Go engine over the season window and persist its
 * output to the shadow box-score tables for engine-vs-JSB comparison.
 *
 * Additive and isolated: it runs AFTER the canonical ProcessBoxscoresStep, never
 * touches canonical tables, and is gated behind a flag (default OFF in prod). When
 * the flag is off it skips immediately; when the engine or loader fails it returns
 * a failure WITHOUT aborting the pipeline or affecting canonical data. The bundle
 * is built by reusing EngineBundleService (PR2) — this step assembles nothing.
 */
final class EngineShadowStep implements PipelineStepInterface
{
    /**
     * Per-run cap on shadowed games. The engine slurps its whole Result JSON
     * into a 128MB PHP process; an unbounded season window (hundreds of games,
     * ~360MB result) OOMs with an uncatchable E_ERROR. 20 games (~9MB result)
     * was proven safe under 128MB with margin. Public so tests can reference it.
     */
    public const SHADOW_MAX_GAMES_PER_RUN = 20;

    public function __construct(
        private readonly EngineBundleService $bundleService,
        private readonly EngineRunnerInterface $runner,
        private readonly EngineShadowLoader $loader,
        private readonly int $seasonYear,
        private readonly bool $enabled,
    ) {
    }

    public function getLabel(): string
    {
        return 'Engine shadow sim';
    }

    public function execute(): StepResult
    {
        if (!$this->enabled) {
            return StepResult::skipped($this->getLabel(), 'ENGINE_SHADOW_ENABLED is off (skipped)');
        }

        try {
            $bundleJson = $this->bundleService->buildBundleJson(
                $this->seasonYear,
                maxGames: self::SHADOW_MAX_GAMES_PER_RUN,
            );
        } catch (EmptyScheduleException | EmptyRosterException $e) {
            return StepResult::skipped($this->getLabel(), 'Nothing to shadow-sim: ' . $e->getMessage());
        }

        try {
            $resultJson = $this->runner->run($bundleJson);
            $result = $this->loader->load($resultJson);
        } catch (\Throwable $e) {
            // Isolation: the shadow run is best-effort. Any engine/loader failure
            // (nonzero exit, malformed JSON, a DB write error) degrades to a step
            // failure WITHOUT aborting the canonical pipeline or touching canonical
            // tables — per-game writes are already rolled back by transactional().
            return StepResult::failure($this->getLabel(), 'Engine shadow run failed: ' . $e->getMessage());
        }

        return StepResult::success(
            $this->getLabel(),
            detail: sprintf(
                '%d games → %d player rows, %d team rows (shadow)',
                $result->gamesLoaded,
                $result->playerRowsInserted,
                $result->teamRowsInserted,
            ),
        );
    }
}
