<?php

declare(strict_types=1);

namespace EngineShadow;

/**
 * Immutable summary of an EngineShadowLoader::load() run, for step reporting and
 * test assertions.
 */
final class EngineShadowLoadResult
{
    public function __construct(
        public readonly int $gamesLoaded,
        public readonly int $playerRowsInserted,
        public readonly int $teamRowsInserted,
    ) {
    }
}
