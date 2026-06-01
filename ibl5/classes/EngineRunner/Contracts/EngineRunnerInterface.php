<?php

declare(strict_types=1);

namespace EngineRunner\Contracts;

use EngineRunner\EngineRunnerException;

/**
 * Runs the compiled native sim binary as a pure stdin/stdout transform.
 */
interface EngineRunnerInterface
{
    /**
     * Execute the engine and stream its NDJSON output one game at a time, at
     * constant memory: the engine's stdout is spooled to a temp file (PHP never
     * holds the multi-hundred-MB payload), then read line by line. The first line
     * is the header {"seed":N}; each subsequent non-empty line is one compact
     * GameResult, decoded and handed to $onGame.
     *
     * @param string                                       $bundleJson the engine input bundle (from EngineBundleService)
     * @param callable(array<string, mixed>, int): void    $onGame     invoked once per game with (decoded game array, seed)
     * @param int|null                                     $seed       optional seed override (>= 0); null uses the bundle's own seed
     *
     * @return int the number of games processed (callback invocations)
     *
     * @throws EngineRunnerException on missing/invalid binary, nonzero exit,
     *                               a missing/malformed header line, or a malformed game line
     */
    public function runStreaming(string $bundleJson, callable $onGame, ?int $seed = null): int;
}
