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
     * Execute the engine: write the bundle JSON to stdin, return the Result JSON
     * from stdout.
     *
     * @param string   $bundleJson the engine input bundle (from EngineBundleService)
     * @param int|null $seed       optional seed override (>= 0); null uses the bundle's own seed
     *
     * @return string the engine's Result JSON (validated as decodable)
     *
     * @throws EngineRunnerException on missing/invalid binary, nonzero exit,
     *                               empty stdout, or malformed JSON output
     */
    public function run(string $bundleJson, ?int $seed = null): string;
}
