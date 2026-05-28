<?php

declare(strict_types=1);

namespace Cli;

final class LighthouseThresholds
{
    /** @var array<string, array{level: string, minScore: float}> */
    public const THRESHOLDS = [
        'performance' => ['level' => 'warn', 'minScore' => 0.6],
        'accessibility' => ['level' => 'error', 'minScore' => 0.85],
        'best-practices' => ['level' => 'warn', 'minScore' => 0.8],
    ];

    public const REGRESSION_THRESHOLD = 0.03;

    /** @var list<string> */
    public const CATEGORIES = ['performance', 'accessibility', 'best-practices'];
}
