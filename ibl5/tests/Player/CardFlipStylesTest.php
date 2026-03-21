<?php

declare(strict_types=1);

namespace Tests\Player;

use PHPUnit\Framework\TestCase;
use Player\Views\CardFlipStyles;

/**
 * @covers \Player\Views\CardFlipStyles
 */
class CardFlipStylesTest extends TestCase
{
    // --- getFlipIcon() ---

    public function testGetFlipIconReturnsSvg(): void
    {
        $svg = CardFlipStyles::getFlipIcon();

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('viewBox', $svg);
        $this->assertStringContainsString('</svg>', $svg);
    }

    // --- getFlipScript() ---

    public function testGetFlipScriptContainsContainerSelector(): void
    {
        $js = CardFlipStyles::getFlipScript('.my-container', '.my-icon');

        $this->assertStringContainsString('.my-container', $js);
    }

    public function testGetFlipScriptContainsIconSelector(): void
    {
        $js = CardFlipStyles::getFlipScript('.my-container', '.my-icon');

        $this->assertStringContainsString('.my-icon', $js);
    }

    public function testGetFlipScriptWithoutToggleLabelsOmitsLabelCode(): void
    {
        $js = CardFlipStyles::getFlipScript('.container', '.icon', false);

        $this->assertStringNotContainsString('toggle-label', $js);
        $this->assertStringNotContainsString('Totals', $js);
    }

    public function testGetFlipScriptWithToggleLabelsIncludesLabelCode(): void
    {
        $js = CardFlipStyles::getFlipScript('.container', '.icon', true);

        $this->assertStringContainsString('toggle-label', $js);
        $this->assertStringContainsString('Totals', $js);
        $this->assertStringContainsString('Averages', $js);
    }

    public function testGetFlipScriptContainsFlipClassToggle(): void
    {
        $js = CardFlipStyles::getFlipScript('.container', '.icon');

        $this->assertStringContainsString("classList.toggle('flipped')", $js);
    }

    // --- getTradingCardFlipStyles() ---

    public function testGetTradingCardFlipStylesReturnsScriptTag(): void
    {
        $html = CardFlipStyles::getTradingCardFlipStyles();

        $this->assertStringContainsString('<script>', $html);
        $this->assertStringContainsString('</script>', $html);
    }

    public function testGetTradingCardFlipStylesUsesCardFlipContainerSelector(): void
    {
        $html = CardFlipStyles::getTradingCardFlipStyles();

        $this->assertStringContainsString('.card-flip-container', $html);
        $this->assertStringContainsString('.flip-icon', $html);
    }

    // --- getStatsCardFlipStyles() ---

    public function testGetStatsCardFlipStylesReturnsScriptTag(): void
    {
        $html = CardFlipStyles::getStatsCardFlipStyles();

        $this->assertStringContainsString('<script>', $html);
        $this->assertStringContainsString('</script>', $html);
    }

    public function testGetStatsCardFlipStylesUsesStatsFlipContainerSelector(): void
    {
        $html = CardFlipStyles::getStatsCardFlipStyles();

        $this->assertStringContainsString('.stats-flip-container', $html);
        $this->assertStringContainsString('.stats-flip-toggle', $html);
    }

    public function testGetStatsCardFlipStylesContainsTouchScrollPolyfill(): void
    {
        $html = CardFlipStyles::getStatsCardFlipStyles();

        $this->assertStringContainsString('touchstart', $html);
        $this->assertStringContainsString('touchmove', $html);
        $this->assertStringContainsString('scrollLeft', $html);
    }

    public function testGetStatsCardFlipStylesIncludesToggleLabelLogic(): void
    {
        $html = CardFlipStyles::getStatsCardFlipStyles();

        $this->assertStringContainsString('toggle-label', $html);
    }
}
