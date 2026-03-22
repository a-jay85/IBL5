<?php

declare(strict_types=1);

namespace Tests\DepthChartEntry;

use PHPUnit\Framework\TestCase;
use DepthChartEntry\DepthChartEntryView;

/**
 * Tests for DepthChartEntryView
 */
class DepthChartEntryViewTest extends TestCase
{
    private DepthChartEntryView $view;

    protected function setUp(): void
    {
        $this->view = new DepthChartEntryView();
    }

    /**
     * Build a minimal player array matching the PlayerRow type shape
     * with depth chart fields for testing.
     *
     * @return array<string, mixed>
     */
    private function buildTestPlayer(int $pid = 12345, string $name = 'Test Player', string $pos = 'PG'): array
    {
        return [
            'pid' => $pid,
            'name' => $name,
            'pos' => $pos,
            'injured' => 0,
            'sta' => -5,
            'dc_PGDepth' => 1,
            'dc_SGDepth' => 0,
            'dc_SFDepth' => 0,
            'dc_PFDepth' => 0,
            'dc_CDepth' => 0,
            'dc_canPlayInGame' => 1,
            'dc_minutes' => 0,
            'dc_of' => 0,
            'dc_df' => 0,
            'dc_oi' => 0,
            'dc_di' => 0,
            'dc_bh' => 0,
        ];
    }

    // =====================================================================
    // Characterization tests — lock existing behavior before changes
    // =====================================================================

    public function testRenderFormHeaderOutputsFormAndTable(): void
    {
        ob_start();
        $this->view->renderFormHeader('Test Team', 1, ['PG', 'SG', 'SF', 'PF', 'C']);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('<form name="DepthChartEntry"', $output);
        $this->assertStringContainsString('method="post"', $output);
        $this->assertStringContainsString('modules.php?name=DepthChartEntry&amp;op=submit', $output);
        $this->assertStringContainsString('data-no-responsive', $output);
        $this->assertStringContainsString('depth-chart-table', $output);
        $this->assertStringContainsString('<th>Pos</th>', $output);
        $this->assertStringContainsString('<th>Player</th>', $output);
        $this->assertStringContainsString('<th>PG</th>', $output);
        $this->assertStringContainsString('<th>C</th>', $output);
        $this->assertStringContainsString('<th>active?</th>', $output);
        $this->assertStringContainsString('<th>min</th>', $output);
        $this->assertStringContainsString('<th>BH</th>', $output);
    }

    public function testRenderPlayerRowOutputsCorrectFieldNames(): void
    {
        $player = $this->buildTestPlayer();

        ob_start();
        $this->view->renderPlayerRow($player, 1);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('name="pg1"', $output);
        $this->assertStringContainsString('name="sg1"', $output);
        $this->assertStringContainsString('name="sf1"', $output);
        $this->assertStringContainsString('name="pf1"', $output);
        $this->assertStringContainsString('name="c1"', $output);
        $this->assertStringContainsString('name="canPlayInGame1"', $output);
        $this->assertStringContainsString('name="min1"', $output);
        $this->assertStringContainsString('name="OF1"', $output);
        $this->assertStringContainsString('name="DF1"', $output);
        $this->assertStringContainsString('name="OI1"', $output);
        $this->assertStringContainsString('name="DI1"', $output);
        $this->assertStringContainsString('name="BH1"', $output);
    }

    public function testRenderPlayerRowContainsHiddenFields(): void
    {
        $player = $this->buildTestPlayer(12345, 'Test Player');

        ob_start();
        $this->view->renderPlayerRow($player, 1);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('name="pid1"', $output);
        $this->assertStringContainsString('value="12345"', $output);
        $this->assertStringContainsString('name="Injury1"', $output);
        $this->assertStringContainsString('name="Name1"', $output);
        $this->assertStringContainsString('data-pid="12345"', $output);
    }

