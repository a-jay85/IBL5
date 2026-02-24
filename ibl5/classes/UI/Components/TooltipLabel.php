<?php

declare(strict_types=1);

namespace UI\Components;

use Utilities\HtmlSanitizer;

/**
 * Renders a value with a CSS-only hover/focus tooltip.
 *
 * Produces a <span> with the `ibl-tooltip` CSS class (dotted underline,
 * help cursor, hover/focus tooltip showing additional context).
 *
 * Used for injury return dates, sim number context, and any other
 * value that benefits from a hover/tap tooltip.
 */
class TooltipLabel
{
    /**
     * Render a value with an optional tooltip.
     *
     * @param string $displayValue The visible text (caller is responsible for sanitization)
     * @param string $tooltipText  Text shown on hover/focus, or empty string for no tooltip
     * @param string $cssClass     Additional CSS class(es) to add to the span
     * @return string HTML: tooltip span when tooltipText is provided, plain displayValue otherwise
     */
    public static function render(string $displayValue, string $tooltipText, string $cssClass = ''): string
    {
        if ($tooltipText === '') {
            return $displayValue;
        }

        $safeTooltip = HtmlSanitizer::safeHtmlOutput($tooltipText);

        $classes = $cssClass !== '' ? 'ibl-tooltip ' . $cssClass : 'ibl-tooltip';

        return '<span class="' . $classes . '" title="' . $safeTooltip . '" tabindex="0">'
            . $displayValue
            . '</span>';
    }
}
