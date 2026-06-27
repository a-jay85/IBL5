<?php

declare(strict_types=1);

namespace Boxscore\Contracts;

interface ProgressReporterInterface
{
    /**
     * Report that $processedCount games have been processed so far.
     * Implementations decide whether/when to take a side effect (e.g. flush output).
     */
    public function report(int $processedCount): void;
}
