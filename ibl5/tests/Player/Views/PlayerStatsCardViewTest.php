<?php

declare(strict_types=1);

namespace Tests\Player\Views;

use PHPUnit\Framework\TestCase;
use Player\Views\PlayerStatsCardView;
use Player\Views\PlayerStatsFlipCardView;

/**
 * Tests for PlayerStatsCardView and PlayerStatsFlipCardView
 * 
 * @covers \Player\Views\PlayerStatsCardView
 * @covers \Player\Views\PlayerStatsFlipCardView
 */
class PlayerStatsCardViewTest extends TestCase
{
    // =========================================================================
    // PlayerStatsCardView Tests
    // =========================================================================

    public function testGetStylesReturnsValidCss(): void
    {
        $styles = PlayerStatsCardView::getStyles();
        
        $this->assertStringContainsString('<style>', $styles);
        $this->assertStringContainsString('.player-stats-card', $styles);
        $this->assertStringContainsString('.stats-table', $styles);
        $this->assertStringContainsString('</style>', $styles);
    }

    public function testWrapAddsCardWrapper(): void
    {
        $tableContent = '<table><tr><td>Test</td></tr></table>';
        
        $result = PlayerStatsCardView::wrap($tableContent);
        
        $this->assertStringContainsString('class="player-stats-card"', $result);
        $this->assertStringContainsString($tableContent, $result);
    }

    public function testWrapWithStatsType(): void
    {
        $tableContent = '<table><tr><td>Test</td></tr></table>';
        
        $result = PlayerStatsCardView::wrap($tableContent, '', 'Averages');
        
        $this->assertStringContainsString('stats-type-indicator', $result);
        $this->assertStringContainsString('Averages', $result);
    }

    public function testStyleTableReplacesPlayerTableClasses(): void
    {
        $tableHtml = '<table class="sortable player-table"><tr><td>Test</td></tr></table>';
        
        $result = PlayerStatsCardView::styleTable($tableHtml);
        
        $this->assertStringContainsString('class="stats-table sortable"', $result);
        $this->assertStringNotContainsString('player-table"', $result);
    }

    public function testStyleTableReplacesPlayerTableHeader(): void
    {
        $tableHtml = '<td class="player-table-header">Regular Season</td>';
        
        $result = PlayerStatsCardView::styleTable($tableHtml);
        
        $this->assertStringContainsString('class="stats-table-header"', $result);
        $this->assertStringNotContainsString('player-table-header', $result);
    }

    public function testStyleTableAddsCareerRowClass(): void
    {
        $tableHtml = '<tr class="player-table-row-bold"><td>Career</td></tr>';
        
        $result = PlayerStatsCardView::styleTable($tableHtml);
        
        $this->assertStringContainsString('class="player-table-row-bold career-row"', $result);
    }

    public function testStyleTableHandlesSimStatsTableVariant(): void
    {
        $tableHtml = '<table class="sortable player-table sim-stats-table"><tr><td>Test</td></tr></table>';
        
        $result = PlayerStatsCardView::styleTable($tableHtml);
        
        $this->assertStringContainsString('class="stats-table sortable sim-stats-table"', $result);
    }

    public function testRenderCombinesStyleAndWrap(): void
    {
        $tableHtml = '<table class="sortable player-table"><tr><td>Test</td></tr></table>';
        
        $result = PlayerStatsCardView::render($tableHtml);
        
        $this->assertStringContainsString('class="player-stats-card"', $result);
        $this->assertStringContainsString('class="stats-table sortable"', $result);
    }

    public function testRenderWithStatsType(): void
    {
        $tableHtml = '<table class="sortable player-table"><tr><td>Test</td></tr></table>';
        
        $result = PlayerStatsCardView::render($tableHtml, 'Totals');
        
        $this->assertStringContainsString('stats-type-indicator', $result);
        $this->assertStringContainsString('Totals', $result);
    }

    // =========================================================================
    // PlayerStatsFlipCardView Tests
    // =========================================================================

    public function testFlipSupportedTypesConstant(): void
    {
        $supportedTypes = PlayerStatsFlipCardView::FLIP_SUPPORTED_TYPES;
        
        $this->assertContains('regular-season', $supportedTypes);
        $this->assertContains('playoffs', $supportedTypes);
        $this->assertContains('olympics', $supportedTypes);
        $this->assertContains('heat', $supportedTypes);
        $this->assertCount(4, $supportedTypes);
    }

    public function testGetFlipStylesReturnsValidCssAndJs(): void
    {
        $styles = PlayerStatsFlipCardView::getFlipStyles();
        
        $this->assertStringContainsString('<style>', $styles);
        $this->assertStringContainsString('.stats-flip-container', $styles);
        $this->assertStringContainsString('.stats-flip-toggle', $styles);
        $this->assertStringContainsString('</style>', $styles);
        
        $this->assertStringContainsString('<script>', $styles);
        $this->assertStringContainsString('DOMContentLoaded', $styles);
        $this->assertStringContainsString('</script>', $styles);
    }

    public function testRenderCreatesFlipContainer(): void
    {
        $averagesHtml = '<table class="sortable player-table"><td class="player-table-header">Averages</td></table>';
        $totalsHtml = '<table class="sortable player-table"><td class="player-table-header">Totals</td></table>';
        
        $result = PlayerStatsFlipCardView::render($averagesHtml, $totalsHtml, 'Regular Season');
        
        $this->assertStringContainsString('class="stats-flip-container"', $result);
        $this->assertStringContainsString('class="stats-flip-inner"', $result);
        $this->assertStringContainsString('class="stats-front"', $result);
        $this->assertStringContainsString('class="stats-back"', $result);
    }

