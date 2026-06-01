<?php

declare(strict_types=1);

namespace EngineShadow;

use EngineBundle\EngineBundleService;
use EngineRunner\Contracts\EngineRunnerInterface;

/**
 * Orchestrates a full-season engine shadow sim: build the input bundle, stream the
 * engine's NDJSON output one game at a time (PR2 made this memory-safe), and load
 * each game into the droppable shadow box-score tables.
 *
 * Isolation by process boundary, NOT try/catch: this service deliberately does NOT
 * swallow engine/loader failures (unlike the removed inline EngineShadowStep). It
 * propagates every exception to its single caller — the detached CLI
 * (runEngineShadow.php) — which is the one catch point (log + nonzero exit). The
 * typed EmptyScheduleException / EmptyRosterException propagate too; the CLI maps
 * them to the exit-0 "nothing to do" case.
 */
final class EngineShadowRunService
{
    public function __construct(
        private readonly EngineBundleService $bundleService,
        private readonly EngineRunnerInterface $runner,
        private readonly EngineShadowLoader $loader,
        private readonly EngineShadowRepository $repository,
    ) {
    }

    /**
     * @throws \EngineBundle\EmptyScheduleException when the season has no unplayed games
     * @throws \EngineBundle\EmptyRosterException   when no rosterable players exist
     */
    public function runForSeason(int $seasonYear): EngineShadowRunSummary
    {
        // Full season — PR2's NDJSON streaming keeps this memory-safe (no cap).
        $bundleJson = $this->bundleService->buildBundleJson($seasonYear);

        // Fetch the pid→teamid map once, then stream each game into the loader.
        $pidMap = $this->repository->getAllTeamIdsByPid();
        $games = 0;
        $seed = $this->runner->runStreaming(
            $bundleJson,
            function (array $game, int $gameSeed) use ($pidMap, &$games): void {
                $this->loader->loadOneGame($game, $gameSeed, $pidMap);
                $games++;
            },
        );

        return new EngineShadowRunSummary($games, $seed);
    }
}
