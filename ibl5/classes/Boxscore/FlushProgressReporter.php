<?php

declare(strict_types=1);

namespace Boxscore;

use Boxscore\Contracts\ProgressReporterInterface;

/**
 * Default progress reporter: flushes the PHP output buffer every 50 games,
 * preserving the cadence BoxscoreProcessor previously called inline.
 */
final class FlushProgressReporter implements ProgressReporterInterface
{
    private const FLUSH_EVERY = 50;

    public function report(int $processedCount): void
    {
        if ($processedCount % self::FLUSH_EVERY === 0) {
            flush();
        }
    }
}