    public function testRenderIncludesFlipToggle(): void
    {
        $averagesHtml = '<table class="sortable player-table"><td class="player-table-header">Averages</td></table>';
        $totalsHtml = '<table class="sortable player-table"><td class="player-table-header">Totals</td></table>';
        
        $result = PlayerStatsFlipCardView::render($averagesHtml, $totalsHtml);
        
        $this->assertStringContainsString('stats-flip-toggle', $result);
        $this->assertStringContainsString('toggle-label', $result);
    }

    public function testRenderIncludesViewLabels(): void
    {
        $averagesHtml = '<table class="sortable player-table"><td class="player-table-header">Averages</td></table>';
        $totalsHtml = '<table class="sortable player-table"><td class="player-table-header">Totals</td></table>';
        
        $result = PlayerStatsFlipCardView::render($averagesHtml, $totalsHtml, 'Playoffs');
        
        $this->assertStringContainsString('stats-view-label', $result);
        $this->assertStringContainsString('Averages', $result);
        $this->assertStringContainsString('Totals', $result);
    }

    public function testRenderShowsAveragesFirstByDefault(): void
    {
        $averagesHtml = '<table class="sortable player-table"><td class="player-table-header">AVG_CONTENT</td></table>';
        $totalsHtml = '<table class="sortable player-table"><td class="player-table-header">TOT_CONTENT</td></table>';
        
        $result = PlayerStatsFlipCardView::render($averagesHtml, $totalsHtml);
        
        // Averages should appear in stats-front
        $frontPos = strpos($result, 'class="stats-front"');
        $backPos = strpos($result, 'class="stats-back"');
        $avgPos = strpos($result, 'AVG_CONTENT');
        $totPos = strpos($result, 'TOT_CONTENT');
        
        // AVG should be between front and back
        $this->assertGreaterThan($frontPos, $avgPos);
        $this->assertLessThan($backPos, $avgPos);
        
        // TOT should be after back
        $this->assertGreaterThan($backPos, $totPos);
    }

    public function testRenderCanShowTotalsFirst(): void
    {
        $averagesHtml = '<table class="sortable player-table"><td class="player-table-header">AVG_CONTENT</td></table>';
        $totalsHtml = '<table class="sortable player-table"><td class="player-table-header">TOT_CONTENT</td></table>';
        
        $result = PlayerStatsFlipCardView::render($averagesHtml, $totalsHtml, '', false);
        
        // Totals should appear in stats-front
        $frontPos = strpos($result, 'class="stats-front"');
        $backPos = strpos($result, 'class="stats-back"');
        $totPos = strpos($result, 'TOT_CONTENT');
        $avgPos = strpos($result, 'AVG_CONTENT');
        
        // TOT should be between front and back
        $this->assertGreaterThan($frontPos, $totPos);
        $this->assertLessThan($backPos, $totPos);
        
        // AVG should be after back
        $this->assertGreaterThan($backPos, $avgPos);
    }

    public function testRenderSetsDataCategoryAttribute(): void
    {
        $averagesHtml = '<table class="sortable player-table"><td class="player-table-header">Averages</td></table>';
        $totalsHtml = '<table class="sortable player-table"><td class="player-table-header">Totals</td></table>';
        
        $result = PlayerStatsFlipCardView::render($averagesHtml, $totalsHtml, 'Olympics');
        
        $this->assertStringContainsString('data-category="Olympics"', $result);
    }

    public function testRenderEscapesCategoryForXss(): void
    {
        $averagesHtml = '<table class="sortable player-table"><td class="player-table-header">Averages</td></table>';
        $totalsHtml = '<table class="sortable player-table"><td class="player-table-header">Totals</td></table>';
        
        $maliciousCategory = '<script>alert("xss")</script>';
        $result = PlayerStatsFlipCardView::render($averagesHtml, $totalsHtml, $maliciousCategory);
        
        $this->assertStringNotContainsString('<script>alert', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testRenderAppliesStatsCardStylingToTables(): void
    {
        $averagesHtml = '<table class="sortable player-table"><tr class="player-table-row-bold"><td>Career</td></tr></table>';
        $totalsHtml = '<table class="sortable player-table"><tr class="player-table-row-bold"><td>Career</td></tr></table>';
        
        $result = PlayerStatsFlipCardView::render($averagesHtml, $totalsHtml);
        
        // Check that table classes were transformed
        $this->assertStringContainsString('class="stats-table sortable"', $result);
        $this->assertStringContainsString('career-row', $result);
    }

    public function testRenderIncludesPlayerStatsCardWrapper(): void
    {
        $averagesHtml = '<table class="sortable player-table"><td class="player-table-header">Averages</td></table>';
        $totalsHtml = '<table class="sortable player-table"><td class="player-table-header">Totals</td></table>';
        
        $result = PlayerStatsFlipCardView::render($averagesHtml, $totalsHtml);
        
        // Should wrap content in player-stats-card (2 times - front and back)
        $matches = substr_count($result, 'class="player-stats-card"');
        $this->assertEquals(2, $matches);
    }

    public function testRenderIncludesFlipIconSvg(): void
    {
        $averagesHtml = '<table class="sortable player-table"><td class="player-table-header">Averages</td></table>';
        $totalsHtml = '<table class="sortable player-table"><td class="player-table-header">Totals</td></table>';
        
        $result = PlayerStatsFlipCardView::render($averagesHtml, $totalsHtml);
        
        $this->assertStringContainsString('<svg viewBox=', $result);
        $this->assertStringContainsString('</svg>', $result);
    }
}
