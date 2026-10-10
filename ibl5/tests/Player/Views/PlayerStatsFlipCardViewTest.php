<?php

declare(strict_types=1);

namespace Tests\Player\Views;

use PHPUnit\Framework\TestCase;
use Player\Views\PlayerStatsFlipCardView;

/** @covers \Player\Views\PlayerStatsFlipCardView */
class PlayerStatsFlipCardViewTest extends TestCase
{
    use SnapshotTestTrait;

    public function testGetFlipStylesWithNullColorSchemeReturnsNonEmptyString(): void
    {
        $result = PlayerStatsFlipCardView::getFlipStyles(null);

        $this->assertNotSame('', $result);
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
}
