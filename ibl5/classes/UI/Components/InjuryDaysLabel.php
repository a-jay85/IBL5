<?php

declare(strict_types=1);

namespace UI\Components;

use Utilities\HtmlSanitizer;

/**
 * Renders injury days with a return-date tooltip.
 *
 * Produces a <span> with the `injury-days-tooltip` CSS class (dotted underline,
 * help cursor, hover/focus tooltip showing the expected return date).
 *
 * Shared by the Injuries module table and the Ratings table.
 */
class InjuryDaysLabel
{
    /**
     * Render an injury-days value with an optional return-date tooltip.
     *
     * @param int    $daysRemaining Days remaining for injury (0 = not injured)
     * @param string $returnDate    Expected return date (Y-m-d), or empty string
     * @return string HTML: tooltip span when injured with a return date,
     *                plain number when injured without a date, or empty string
     */
    public static function render(int $daysRemaining, string $returnDate): string
    {
        if ($daysRemaining <= 0) {
            return '';
        }

        if ($returnDate === '') {
            return (string) $daysRemaining;
        }

        $safeReturnDate = HtmlSanitizer::safeHtmlOutput($returnDate);

        return '<span class="injury-days-tooltip" title="Returns: '
            . $safeReturnDate
            . '" tabindex="0">'
            . $daysRemaining
            . '</span>';
    }
}
