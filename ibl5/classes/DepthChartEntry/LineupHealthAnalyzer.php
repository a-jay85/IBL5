<?php

declare(strict_types=1);

namespace DepthChartEntry;

use DepthChartEntry\Contracts\LineupHealthAnalyzerInterface;
use League\League;

/**
 * Computes deterministic "Lineup Health Check" warnings about a GM's
 * currently-saved depth chart and roster.
 *
 * Pure function: no DB, no superglobals, no constructor. All data is injected
 * by the controller, so owner-scoping is structural and the suite is DB-free.
 *
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 * @phpstan-import-type LineupWarning from \DepthChartEntry\Contracts\LineupHealthAnalyzerInterface
 *
 * @see LineupHealthAnalyzerInterface
 */
final class LineupHealthAnalyzer implements LineupHealthAnalyzerInterface
{
    /**
     * Minimum players assigned (depth 1-5) per position before "thin depth"
     * fires. Mirrors DepthChartEntryValidator's Regular-Season minPerPosition
     * so the panel and the submit-time validator agree.
     */
    private const MIN_PER_POSITION = 3;

    /**
     * Slot label => the `ibl_plr` depth column it maps to. Order mirrors
     * \JSB::PLAYER_POSITIONS and DepthChartEntryView::POSITION_SLOTS.
     *
     * @var array<string, string>
     */
    private const SLOT_DEPTH_KEYS = [
        'PG' => 'dc_pg_depth',
        'SG' => 'dc_sg_depth',
        'SF' => 'dc_sf_depth',
        'PF' => 'dc_pf_depth',
        'C'  => 'dc_c_depth',
    ];

    /**
     * @see LineupHealthAnalyzerInterface::analyze()
     * @param list<PlayerRow> $roster
     * @return list<LineupWarning>
     */
    public function analyze(array $roster, int $totalSalary): array
    {
        $warnings = [];

        // 1. No starter at a position (no player with dc_*_depth === 1).
        foreach (self::SLOT_DEPTH_KEYS as $label => $key) {
            if (!$this->hasStarterAt($roster, $key)) {
                $warnings[] = [
                    'type' => 'no_starter',
                    'message' => "No starter (1st) assigned at {$label}.",
                ];
            }
        }

        // 2. Thin position depth (fewer than MIN_PER_POSITION players assigned
        //    at depth 1-5). Distinct from check 1: counts depth 1-5, not just 1.
        foreach (self::SLOT_DEPTH_KEYS as $label => $key) {
            $assigned = $this->countAssignedAt($roster, $key);
            if ($assigned < self::MIN_PER_POSITION) {
                $warnings[] = [
                    'type' => 'thin_depth',
                    'message' => "Thin depth at {$label}: only {$assigned} player(s) assigned (want at least " . self::MIN_PER_POSITION . ").",
                ];
            }
        }

        // 3. Injured starter (dc_*_depth === 1 AND injured > 0).
        foreach (self::SLOT_DEPTH_KEYS as $label => $key) {
            foreach ($roster as $player) {
                if ($this->depthOf($player, $key) === 1 && $this->intField($player, 'injured') > 0) {
                    $warnings[] = [
                        'type' => 'injured_starter',
                        'message' => $this->nameOf($player) . " is your starter at {$label} but is injured.",
                    ];
                }
            }
        }

        // 4. Inactive starter (dc_*_depth === 1 AND dc_can_play_in_game === 0).
        foreach (self::SLOT_DEPTH_KEYS as $label => $key) {
            foreach ($roster as $player) {
                if ($this->depthOf($player, $key) === 1 && $this->intField($player, 'dc_can_play_in_game') === 0) {
                    $warnings[] = [
                        'type' => 'inactive_starter',
                        'message' => $this->nameOf($player) . " is your starter at {$label} but is flagged inactive (won't play).",
                    ];
                }
            }
        }

        // 5. Over the soft cap (strictly greater than SOFT_CAP_MAX).
        if ($totalSalary > League::SOFT_CAP_MAX) {
            $warnings[] = [
                'type' => 'over_cap',
                'message' => "Team salary ({$totalSalary}) is over the soft cap (" . League::SOFT_CAP_MAX . ").",
            ];
        }

        return $warnings;
    }

    /**
     * @param list<PlayerRow> $roster
     */
    private function hasStarterAt(array $roster, string $depthKey): bool
    {
        foreach ($roster as $player) {
            if ($this->depthOf($player, $depthKey) === 1) {
                return true;
            }
        }
        return false;
    }

    /**
     * Count players assigned at this position (any depth 1-5).
     *
     * @param list<PlayerRow> $roster
     */
    private function countAssignedAt(array $roster, string $depthKey): int
    {
        $count = 0;
        foreach ($roster as $player) {
            $depth = $this->depthOf($player, $depthKey);
            if ($depth >= 1 && $depth <= 5) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Read a depth column defensively — PlayerRow types dc_*_depth as ?int.
     *
     * @param PlayerRow $player
     */
    private function depthOf(array $player, string $depthKey): int
    {
        return $this->intField($player, $depthKey);
    }

    /**
     * Read a nullable int column reached through a dynamic key. The PlayerRow
     * catch-all types dynamic access as mixed, so narrow before casting (a
     * non-numeric value reads as 0), matching DepthChartEntryView::clampDepthValue().
     *
     * @param PlayerRow $player
     */
    private function intField(array $player, string $key): int
    {
        $raw = $player[$key] ?? 0;
        return is_numeric($raw) ? (int) $raw : 0;
    }

    /**
     * @param PlayerRow $player
     */
    private function nameOf(array $player): string
    {
        $name = $player['name'] ?? '';
        return is_string($name) ? $name : '';
    }
}
