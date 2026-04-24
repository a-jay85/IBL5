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
            'stamina' => -5,
            'dc_pg_depth' => 1,
            'dc_sg_depth' => 0,
            'dc_sf_depth' => 0,
            'dc_pf_depth' => 0,
            'dc_c_depth' => 0,
            'dc_can_play_in_game' => 1,
            'dc_minutes' => 0,
            'dc_of' => 0,
            'dc_df' => 0,
            'dc_oi' => 0,
            'dc_di' => 0,
            'dc_bh' => 0,
            'quality_score' => 0.0,
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
        $this->assertStringContainsString('<th>Active</th>', $output);
        $this->assertStringContainsString('<th>PG</th>', $output);
        $this->assertStringContainsString('<th>SG</th>', $output);
        $this->assertStringContainsString('<th>SF</th>', $output);
        $this->assertStringContainsString('<th>PF</th>', $output);
        $this->assertStringContainsString('<th>C</th>', $output);
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
        // Role fields (OF, DF, OI, DI, BH) are no longer rendered
        $this->assertStringNotContainsString('name="OF1"', $output);
        $this->assertStringNotContainsString('name="DF1"', $output);
        $this->assertStringNotContainsString('name="OI1"', $output);
        $this->assertStringNotContainsString('name="DI1"', $output);
        $this->assertStringNotContainsString('name="BH1"', $output);
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

    public function testRenderPlayerRowMinutesIsNumberInput(): void
    {
        $player = $this->buildTestPlayer();
        $player['dc_minutes'] = 30;

        ob_start();
        $this->view->renderPlayerRow($player, 1);
        $output = (string) ob_get_clean();

        // Minutes is rendered as a <input type="number"> with a native stepper,
        // constrained to 0-40 (matching DepthChartEntryProcessor::sanitizeMinutesValue).
        // The server sanitizer clamps the submitted value to the same range.
        $this->assertMatchesRegularExpression(
            '/<input type="number" name="min1"[^>]*value="30"[^>]*min="0"[^>]*max="40"[^>]*step="1"/',
            $output
        );
        // No <select> markup for the minutes field
        $this->assertDoesNotMatchRegularExpression('/<select[^>]*name="min1"/', $output);
    }

    public function testRenderPlayerRowActiveIsCheckbox(): void
    {
        $player = $this->buildTestPlayer();
        $player['dc_can_play_in_game'] = 1;

        ob_start();
        $this->view->renderPlayerRow($player, 1);
        $output = (string) ob_get_clean();

        // Active is rendered as a checkbox + sibling hidden input so the form
        // submits "0" when unchecked and "1" when checked.
        $this->assertMatchesRegularExpression(
            '/<input type="hidden" name="canPlayInGame1" value="0"/',
            $output
        );
        $this->assertMatchesRegularExpression(
            '/<input type="checkbox" name="canPlayInGame1" value="1"[^>]*class="dc-active-cb"[^>]*checked/',
            $output
        );
        // No <select> markup for the active field
        $this->assertDoesNotMatchRegularExpression('/<select[^>]*name="canPlayInGame1"/', $output);
    }

    public function testRenderPlayerRowActiveCheckboxNotCheckedWhenInactive(): void
    {
        $player = $this->buildTestPlayer();
        $player['dc_can_play_in_game'] = 0;

        ob_start();
        $this->view->renderPlayerRow($player, 1);
        $output = (string) ob_get_clean();

        // The checkbox should NOT have the "checked" attribute when inactive
        $this->assertDoesNotMatchRegularExpression(
            '/<input type="checkbox" name="canPlayInGame1"[^>]*checked/',
            $output
        );
    }

    public function testRenderPlayerRowEmitsJsbProductionAttribute(): void
    {
        // The data-jsb-production attribute exposes the inner sum of
        // FUN_0040af90's roster sort formula (verified at
        // jsb560_decompiled.c:5723-5728), pre-computed PHP-side from ibl_plr
        // stat columns. The lineup-preview JS reads this attribute and
        // applies the (dc_minutes + 100) × production multiplier to compute
        // the live JSB roster ordering used by FUN_004db520's bench-scan
        // fallback.
        $player = $this->buildTestPlayer();
        $player['stats_fgm'] = 100;   // total FGM (= 2pt + 3pt)
        $player['stats_3gm'] = 20;    // 3pt FGM
        $player['stats_ftm'] = 50;
        $player['stats_orb'] = 10;
        $player['stats_drb'] = 30;
        $player['stats_ast'] = 40;
        $player['stats_stl'] = 15;
        $player['stats_blk'] = 5;

        ob_start();
        $this->view->renderPlayerRow($player, 1);
        $output = (string) ob_get_clean();

        // Expected production = 2 × stats_fgm + stats_3gm + stats_ftm
        //                     + stats_orb + stats_drb + stats_ast
        //                     + stats_stl + stats_blk
        //                     = 200 + 20 + 50 + 10 + 30 + 40 + 15 + 5
        //                     = 370
        $this->assertStringContainsString('data-jsb-production="370"', $output);
    }

    public function testRenderPlayerRowJsbProductionDefaultsToZero(): void
    {
        // When stat fields are missing from the player array, computeJsbProduction
        // should default each one to 0 and emit data-jsb-production="0".
        $player = $this->buildTestPlayer();
        // (intentionally do not set any stats_* fields)

        ob_start();
        $this->view->renderPlayerRow($player, 1);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('data-jsb-production="0"', $output);
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
        // Reset handles all three field types: selects (role slots), number
        // inputs (minutes), and checkboxes (canPlayInGame).
        $this->assertStringContainsString('getElementsByTagName(\'select\')', $output);
        $this->assertStringContainsString('input[type="number"][name^="min"]', $output);
        $this->assertStringContainsString('input[type="checkbox"][name^="canPlayInGame"]', $output);
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

        // All selects should have the disabled attribute
        $selectCount = preg_match_all('/<select\b/', $output);
        $disabledSelectCount = preg_match_all('/<select[^>]+disabled/', $output);
        $this->assertGreaterThan(0, $selectCount);
        $this->assertSame($selectCount, $disabledSelectCount, 'All selects must have disabled attribute');

        // All hidden/checkbox/number inputs should have the disabled attribute
        // (excludes footer button/submit inputs which should NOT be disabled)
        $formInputCount = preg_match_all('/<input\s+type="(hidden|checkbox|number)"/', $output);
        $disabledFormInputCount = preg_match_all('/<input\s+type="(hidden|checkbox|number)"[^>]+disabled/', $output);
        $this->assertGreaterThan(0, $formInputCount);
        $this->assertSame($formInputCount, $disabledFormInputCount, 'All form inputs must have disabled attribute');
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
        // Role fields (OF, DF, OI, DI, BH) are no longer rendered
        $this->assertStringNotContainsString('name="OF1"', $output);
        $this->assertStringNotContainsString('name="DF1"', $output);
        $this->assertStringNotContainsString('name="OI1"', $output);
        $this->assertStringNotContainsString('name="DI1"', $output);
        $this->assertStringNotContainsString('name="BH1"', $output);
    }

    public function testRenderMobileViewActiveCheckboxChecked(): void
    {
        $player = $this->buildTestPlayer();
        $player['dc_can_play_in_game'] = 1;

        ob_start();
        $this->view->renderMobileView([$player], ['PG', 'SG', 'SF', 'PF', 'C']);
        $output = (string) ob_get_clean();

        $this->assertMatchesRegularExpression('/dc-card__active-cb[^>]*checked/', $output);
    }

    public function testRenderMobileViewActiveCheckboxUnchecked(): void
    {
        $player = $this->buildTestPlayer();
        $player['dc_can_play_in_game'] = 0;

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

        // Single settings grid for 5 role slots (no separate pos-grid or divider)
        $this->assertStringContainsString('dc-card__settings-grid', $output);
        $this->assertStringNotContainsString('dc-card__pos-grid', $output);
        $this->assertStringNotContainsString('dc-card__divider', $output);
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

    public function testRenderMobileViewRendersStepperForEachRoleSlot(): void
    {
        // Two players × six steppers (five role slots + Min) = twelve pairs.
        $players = [$this->buildTestPlayer(1, 'Alice'), $this->buildTestPlayer(2, 'Bob')];

        ob_start();
        $this->view->renderMobileView($players, ['PG', 'SG', 'SF', 'PF', 'C']);
        $output = (string) ob_get_clean();

        $upCount = preg_match_all('/dc-card__stepper-arrow--up/', $output);
        $downCount = preg_match_all('/dc-card__stepper-arrow--down/', $output);

        $this->assertSame(12, $upCount, 'One up arrow per player per role slot and Min');
        $this->assertSame(12, $downCount, 'One down arrow per player per role slot and Min');
    }

    public function testRenderMobileViewStepperInitialLabelMatchesDcValue(): void
    {
        $player = $this->buildTestPlayer();
        $player['dc_pg_depth'] = 1; // PG depth → starter label "1st"
        $player['dc_sf_depth'] = 2; // SF depth → backup label "2nd"

        ob_start();
        $this->view->renderMobileView([$player], ['PG', 'SG', 'SF', 'PF', 'C']);
        $output = (string) ob_get_clean();

        // Initial labels are rendered server-side so stepper values are
        // correct before any JS runs. "No" is the unassigned label.
        $this->assertMatchesRegularExpression(
            '/dc-card__stepper-value[^>]*>1st</',
            $output,
            'PG slot with depth=1 should render initial label "1st"'
        );
        $this->assertMatchesRegularExpression(
            '/dc-card__stepper-value[^>]*>2nd</',
            $output,
            'SF slot with depth=2 should render initial label "2nd"'
        );
        $this->assertMatchesRegularExpression(
            '/dc-card__stepper-value[^>]*>No</',
            $output,
            'Unassigned slots should render the "No" label'
        );
    }

    public function testRenderMobileViewStepperButtonsHaveAriaLabels(): void
    {
        $player = $this->buildTestPlayer(99, 'Unique Player');

        ob_start();
        $this->view->renderMobileView([$player], ['PG', 'SG', 'SF', 'PF', 'C']);
        $output = (string) ob_get_clean();

        // aria-labels include direction + position + player name so screen
        // readers announce the action and its target.
        $this->assertStringContainsString('aria-label="Previous PG depth for Unique Player"', $output);
        $this->assertStringContainsString('aria-label="Next PG depth for Unique Player"', $output);
        $this->assertStringContainsString('aria-label="Previous C depth for Unique Player"', $output);
        $this->assertStringContainsString('aria-label="Next C depth for Unique Player"', $output);
    }

    public function testRenderMobileViewStepperButtonsAreTypeButton(): void
    {
        $players = [$this->buildTestPlayer()];

        ob_start();
        $this->view->renderMobileView($players, ['PG', 'SG', 'SF', 'PF', 'C']);
        $output = (string) ob_get_clean();

        // Every stepper arrow must be type="button" so tapping one never
        // submits the depth-chart form.
        $arrowMatches = preg_match_all('/<button[^>]*class="[^"]*dc-card__stepper-arrow[^"]*"[^>]*>/', $output);
        $typeButtonArrowMatches = preg_match_all(
            '/<button[^>]*type="button"[^>]*class="[^"]*dc-card__stepper-arrow[^"]*"[^>]*>/',
            $output
        );

        $this->assertGreaterThan(0, $arrowMatches);
        $this->assertSame($arrowMatches, $typeButtonArrowMatches);
    }

    public function testPositionDepthSelectsShowCorrectOptionLabels(): void
    {
        $player = $this->buildTestPlayer();
        $player['dc_sf_depth'] = 3;

        ob_start();
        $this->view->renderPlayerRow($player, 1);
        $output = (string) ob_get_clean();

        $expectedLabels = ['No', '1st', '2nd', '3rd', '4th', 'ok'];

        foreach (['pg', 'sg', 'sf', 'pf', 'c'] as $field) {
            preg_match('/<select name="' . $field . '1"[^>]*>(.*?)<\/select>/s', $output, $matches);
            $this->assertNotEmpty($matches, "Position depth select for {$field} not found");
            $selectHtml = $matches[1];

            foreach ($expectedLabels as $i => $label) {
                $this->assertMatchesRegularExpression(
                    '/value="' . $i . '"[^>]*>' . preg_quote($label, '/') . '<\/option>/',
                    $selectHtml,
                    "Position {$field}: option {$i} should have label '{$label}'"
                );
            }
        }
    }

    public function testPositionDepthSelectsHaveSixOptions(): void
    {
        $player = $this->buildTestPlayer();

        ob_start();
        $this->view->renderPlayerRow($player, 1);
        $output = (string) ob_get_clean();

        preg_match('/<select name="pg1"[^>]*>(.*?)<\/select>/s', $output, $matches);
        $this->assertNotEmpty($matches);
        $optionCount = substr_count($matches[1], '<option');
        $this->assertSame(6, $optionCount, 'Position depth should have exactly 6 options (No/1st/2nd/3rd/4th/ok)');
    }

    public function testPositionDepthSelectPreselectsCorrectValue(): void
    {
        $player = $this->buildTestPlayer();
        $player['dc_sf_depth'] = 1;
        $player['dc_pg_depth'] = 0;

        ob_start();
        $this->view->renderPlayerRow($player, 1);
        $output = (string) ob_get_clean();

        preg_match('/<select name="sf1"[^>]*>(.*?)<\/select>/s', $output, $matches);
        $this->assertNotEmpty($matches);
        $this->assertMatchesRegularExpression('/value="1" SELECTED[^>]*>1st<\/option>/', $matches[1]);

        preg_match('/<select name="pg1"[^>]*>(.*?)<\/select>/s', $output, $matches);
        $this->assertNotEmpty($matches);
        $this->assertMatchesRegularExpression('/value="0" SELECTED[^>]*>No<\/option>/', $matches[1]);
    }
}
