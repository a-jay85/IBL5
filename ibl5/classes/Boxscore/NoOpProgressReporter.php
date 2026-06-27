<?php

declare(strict_types=1);

namespace Boxscore;

use Boxscore\Contracts\ProgressReporterInterface;

/**
 * No-op progress reporter: never flushes. Used in tests and queued/CLI jobs
 * where flushing the HTTP output buffer is meaningless or harmful.
 */
final class NoOpProgressReporter implements ProgressReporterInterface
{
    public function report(int $processedCount): void
    {
        // intentionally empty — no HTTP-flush side effect
    }
}
