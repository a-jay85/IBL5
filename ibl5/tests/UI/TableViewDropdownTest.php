<?php

declare(strict_types=1);

namespace Tests\UI;

use PHPUnit\Framework\TestCase;
use UI\Components\TableViewDropdown;

/**
 * @covers \UI\Components\TableViewDropdown
 */
class TableViewDropdownTest extends TestCase
{
    // --- renderDropdown() ---

    public function testRenderDropdownContainsSelectElement(): void
    {
        $dropdown = $this->createDropdown();
        $html = $dropdown->renderDropdown();

        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('</select>', $html);
        $this->assertStringContainsString('aria-label="Stats display"', $html);
    }

    public function testRenderDropdownActiveValueGetsSelectedAttribute(): void
    {
        $dropdown = $this->createDropdown('ratings');
        $html = $dropdown->renderDropdown();

        $this->assertStringContainsString('value="ratings" selected', $html);
    }

    public function testRenderDropdownNonActiveValueDoesNotGetSelected(): void
    {
        $dropdown = $this->createDropdown('ratings');
        $html = $dropdown->renderDropdown();

        // "total_s" should NOT have selected
        $this->assertStringNotContainsString('value="total_s" selected', $html);
    }

    public function testRenderDropdownRendersOptgroups(): void
    {
        $dropdown = $this->createDropdown();
        $html = $dropdown->renderDropdown();

        $this->assertStringContainsString('<optgroup label="Views">', $html);
        $this->assertStringContainsString('</optgroup>', $html);
    }

    public function testRenderDropdownEscapesOptgroupLabels(): void
    {
        $groups = [
            'Views & Stats' => ['ratings' => 'Ratings'],
        ];
        $dropdown = new TableViewDropdown($groups, 'ratings', '/team.php?id=1', 'FF0000', '0000FF');
        $html = $dropdown->renderDropdown();

        $this->assertStringContainsString('label="Views &amp; Stats"', $html);
    }

    public function testRenderDropdownEscapesOptionValues(): void
    {
        $groups = [
            'Views' => ['val"ue' => 'Label'],
        ];
        $dropdown = new TableViewDropdown($groups, 'other', '/test', 'FF0000', '0000FF');
        $html = $dropdown->renderDropdown();

        $this->assertStringContainsString('value="val&quot;ue"', $html);
    }

    public function testRenderDropdownAppliesTeamColors(): void
    {
        $dropdown = $this->createDropdown('ratings', '1a2e5a', 'ffffff');
        $html = $dropdown->renderDropdown();

        $this->assertStringContainsString('--team-tab-bg-color: #1a2e5a', $html);
        $this->assertStringContainsString('--team-tab-active-color: #ffffff', $html);
    }

    public function testRenderDropdownSplitValueEncodedCorrectly(): void
    {
        $groups = [
            'Split' => ['split:home' => 'Home Games', 'split:away' => 'Away Games'],
        ];
        $dropdown = new TableViewDropdown($groups, 'split:home', '/test', 'FF0000', '0000FF');
        $html = $dropdown->renderDropdown();

        $this->assertStringContainsString('value="split:home" selected', $html);
        $this->assertStringContainsString('value="split:away"', $html);
    }

    public function testRenderDropdownIncludesNoscriptFallback(): void
    {
        $dropdown = $this->createDropdown();
        $html = $dropdown->renderDropdown();

        $this->assertStringContainsString('<noscript>', $html);
        $this->assertStringContainsString('</noscript>', $html);
        $this->assertStringContainsString('Back to Ratings', $html);
    }

    // --- wrap() ---

    public function testWrapInjectsDropdownAsCaptionInTable(): void
    {
        $dropdown = $this->createDropdown();
        $tableHtml = '<table class="ibl-data-table"><thead><tr><th>Test</th></tr></thead></table>';

        $result = $dropdown->wrap($tableHtml);

        $this->assertStringContainsString('<caption class="team-table-caption">', $result);
        $this->assertStringContainsString('<select', $result);
        $this->assertStringContainsString('</table>', $result);
    }

    public function testWrapReturnsOriginalHtmlWhenNoTableTag(): void
    {
        $dropdown = $this->createDropdown();
        $noTableHtml = '<div>No table here</div>';

        $result = $dropdown->wrap($noTableHtml);

        $this->assertSame($noTableHtml, $result);
    }

    public function testWrapInjectsIntoFirstTableOnly(): void
    {
        $dropdown = $this->createDropdown();
        $twoTables = '<table class="first"><tr><td>1</td></tr></table><table class="second"><tr><td>2</td></tr></table>';

        $result = $dropdown->wrap($twoTables);

        // Caption should appear after first <table> only
        $captionCount = substr_count($result, '<caption');
        $this->assertSame(1, $captionCount);
    }

    // --- HTMX attributes ---

    public function testWithoutHtmxGetUrlNoHtmxAttributesRendered(): void
    {
        $dropdown = $this->createDropdown();
        $html = $dropdown->renderDropdown();

        $this->assertStringNotContainsString('hx-get', $html);
        $this->assertStringNotContainsString('hx-target', $html);
        $this->assertStringNotContainsString('hx-swap', $html);
        $this->assertStringNotContainsString('hx-trigger', $html);
    }

    public function testWithHtmxGetUrlRendersHtmxAttributes(): void
    {
        $dropdown = $this->createDropdownWithHtmx();
        $html = $dropdown->renderDropdown();

        $this->assertStringContainsString('hx-get="modules.php?name=Team&amp;op=api&amp;teamID=1"', $html);
        $this->assertStringContainsString('hx-target="closest .table-scroll-container"', $html);
        $this->assertStringContainsString('hx-swap="innerHTML"', $html);
        $this->assertStringContainsString('hx-trigger="change"', $html);
    }

    public function testOnchangeFallbackChecksWindowHtmx(): void
    {
        $dropdown = $this->createDropdownWithHtmx();
        $html = $dropdown->renderDropdown();

        $this->assertStringContainsString('if(window.htmx)return;', $html);
        $this->assertStringNotContainsString('IBL_AJAX_TABS_READY', $html);
    }

    public function testWithoutHtmxOnchangeAlsoChecksWindowHtmx(): void
    {
        $dropdown = $this->createDropdown();
        $html = $dropdown->renderDropdown();

        $this->assertStringContainsString('if(window.htmx)return;', $html);
    }

    public function testHtmxDoesNotIncludePushUrlOnDropdown(): void
    {
        $dropdown = $this->createDropdownWithHtmx();
        $html = $dropdown->renderDropdown();

        $this->assertStringNotContainsString('hx-push-url', $html);
    }

    // --- Helper ---

    private function createDropdown(
        string $activeValue = 'ratings',
        string $color1 = 'FF0000',
        string $color2 = '0000FF',
    ): TableViewDropdown {
        $groups = [
            'Views' => [
                'ratings' => 'Ratings',
                'total_s' => 'Season Totals',
                'avg_s' => 'Season Averages',
            ],
        ];
        return new TableViewDropdown($groups, $activeValue, '/team.php?id=1', $color1, $color2);
    }

    private function createDropdownWithHtmx(
        string $activeValue = 'ratings',
    ): TableViewDropdown {
        $groups = [
            'Views' => [
                'ratings' => 'Ratings',
                'total_s' => 'Season Totals',
            ],
        ];
        return new TableViewDropdown(
            $groups,
            $activeValue,
            '/team.php?id=1',
            'FF0000',
            '0000FF',
            'modules.php?name=Team&op=api&teamID=1'
        );
    }
}
