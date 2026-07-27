<?php

declare(strict_types=1);

namespace LastSimRecap;

use Security\HtmlSanitizer;

/**
 * Renders a team name with a mobile-friendly short form.
 *
 * Returns ALREADY-ESCAPED HTML — call sites must NOT wrap the result in
 * HtmlSanitizer::e() again (double-escaping would surface as literal
 * &lt;span&gt; text in the recap).
 */
final class RecapTeamNameHelper
{
    private const MOBILE_SHORT_NAMES = [
        'Trailblazers' => 'Blazers',
        'Timberwolves' => 'Wolves',
    ];

    public static function responsive(string $name): string
    {
        $short = self::MOBILE_SHORT_NAMES[$name] ?? null;
        if ($short === null) {
            return HtmlSanitizer::e($name);
        }
        return '<span class="last-sim-recap__name-full">' . HtmlSanitizer::e($name) . '</span>'
             . '<span class="last-sim-recap__name-short">' . HtmlSanitizer::e($short) . '</span>';
    }
}
