<?php

declare(strict_types=1);

namespace Tests\Player\Views;

use PHPUnit\Framework\TestCase;
use Player\Stats\Views\PlayerHeatAveragesView;
use Player\Stats\Views\PlayerHeatTotalsView;
use Player\Stats\Views\PlayerOlympicAveragesView;
use Player\Stats\Views\PlayerOlympicTotalsView;
use Player\Stats\Views\PlayerPlayoffAveragesView;
use Player\Stats\Views\PlayerPlayoffTotalsView;
use Player\Stats\Views\PlayerRegularSeasonAveragesView;
use Player\Stats\Views\PlayerRegularSeasonTotalsView;
use Player\Views\PlayerStatsFlipCardView;

/** @covers \Player\Views\PlayerStatsFlipCardView */
class PlayerStatsFlipCardViewTest extends TestCase
{
    use SnapshotTestTrait;

    public function testGetFlipStylesWithNullColorSchemeReturnsNonEmptyString(): void
    {
        $result = PlayerStatsFlipCardView::getFlipStyles(null);

        $this->assertNotEmpty($result);
    }

    public function testRenderWithShowAveragesFirstSnapshot(): void
    {
        $result = PlayerStatsFlipCardView::render('<AVG/>', '<TOT/>', 'Regular Season', true, null);

        // $averagesHtml/$totalsHtml flow through styleTable() then ob_start output,
        // not through HtmlSanitizer::e(), so sentinels appear literally in the output.
        $avgPos = strpos($result, '<AVG/>');
        $totPos = strpos($result, '<TOT/>');

        $this->assertNotFalse($avgPos, 'averages sentinel not found in output');
        $this->assertNotFalse($totPos, 'totals sentinel not found in output');
        $this->assertLessThan($totPos, $avgPos, 'averages face must precede totals face');
        $this->assertSnapshotMatches($result, 'PlayerStatsFlipCardView-averages-first.html');
    }

    public function testRenderWithShowTotalsFirstDiffersFromAveragesFirst(): void
    {
        $averagesFirst = PlayerStatsFlipCardView::render('<AVG/>', '<TOT/>', 'Regular Season', true, null);
        $totalsFirst   = PlayerStatsFlipCardView::render('<AVG/>', '<TOT/>', 'Regular Season', false, null);

        $this->assertNotSame($averagesFirst, $totalsFirst);
    }

    public function testRenderRegularSeasonSnapshot(): void
    {
        $averagesView = self::createStub(PlayerRegularSeasonAveragesView::class);
        $averagesView->method('renderAverages')->willReturn('<AVG/>');
        $totalsView = self::createStub(PlayerRegularSeasonTotalsView::class);
        $totalsView->method('renderTotals')->willReturn('<TOT/>');

        $result = PlayerStatsFlipCardView::renderRegularSeason($averagesView, $totalsView, 42, null, 0);

        $this->assertStringContainsString('<AVG/>', $result);
        $this->assertStringContainsString('<TOT/>', $result);
        $this->assertSnapshotMatches($result, 'PlayerStatsFlipCardView-regular-season.html');
    }

    public function testRenderPlayoffsSnapshot(): void
    {
        $averagesView = self::createStub(PlayerPlayoffAveragesView::class);
        $averagesView->method('renderAverages')->willReturn('<AVG/>');
        $totalsView = self::createStub(PlayerPlayoffTotalsView::class);
        $totalsView->method('renderTotals')->willReturn('<TOT/>');

        $result = PlayerStatsFlipCardView::renderPlayoffs($averagesView, $totalsView, 'Test Player', null, 0);

        $this->assertStringContainsString('<AVG/>', $result);
        $this->assertStringContainsString('<TOT/>', $result);
        $this->assertSnapshotMatches($result, 'PlayerStatsFlipCardView-playoffs.html');
    }

    public function testRenderOlympicsSnapshot(): void
    {
        $averagesView = self::createStub(PlayerOlympicAveragesView::class);
        $averagesView->method('renderAverages')->willReturn('<AVG/>');
        $totalsView = self::createStub(PlayerOlympicTotalsView::class);
        $totalsView->method('renderTotals')->willReturn('<TOT/>');

        $result = PlayerStatsFlipCardView::renderOlympics($averagesView, $totalsView, 42, null, 0);

        $this->assertStringContainsString('<AVG/>', $result);
        $this->assertStringContainsString('<TOT/>', $result);
        $this->assertSnapshotMatches($result, 'PlayerStatsFlipCardView-olympics.html');
    }

    public function testRenderHeatSnapshot(): void
    {
        $averagesView = self::createStub(PlayerHeatAveragesView::class);
        $averagesView->method('renderAverages')->willReturn('<AVG/>');
        $totalsView = self::createStub(PlayerHeatTotalsView::class);
        $totalsView->method('renderTotals')->willReturn('<TOT/>');

        $result = PlayerStatsFlipCardView::renderHeat($averagesView, $totalsView, 'Test Player', null, 0);

        $this->assertStringContainsString('<AVG/>', $result);
        $this->assertStringContainsString('<TOT/>', $result);
        $this->assertSnapshotMatches($result, 'PlayerStatsFlipCardView-heat.html');
    }
}
