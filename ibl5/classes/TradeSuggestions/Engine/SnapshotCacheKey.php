<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use TradeSuggestions\TeamMode;

/**
 * Cache key for one league state (F14). Every component is a positional
 * argument, so a caller cannot drop one. Bump the `v1` segment with
 * `SnapshotCodec::VERSION`.
 */
final class SnapshotCacheKey
{
    public const string PREFIX = 'trade_suggestions:snapshot:v1:';

    /**
     * @param array<int, TeamMode> $modesByTid
     */
    public static function build(
        string $simMarker,
        string $seasonPhase,
        string $rosterDigest,
        string $tradeMarker,
        array $modesByTid,
    ): string {
        ksort($modesByTid);
        $pairs = [];
        foreach ($modesByTid as $tid => $mode) {
            $pairs[] = $tid . '=' . $mode->name;
        }

        return self::PREFIX . sha1(implode('|', [$simMarker, $seasonPhase, $rosterDigest, $tradeMarker, implode(',', $pairs)]));
    }
}
