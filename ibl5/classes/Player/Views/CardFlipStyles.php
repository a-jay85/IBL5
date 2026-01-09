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
     * @param string $containerClass Class for the flip container
     * @param string $innerClass Class for the inner wrapper
     * @return string CSS rules
     */
    public static function getYAxisFlipCss(string $containerClass, string $innerClass): string
    {
        return <<<CSS
.{$containerClass} {
    perspective: 1000px;
    max-width: 420px;
    margin: 0 auto;
    position: relative;
}

.{$innerClass} {
    position: relative;
    width: 100%;
    transition: transform 0.6s;
    transform-style: preserve-3d;
}

.{$containerClass}.flipped .{$innerClass} {
    transform: rotateY(180deg);
}

.{$containerClass} .card-front,
.{$containerClass} .card-back {
    width: 100%;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    position: relative;
}

.{$containerClass} .card-back {
    position: absolute;
    top: 0;
    left: 0;
    transform: rotateY(180deg);
}
CSS;
    }

    /**
     * Get flip container CSS for X-axis rotation (stats cards)
     * 
     * @param string $containerClass Class for the flip container
     * @param string $innerClass Class for the inner wrapper
     * @return string CSS rules
     */
    public static function getXAxisFlipCss(string $containerClass, string $innerClass): string
    {
        return <<<CSS
.{$containerClass} {
    perspective: 2000px;
    margin: 16px auto;
    position: relative;
    min-height: 200px;
}

.{$innerClass} {
    position: relative;
    width: 100%;
    transition: transform 0.6s ease-in-out;
    transform-style: preserve-3d;
}

.{$containerClass}.flipped .{$innerClass} {
    transform: rotateX(180deg);
}

.{$containerClass} .stats-front,
.{$containerClass} .stats-back {
    width: 100%;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
}

.{$containerClass} .stats-front {
    position: relative;
}

.{$containerClass} .stats-back {
    position: absolute;
    top: 0;
    left: 0;
    transform: rotateX(180deg);
}
CSS;
    }

    /**
     * Get flip icon/button CSS
     * 
     * @param array $colorScheme Color scheme from TeamColorHelper
     * @param string $iconClass CSS class for the flip icon
     * @param bool $isButton Whether it's a button (stats) or icon (trading card)
     * @return string CSS rules
     */
    public static function getFlipIconCss(array $colorScheme, string $iconClass, bool $isButton = false): string
    {
        $borderRgb = $colorScheme['border_rgb'];
        $gradMid = $colorScheme['gradient_mid'];
        $accent = $colorScheme['accent'];
        
        if ($isButton) {
            // Stats flip toggle button style
            return <<<CSS
.{$iconClass} {
    position: absolute;
    top: 8px;
    right: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
    background: rgba({$borderRgb}, 0.95);
    color: #{$gradMid};
    font-size: 11px;
    font-weight: 700;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    z-index: 20;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    border: none;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.{$iconClass}:hover {
    background: rgba({$borderRgb}, 1);
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba({$borderRgb}, 0.5);
}

.{$iconClass} svg {
    width: 14px;
    height: 14px;
    fill: #{$gradMid};
    transition: transform 0.3s ease;
}

.{$iconClass}:hover svg {
    transform: rotate(180deg);
}

.{$iconClass}::after {
    content: 'Click to toggle view';
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 8px;
    padding: 4px 8px;
    background: rgba(0, 0, 0, 0.9);
    color: #{$accent};
    font-size: 10px;
    font-weight: 400;
    text-transform: none;
    white-space: nowrap;
    border-radius: 4px;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s;
}

.{$iconClass}:hover::after {
    opacity: 1;
}

@media (max-width: 768px) {
    .{$iconClass} {
        font-size: 10px;
        padding: 4px 8px;
        top: 4px;
        right: 4px;
    }
    
    .{$iconClass} svg {
        width: 12px;
        height: 12px;
    }
}
CSS;
        } else {
            // Trading card flip icon style (circular)
            return <<<CSS
.{$iconClass} {
    position: absolute;
    bottom: 12px;
    right: 12px;
    width: 32px;
    height: 32px;
    background: rgba(212, 175, 55, 0.9);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 10;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

.{$iconClass}:hover {
    background: rgba(212, 175, 55, 1);
    transform: scale(1.1) rotate(180deg);
    box-shadow: 0 4px 12px rgba(212, 175, 55, 0.5);
}

.{$iconClass} svg {
    width: 20px;
    height: 20px;
    fill: #0f1419;
}

.{$iconClass}::before {
    content: 'Click to flip';
    position: absolute;
    bottom: 100%;
    right: 0;
    margin-bottom: 8px;
    padding: 4px 8px;
    background: rgba(0, 0, 0, 0.8);
    color: #D4AF37;
    font-size: 11px;
    white-space: nowrap;
    border-radius: 4px;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s, transform 0.3s;
}

.{$iconClass}:hover::before {
    opacity: 1;
    transform: rotate(-180deg);
}

@media (max-width: 480px) {
    .{$iconClass} {
        width: 28px;
        height: 28px;
        bottom: 8px;
        right: 8px;
    }
    
    .{$iconClass} svg {
        width: 16px;
        height: 16px;
    }
}
CSS;
        }
    }

    /**
     * Get pulse animation CSS
     * 
     * @param array $colorScheme Color scheme from TeamColorHelper
     * @param string $iconClass CSS class for the flip icon
     * @param string $animationName Name for the keyframes animation
     * @return string CSS rules
     */
    public static function getPulseAnimationCss(array $colorScheme, string $iconClass, string $animationName = 'pulse-glow'): string
    {
        $borderRgb = $colorScheme['border_rgb'] ?? '212, 175, 55';
        
        return <<<CSS
@keyframes {$animationName} {
    0%, 100% {
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    }
    50% {
        box-shadow: 0 2px 16px rgba({$borderRgb}, 0.7);
    }
}

.{$iconClass}.pulse {
    animation: {$animationName} 2s ease-in-out infinite;
}
CSS;
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
     * @return string Complete CSS and JS in HTML tags
     */
    public static function getTradingCardFlipStyles(): string
    {
        $flipCss = self::getYAxisFlipCss('card-flip-container', 'card-flip-inner');
        $iconCss = self::getFlipIconCss(TeamColorHelper::getDefaultColorScheme(), 'flip-icon', false);
        $pulseCss = self::getPulseAnimationCss(TeamColorHelper::getDefaultColorScheme(), 'flip-icon', 'pulse-glow');
        $script = self::getFlipScript('.card-flip-container', '.flip-icon', false);
        
        return <<<HTML
<style>
{$flipCss}
{$iconCss}
{$pulseCss}
</style>
<script>
{$script}
</script>
HTML;
    }

    /**
     * Get complete flip styles for stats cards (X-axis rotation)
     * 
     * @param array|null $colorScheme Optional color scheme
     * @return string Complete CSS and JS in HTML tags
     */
    public static function getStatsCardFlipStyles(?array $colorScheme = null): string
    {
        if ($colorScheme === null) {
            $colorScheme = TeamColorHelper::getDefaultColorScheme();
        }
        
        $flipCss = self::getXAxisFlipCss('stats-flip-container', 'stats-flip-inner');
        $iconCss = self::getFlipIconCss($colorScheme, 'stats-flip-toggle', true);
        $pulseCss = self::getPulseAnimationCss($colorScheme, 'stats-flip-toggle', 'stats-pulse-glow');
        
        $accent = $colorScheme['accent'];
        $labelCss = <<<CSS
.stats-view-label {
    position: absolute;
    top: 8px;
    left: 8px;
    background: rgba(0, 0, 0, 0.6);
    color: #{$accent};
    font-size: 10px;
    font-weight: 600;
    padding: 4px 8px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    z-index: 15;
}

@media (max-width: 768px) {
    .stats-view-label {
        font-size: 9px;
        padding: 3px 6px;
    }
}
CSS;
        
        $script = self::getFlipScript('.stats-flip-container', '.stats-flip-toggle', true);
        
        return <<<HTML
<style>
{$flipCss}
{$iconCss}
{$pulseCss}
{$labelCss}
</style>
<script>
{$script}
</script>
HTML;
    }
}
