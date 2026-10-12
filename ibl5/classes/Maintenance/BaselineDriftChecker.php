<?php

declare(strict_types=1);

namespace Maintenance;

/**
 * Compares PHPStan baseline entry counts against the committed snapshot
 * (phpstan-baseline-counts.json) and, when the merge-base is known, against the
 * baseline as it stood at the merge-base.
 *
 * Per neon file and identifier, with head = PR neon count, snap = PR snapshot value,
 * baseNeon = neon count at the merge-base, baseSnap = snapshot value at the merge-base
 * (any missing value is 0):
 *
 *     ceiling = min(snap, baseNeon + max(0, snap - baseSnap))
 *     fail iff head > ceiling
 *
 * This only tightens. The old gate failed iff head > snap. The ceiling is min(snap, ...),
 * so ceiling <= snap always, and every input the old gate failed still fails. No
 * previously failing input can pass. The second term is new strictness: growth over the
 * merge-base neon needs an explicit raise of the snapshot in the same PR, equal to the
 * growth. When the base is unavailable (null), only the snapshot rule runs.
 */
final class BaselineDriftChecker
{
    /**
     * @param array<string, array<string, int>> $current      per-neon-file => identifier => count
     * @param array<string, array<string, int>> $snapshot     same shape, decoded counts JSON
     * @param array<string, array<string, int>>|null $baseNeon     null = base unavailable, snapshot rule only
     * @param array<string, array<string, int>>|null $baseSnapshot counts JSON at the merge-base
     * @return array{increases: list<string>, warnings: list<string>}
     */
    public function compare(array $current, array $snapshot, ?array $baseNeon = null, ?array $baseSnapshot = null): array
    {
        $increases = [];
        $warnings = [];
        $useBase = $baseNeon !== null && $baseSnapshot !== null;

        foreach ($current as $file => $counts) {
            $snapshotCounts = $snapshot[$file] ?? [];
            $baseNeonCounts = $baseNeon[$file] ?? [];
            $baseSnapshotCounts = $baseSnapshot[$file] ?? [];

            $allIdentifiers = array_unique(array_merge(
                array_keys($counts),
                array_keys($snapshotCounts),
                array_keys($baseNeonCounts),
            ));
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
                    continue;
                }

                if ($currentCount < $previous) {
                    $decrease = $previous - $currentCount;
                    if ($decrease > 5) {
                        $warnings[] = "WARNING: [{$file}] {$identifier}: {$previous} → {$currentCount} (decreased by {$decrease} — snapshot may be stale, master's update-baselines job will sync it)";
                    }
                }

                if ($useBase) {
                    $baseNeonCount = $baseNeonCounts[$identifier] ?? 0;
                    $raise = max(0, $previous - ($baseSnapshotCounts[$identifier] ?? 0));
                    $ceiling = min($previous, $baseNeonCount + $raise);
                    if ($currentCount > $ceiling) {
                        $increases[] = "INCREASE: [{$file}] {$identifier}: merge-base {$baseNeonCount} → {$currentCount} (snapshot raise in this PR: +{$raise}; run php bin/check-baseline-drift --update)";
                    }
                }
            }
        }

        return ['increases' => $increases, 'warnings' => $warnings];
    }

    /**
     * Raise-only snapshot update. Never lowers an existing value.
     * required(id) = max(snap, head, baseSnap + (head - baseNeon))   when base arrays are non-null
     * required(id) = max(snap, head)                                 when base arrays are null
     * Existing file/identifier key order of $snapshot is preserved; new files and identifiers
     * are appended in $current order. Identifiers only in $snapshot are kept unchanged.
     *
     * @param array<string, array<string, int>> $current
     * @param array<string, array<string, int>> $snapshot
     * @param array<string, array<string, int>>|null $baseNeon
     * @param array<string, array<string, int>>|null $baseSnapshot
     * @return array<string, array<string, int>>
     */
    public function raiseOnly(array $current, array $snapshot, ?array $baseNeon, ?array $baseSnapshot): array
    {
        $useBase = $baseNeon !== null && $baseSnapshot !== null;
        $result = $snapshot;

        foreach ($current as $file => $counts) {
            foreach ($counts as $identifier => $head) {
                $existing = $result[$file][$identifier] ?? null;
                $required = max($existing ?? 0, $head);
                if ($useBase) {
                    $baseNeonCount = $baseNeon[$file][$identifier] ?? 0;
                    $baseSnapshotCount = $baseSnapshot[$file][$identifier] ?? 0;
                    $required = max($required, $baseSnapshotCount + ($head - $baseNeonCount));
                }

                if ($required > ($existing ?? 0)) {
                    $result[$file][$identifier] = $required;
                }
            }
        }

        return $result;
    }

    public function resolveBaseRef(?string $flagRef, ?string $declaredBase): string
    {
        if ($flagRef !== null) {
            return $flagRef;
        }
        if ($declaredBase !== null && $declaredBase !== '') {
            return $declaredBase;
        }

        return 'origin/master';
    }
}
