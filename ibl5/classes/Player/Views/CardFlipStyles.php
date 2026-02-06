<?php

declare(strict_types=1);

namespace Player\Views;

/**
 * CardFlipStyles - Shared flip animation styles and scripts
 * 
 * Consolidates the flip functionality used by both PlayerTradingCardFlipView
 * and PlayerStatsFlipCardView to eliminate duplication.
 * 
 * @since 2026-01-08
 */
class CardFlipStyles
{
    /**
     * The shared flip icon SVG
     */
    private const FLIP_ICON_SVG = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg>';

    /**
     * Get the flip icon SVG
     * 
     * @return string SVG HTML
     */
    public static function getFlipIcon(): string
    {
        return self::FLIP_ICON_SVG;
    }

    /**
     * Get flip container CSS for Y-axis rotation (trading cards)
     *
     * @deprecated CSS is now centralized in design/components/player-cards.css.
     *
     * @param string $containerClass Class for the flip container
     * @param string $innerClass Class for the inner wrapper
     * @return string Empty string — styles are in centralized CSS
     */
    public static function getYAxisFlipCss(string $containerClass, string $innerClass): string
    {
        return '';
    }

    /**
     * Get flip container CSS for X-axis rotation (stats cards)
     *
     * @deprecated CSS is now centralized in design/components/player-cards.css.
     *
     * @param string $containerClass Class for the flip container
     * @param string $innerClass Class for the inner wrapper
     * @return string Empty string — styles are in centralized CSS
     */
    public static function getXAxisFlipCss(string $containerClass, string $innerClass): string
    {
        return '';
    }

    /**
     * Get flip icon/button CSS
     *
     * @deprecated CSS is now centralized in design/components/player-cards.css.
     *
     * @param array{primary: string, secondary: string, gradient_start: string, gradient_mid: string, gradient_end: string, border: string, border_rgb: string, accent: string, text: string, text_muted: string} $colorScheme Color scheme from TeamColorHelper
     * @param string $iconClass CSS class for the flip icon
     * @param bool $isButton Whether it's a button (stats) or icon (trading card)
     * @return string Empty string — styles are in centralized CSS
     */
    public static function getFlipIconCss(array $colorScheme, string $iconClass, bool $isButton = false): string
    {
        return '';
    }

    /**
     * Get pulse animation CSS
     *
     * @deprecated CSS is now centralized in design/components/player-cards.css.
     *
     * @param array{primary: string, secondary: string, gradient_start: string, gradient_mid: string, gradient_end: string, border: string, border_rgb: string, accent: string, text: string, text_muted: string} $colorScheme Color scheme from TeamColorHelper
     * @param string $iconClass CSS class for the flip icon
     * @param string $animationName Name for the keyframes animation
     * @return string Empty string — styles are in centralized CSS
     */
    public static function getPulseAnimationCss(array $colorScheme, string $iconClass, string $animationName = 'pulse-glow'): string
    {
        return '';
    }

    /**
     * Get the flip JavaScript for any flip container
     * 
     * @param string $containerSelector CSS selector for flip containers
     * @param string $iconSelector CSS selector for flip icons
     * @param bool $toggleLabels Whether to toggle text labels (for stats cards)
     * @return string JavaScript code
     */
    public static function getFlipScript(string $containerSelector, string $iconSelector, bool $toggleLabels = false): string
    {
        $labelToggleCode = $toggleLabels ? <<<JS

                // Update toggle label based on state
                const isFlipped = container.classList.contains('flipped');
                flipIcons.forEach(function(t) {
                    const label = t.querySelector('.toggle-label');
                    if (label) {
                        const currentText = label.textContent;
                        if (currentText.includes('Totals')) {
                            label.textContent = 'Averages';
                        } else {
                            label.textContent = 'Totals';
                        }
                    }
                });
JS
        : '';

        return <<<JS
document.addEventListener('DOMContentLoaded', function() {
    const flipContainers = document.querySelectorAll('{$containerSelector}');
    
    flipContainers.forEach(function(container) {
        const flipIcons = container.querySelectorAll('{$iconSelector}');
        
        flipIcons.forEach(function(flipIcon) {
            flipIcon.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                container.classList.toggle('flipped');
                {$labelToggleCode}
                // Remove pulse animation after first flip
                flipIcons.forEach(function(icon) {
                    icon.classList.remove('pulse');
                });
            });
        });
        
        // Add pulse for first 5 seconds to draw attention
        if (flipIcons.length > 0) {
            setTimeout(function() {
                flipIcons.forEach(function(icon) {
                    icon.classList.remove('pulse');
                });
            }, 5000);
        }
    });
});
JS;
    }

    /**
     * Get complete flip styles for trading cards (Y-axis rotation)
     *
     * CSS is now centralized in design/components/player-cards.css.
     * Only the JavaScript for flip interaction is returned.
     *
     * @return string HTML script tag with JavaScript
     */
    public static function getTradingCardFlipStyles(): string
    {
        $script = self::getFlipScript('.card-flip-container', '.flip-icon', false);

        return <<<HTML
<script>
{$script}
</script>
HTML;
    }

    /**
     * Get complete flip styles for stats cards (X-axis rotation)
     *
     * CSS is now centralized in design/components/player-cards.css.
     * Only the JavaScript for flip interaction is returned.
     *
     * @param array{primary: string, secondary: string, gradient_start: string, gradient_mid: string, gradient_end: string, border: string, border_rgb: string, accent: string, text: string, text_muted: string}|null $colorScheme Optional color scheme (no longer used for CSS)
     * @return string HTML script tag with JavaScript
     */
    public static function getStatsCardFlipStyles(?array $colorScheme = null): string
    {
        $script = self::getFlipScript('.stats-flip-container', '.stats-flip-toggle', true);

        return <<<HTML
<script>
{$script}
</script>
HTML;
    }
}
