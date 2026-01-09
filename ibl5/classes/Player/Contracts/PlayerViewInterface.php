<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerViewInterface - Base contract for player view classes
 * 
 * All player views must implement a render method that returns HTML content.
 * Views are pure rendering classes with no database logic - all data must be
 * fetched via repositories before rendering.
 */
interface PlayerViewInterface
{
    /**
     * Render the view and return HTML content
     * 
     * Views should use output buffering (ob_start/ob_get_clean) internally
     * and return the complete HTML string. All output should be sanitized
     * using HtmlSanitizer::safeHtmlOutput() or htmlspecialchars().
     * 
     * @return string Rendered HTML content
     */
    public function render(): string;
}
