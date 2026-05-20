<?php

declare(strict_types=1);

namespace DepthChartEntry;

use DepthChartEntry\Contracts\DepthChartEntryServiceInterface;

/**
 * @see DepthChartEntryServiceInterface
 */
final class DepthChartEntryService implements DepthChartEntryServiceInterface
{
    /**
     * @see DepthChartEntryServiceInterface::computeQualityScore()
     */
    public function computeQualityScore(array $player): float
    {
        /** @var int $gp */
        $gp = $player['stats_gm'] ?? 0;
        if ($gp === 0) {
            return 0.0;
        }

        /** @var int $gs */
        $gs = $player['stats_gs'] ?? 0;
        /** @var int $min */
        $min = $player['stats_min'] ?? 0;
        /** @var int $fgm */
        $fgm = $player['stats_fgm'] ?? 0;
        /** @var int $fga */
        $fga = $player['stats_fga'] ?? 0;
        /** @var int $ftm */
        $ftm = $player['stats_ftm'] ?? 0;
        /** @var int $fta */
        $fta = $player['stats_fta'] ?? 0;
        /** @var int $tpm */
        $tpm = $player['stats_3gm'] ?? 0;
        /** @var int $tpa */
        $tpa = $player['stats_3ga'] ?? 0;
        /** @var int $orb */
        $orb = $player['stats_orb'] ?? 0;
        /** @var int $drb */
        $drb = $player['stats_drb'] ?? 0;
        /** @var int $ast */
        $ast = $player['stats_ast'] ?? 0;
        /** @var int $stl */
        $stl = $player['stats_stl'] ?? 0;
        /** @var int $tvr */
        $tvr = $player['stats_tvr'] ?? 0;
        /** @var int $blk */
        $blk = $player['stats_blk'] ?? 0;

        /** @var int $od */
        $od = $player['od'] ?? 5;
        /** @var int $dd */
        $dd = $player['dd'] ?? 5;
        /** @var int $pd */
        $pd = $player['pd'] ?? 5;
        /** @var int $td */
        $td = $player['td'] ?? 5;

        $twoPtMade = $fgm - $tpm;
        $twoPtAtt = $fga - $tpa;

        // TERM_A (defense): (OD+DD+PD+TD-20) × 0.25 × GS × (1/48)
        $termA = ($od + $dd + $pd + $td - 20) * 0.25 * $gs / 48.0;

        // TERM_B (production): (AST×0.8 + ORB×(2/3) + (DRB-ORB)×(1/3) + STL - TVR + BLK) × 0.75
        $termB = ($ast * 0.8 + $orb * (2.0 / 3.0) + ($drb - $orb) * (1.0 / 3.0) + $stl - $tvr + $blk) * 0.75;

        // TERM_C (scoring): ((FTM-2GM)×(1/6) + (MIN + FTA - (2GA-MIN)×(2/3) + 2GM - FTM×0.5)) × 1.5
        $termC = (($ftm - $twoPtMade) / 6.0
            + ($min + $fta - ($twoPtAtt - $min) * (2.0 / 3.0) + $twoPtMade - $ftm * 0.5)) * 1.5;

        return round(($termA + $termB + $termC) / $gp, 2);
    }

    /**
     * @see DepthChartEntryServiceInterface::computeJsbProduction()
     */
    public function computeJsbProduction(array $player): int
    {
        /** @var int $fgm */
        $fgm = $player['stats_fgm'] ?? 0;
        /** @var int $tgm */
        $tgm = $player['stats_3gm'] ?? 0;
        /** @var int $ftm */
        $ftm = $player['stats_ftm'] ?? 0;
        /** @var int $orb */
        $orb = $player['stats_orb'] ?? 0;
        /** @var int $drb */
        $drb = $player['stats_drb'] ?? 0;
        /** @var int $ast */
        $ast = $player['stats_ast'] ?? 0;
        /** @var int $stl */
        $stl = $player['stats_stl'] ?? 0;
        /** @var int $blk */
        $blk = $player['stats_blk'] ?? 0;

        return 2 * $fgm + $tgm + $ftm + $orb + $drb + $ast + $stl + $blk;
    }

    /**
     * @see DepthChartEntryServiceInterface::buildFormOverride()
     */
    public function buildFormOverride(array $postData): array
    {
        $override = [];

        for ($i = 1; $i <= 15; $i++) {
            $pidKey = 'pid' . $i;
            if (!isset($postData[$pidKey])) {
                continue;
            }
            $pid = self::intFromPost($postData[$pidKey]);
            if ($pid <= 0) {
                continue;
            }

            $override[$pid] = [
                'dc_pg_depth' => self::clampDepth(self::intFromPost($postData['pg' . $i] ?? 0)),
                'dc_sg_depth' => self::clampDepth(self::intFromPost($postData['sg' . $i] ?? 0)),
                'dc_sf_depth' => self::clampDepth(self::intFromPost($postData['sf' . $i] ?? 0)),
                'dc_pf_depth' => self::clampDepth(self::intFromPost($postData['pf' . $i] ?? 0)),
                'dc_c_depth' => self::clampDepth(self::intFromPost($postData['c' . $i] ?? 0)),
                'dc_can_play_in_game' => self::intFromPost($postData['canPlayInGame' . $i] ?? 0) === 1 ? 1 : 0,
                'dc_minutes' => self::clampMinutes(self::intFromPost($postData['min' . $i] ?? 0)),
            ];
        }

        return $override;
    }

    private static function intFromPost(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }
        return 0;
    }

    private static function clampDepth(int $value): int
    {
        if ($value < 0) {
            return 0;
        }
        if ($value > 5) {
            return 5;
        }
        return $value;
    }

    private static function clampMinutes(int $value): int
    {
        if ($value < 0) {
            return 0;
        }
        if ($value > 40) {
            return 40;
        }
        return $value;
    }
}
