<?php

declare(strict_types=1);

namespace Utilities;

/**
 * Builds URLs to IBL6 SvelteKit box score pages, with legacy fallback.
 *
 * IBL6 pattern: {IBL6_BASE_URL}/{YYYY-MM-DD}-game-{gameOfThatDay}/boxscore
 * Legacy fallback: ./ibl/IBL/box{BoxID}.htm
 */
class BoxScoreUrlBuilder
{
    private const DEFAULT_BASE_URL = 'https://ibl6.iblhoops.net';

    /**
     * Build a box score URL, preferring IBL6 format with legacy BoxID fallback.
     *
     * Returns empty string only if neither gameOfThatDay nor boxId is available
     * (unplayed games).
     */
    public static function buildUrl(string $date, int $gameOfThatDay, int $boxId = 0): string
    {
        if ($gameOfThatDay > 0 && $date !== '' && $date !== '0000-00-00') {
            $rawBase = defined('IBL6_BASE_URL') ? constant('IBL6_BASE_URL') : self::DEFAULT_BASE_URL;
            $baseUrl = is_string($rawBase) ? $rawBase : self::DEFAULT_BASE_URL;

            return $baseUrl . '/' . $date . '-game-' . $gameOfThatDay . '/boxscore';
        }

        if ($boxId > 0) {
            return './ibl/IBL/box' . $boxId . '.htm';
        }

        return '';
    }
}
