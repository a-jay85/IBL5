<?php

declare(strict_types=1);

namespace Maintenance;

final class CoverageComparator
{
    public function compare(float $current, ?float $previous, float $tolerance): CoverageComparisonResult
    {
        if ($previous === null) {
            return CoverageComparisonResult::pass(
                $current,
                null,
                0.0,
                sprintf('Coverage %.2f%% recorded (first run, no previous baseline)', $current),
            );
        }

        $minimumAllowed = $previous - $tolerance;

        if ($current >= $minimumAllowed) {
            return CoverageComparisonResult::pass(
                $current,
                $previous,
                $minimumAllowed,
                sprintf(
                    'Coverage %.2f%% is within tolerance of previous %.2f%% (minimum %.2f%%)',
                    $current,
                    $previous,
                    $minimumAllowed,
                ),
            );
        }

        return CoverageComparisonResult::fail(
            $current,
            $previous,
            $minimumAllowed,
            sprintf(
                'Coverage regressed: %.2f%% < minimum allowed %.2f%% (previous %.2f%%, tolerance %.2f%%)',
                $current,
                $minimumAllowed,
                $previous,
                $tolerance,
            ),
        );
    }
}
