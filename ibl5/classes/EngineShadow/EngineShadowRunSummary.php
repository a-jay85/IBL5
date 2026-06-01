<?php

declare(strict_types=1);

namespace EngineShadow;

/**
 * Immutable summary of an EngineShadowRunService::runForSeason() run, for CLI
 * reporting.
 *
 * The streaming path (runStreaming → loadOneGame(): void) yields a game count and
 * the run seed, but NOT player/team row counts (those are not aggregated per the
 * PR2 streaming API), so this DTO carries only what the streaming path can report
 * — unlike the now-removed EngineShadowLoadResult, which counted rows.
 */
final class EngineShadowRunSummary
{
    public function __construct(
        public readonly int $gamesLoaded,
        public readonly int $seed,
    ) {
    }
}
