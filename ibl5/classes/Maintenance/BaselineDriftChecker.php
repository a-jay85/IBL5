<?php

declare(strict_types=1);

namespace Maintenance;

final class BaselineDriftChecker
{
    /**
     * @param array<string, array<string, int>> $current  per-neon-file => identifier => count
     * @param array<string, array<string, int>> $snapshot same shape, decoded counts JSON
     * @return array{increases: list<string>, warnings: list<string>}
     */
    public function compare(array $current, array $snapshot): array
    {
        $increases = [];
        $warnings = [];

        foreach ($current as $file => $counts) {
            $snapshotCounts = $snapshot[$file] ?? [];

            $allIdentifiers = array_unique(array_merge(array_keys($counts), array_keys($snapshotCounts)));
            sort($allIdentifiers);

            foreach ($allIdentifiers as $identifier) {
                $currentCount = $counts[$identifier] ?? 0;
                $previous = $snapshotCounts[$identifier] ?? 0;

                if ($currentCount > $previous) {
                    if ($previous === 0) {
                        $increases[] = "INCREASE: [{$file}] {$identifier}: new ({$currentCount} entries)";
                    } else {
                        $increases[] = "INCREASE: [{$file}] {$identifier}: {$previous} → {$currentCount}";
                    }
                } elseif ($currentCount < $previous) {
                    $decrease = $previous - $currentCount;
                    if ($decrease > 5) {
                        $warnings[] = "WARNING: [{$file}] {$identifier}: {$previous} → {$currentCount} (decreased by {$decrease} — snapshot may be stale, consider --update)";
                    }
                }
            }
        }

        return ['increases' => $increases, 'warnings' => $warnings];
    }
}