    public function testRenderPlayerRowEscapesPlayerName(): void
    {
        $player = $this->buildTestPlayer(99999, '<script>alert(1)</script>');

        ob_start();
        $this->view->renderPlayerRow($player, 1);
        $output = (string) ob_get_clean();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    public function testRenderPlayerRowStaminaCapAt40(): void
    {
        // sta=5 → staminaCap = 5 + 40 = 45, clamped to 40
        $player = $this->buildTestPlayer();
        $player['sta'] = 5;

        ob_start();
        $this->view->renderPlayerRow($player, 1);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('value="40"', $output);
        $this->assertStringNotContainsString('value="41"', $output);
        $this->assertStringNotContainsString('value="45"', $output);
    }

    // =====================================================================
    // Existing renderFormFooter tests
    // =====================================================================

    public function testRenderFormFooterIncludesResetButton(): void
    {
        ob_start();
        $this->view->renderFormFooter();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('value="Reset"', $output);
        $this->assertStringContainsString('onclick="resetDepthChart();"', $output);
        $this->assertStringContainsString('value="Submit Depth Chart"', $output);
        $this->assertStringContainsString('depth-chart-reset-btn', $output);
        $this->assertStringContainsString('depth-chart-submit-btn', $output);
    }

    public function testRenderFormFooterIncludesResetScript(): void
    {
        ob_start();
        $this->view->renderFormFooter();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('function resetDepthChart()', $output);
        $this->assertStringContainsString('document.forms[\'DepthChartEntry\']', $output);
        $this->assertStringContainsString('confirm(', $output);
        $this->assertStringContainsString('canPlayInGame', $output);
        $this->assertStringContainsString('pg|sg|sf|pf|c', $output);
        $this->assertStringContainsString('min|OF|DF', $output);
        $this->assertStringContainsString('OI|DI|BH', $output);
    }

    public function testResetButtonIsButtonType(): void
    {
        ob_start();
        $this->view->renderFormFooter();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('type="button" value="Reset"', $output);
    }

    public function testRadioButtonNotPresent(): void
    {
        ob_start();
        $this->view->renderFormFooter();
        $output = (string) ob_get_clean();

        $this->assertStringNotContainsString('Submit Depth Chart?', $output);
        $this->assertStringNotContainsString('type="radio"', $output);
    }

    public function testRenderFormFooterClosesTableButNotForm(): void
    {
        ob_start();
        $this->view->renderFormFooter();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('</tbody>', $output);
        $this->assertStringContainsString('</table>', $output);
        // Form is now closed by renderMobileView() instead
        $this->assertStringNotContainsString('</form>', $output);
    }

    // =====================================================================
    // Mobile view tests
    // =====================================================================

    public function testRenderMobileViewContainsCardContainer(): void
    {
        $players = [$this->buildTestPlayer()];

        ob_start();
        $this->view->renderMobileView($players, ['PG', 'SG', 'SF', 'PF', 'C']);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('dc-mobile-cards', $output);
        $this->assertStringContainsString('</form>', $output);
    }

    public function testRenderMobileViewCardHasPhoto(): void
    {
        $players = [$this->buildTestPlayer(12345)];

        ob_start();
        $this->view->renderMobileView($players, ['PG', 'SG', 'SF', 'PF', 'C']);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('dc-card__photo', $output);
        $this->assertStringContainsString('images/player/', $output);
    }

    public function testRenderMobileViewAllInputsDisabled(): void
    {
        $players = [$this->buildTestPlayer()];

        ob_start();
        $this->view->renderMobileView($players, ['PG', 'SG', 'SF', 'PF', 'C']);
        $output = (string) ob_get_clean();

        // Count selects — all should have disabled attribute
        $selectCount = substr_count($output, '<select');
        $disabledSelectCount = substr_count($output, '<select ') - substr_count($output, '<select ') + preg_match_all('/select[^>]*disabled/', $output);
        $this->assertGreaterThan(0, $selectCount);

        // Every <select should have disabled
        $this->assertStringNotContainsString('<select name="pg1" aria-label', $output);
        $this->assertStringContainsString('disabled', $output);
    }

    public function testRenderMobileViewFieldNamesMatchDesktop(): void
    {
        $players = [$this->buildTestPlayer()];

        ob_start();
        $this->view->renderMobileView($players, ['PG', 'SG', 'SF', 'PF', 'C']);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('name="pg1"', $output);
        $this->assertStringContainsString('name="sg1"', $output);
        $this->assertStringContainsString('name="sf1"', $output);
        $this->assertStringContainsString('name="pf1"', $output);
        $this->assertStringContainsString('name="c1"', $output);
        $this->assertStringContainsString('name="canPlayInGame1"', $output);
        $this->assertStringContainsString('name="min1"', $output);
        $this->assertStringContainsString('name="OF1"', $output);
        $this->assertStringContainsString('name="DF1"', $output);
        $this->assertStringContainsString('name="OI1"', $output);
        $this->assertStringContainsString('name="DI1"', $output);
        $this->assertStringContainsString('name="BH1"', $output);
    }

    public function testRenderMobileViewActiveCheckboxChecked(): void
    {
        $player = $this->buildTestPlayer();
        $player['dc_canPlayInGame'] = 1;

        ob_start();
        $this->view->renderMobileView([$player], ['PG', 'SG', 'SF', 'PF', 'C']);
        $output = (string) ob_get_clean();

        $this->assertMatchesRegularExpression('/dc-card__active-cb[^>]*checked/', $output);
    }

    public function testRenderMobileViewActiveCheckboxUnchecked(): void
    {
        $player = $this->buildTestPlayer();
        $player['dc_canPlayInGame'] = 0;

        ob_start();
        $this->view->renderMobileView([$player], ['PG', 'SG', 'SF', 'PF', 'C']);
        $output = (string) ob_get_clean();

        $this->assertDoesNotMatchRegularExpression('/dc-card__active-cb[^>]*checked/', $output);
    }

    public function testRenderMobileViewEscapesNames(): void
    {
        $player = $this->buildTestPlayer(99999, '<script>alert(1)</script>');

        ob_start();
        $this->view->renderMobileView([$player], ['PG', 'SG', 'SF', 'PF', 'C']);
        $output = (string) ob_get_clean();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    public function testRenderMobileViewHasGridContainers(): void
    {
        $players = [$this->buildTestPlayer()];

        ob_start();
        $this->view->renderMobileView($players, ['PG', 'SG', 'SF', 'PF', 'C']);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('dc-card__pos-grid', $output);
        $this->assertStringContainsString('dc-card__settings-grid', $output);
        $this->assertStringContainsString('dc-card__divider', $output);
    }

    public function testRenderMobileViewHasSubmitAndResetButtons(): void
    {
        $players = [$this->buildTestPlayer()];

        ob_start();
        $this->view->renderMobileView($players, ['PG', 'SG', 'SF', 'PF', 'C']);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('dc-mobile-cards__footer', $output);
        $this->assertStringContainsString('depth-chart-submit-btn', $output);
        $this->assertStringContainsString('depth-chart-reset-btn', $output);
    }
}
