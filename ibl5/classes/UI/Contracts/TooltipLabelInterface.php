<?php

declare(strict_types=1);

namespace UI\Contracts;

/**
 * TooltipLabelInterface - Contract for rendering a value with a CSS-only hover/focus tooltip
 */
interface TooltipLabelInterface
{
    /**
     * Render a value with an optional tooltip.
     *
     * @param string $displayValue The visible text (caller is responsible for sanitization)
     * @param string $tooltipText  Text shown on hover/focus, or empty string for no tooltip
     * @param string $cssClass     Additional CSS class(es) to add to the span
     * @return string HTML: tooltip span when tooltipText is provided, plain displayValue otherwise
     */
    public static function render(string $displayValue, string $tooltipText, string $cssClass = ''): string;
}
