<?php

declare(strict_types=1);

namespace Player\Views;

/**
 * PlayerViewStyles - Centralized CSS styles for all Player Views
 * 
 * Provides a unified style block that replaces deprecated HTML tags
 * (<center>, <font>, <b>, align=, bgcolor=) with modern CSS equivalents.
 * 
 * Include this at the top of your page or in a view factory to ensure
 * consistent styling across all player view components.
 * 
 * Usage:
 *   echo PlayerViewStyles::getStyles();
 * 
 * @since 2026-01-08
 */
class PlayerViewStyles
{
    /**
     * Returns the centralized CSS styles for Player Views
     *
     * @deprecated CSS is now centralized in design/components/player-views.css.
     *
     * @return string Empty string — styles are in centralized CSS
     */
    public static function getStyles(): string
    {
        return '';
    }

    /**
     * Returns inline styles for a single element (for gradual migration)
     * 
     * @param string $type Type of styling needed
     * @return string Inline style attribute value
     */
    public static function getInlineStyle(string $type): string
    {
        return match ($type) {
            'center' => 'text-align: center;',
            'bold' => 'font-weight: bold;',
            'header-blue' => 'background-color: #0000cc; color: #ffffff; font-weight: bold; text-align: center;',
            'table-center' => 'margin: 0 auto;',
            default => '',
        };
    }
}
