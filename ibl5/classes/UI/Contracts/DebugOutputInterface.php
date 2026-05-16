<?php

declare(strict_types=1);

namespace UI\Contracts;

/**
 * DebugOutputInterface - Contract for displaying debug information in a collapsible panel
 */
interface DebugOutputInterface
{
    /**
     * Display debug output in a collapsible panel
     *
     * SECURITY: Only displays to admin users. Content is HTML-escaped
     * to prevent XSS attacks.
     *
     * @param string $content The content to display
     * @param string $title The title of the debug panel
     * @return void
     */
    public static function display(string $content, string $title = 'Debug Output'): void;
}
