<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use UI\Components\TableViewSwitcher;

/**
 * Tests for TableViewSwitcher component
 */
class TableViewSwitcherTest extends TestCase
{
    /** @var array<string, string> */
    private array $defaultTabs;

    protected function setUp(): void
    {
        $this->defaultTabs = [
            'ratings' => 'Ratings',
            'total_s' => 'Season Totals',
            'avg_s' => 'Season Averages',
        ];
    }

    public function testWrapInjectsCaptionAfterOpeningTableTag(): void
    {
        $switcher = new TableViewSwitcher(
            $this->defaultTabs,
            'ratings',
            'modules.php?name=Team&op=team&teamID=1',
            'FF0000',
            '0000FF'
        );

        $tableHtml = '<table class="ibl-data-table"><thead><tr><th>Test</th></tr></thead></table>';
        $result = $switcher->wrap($tableHtml);

        $this->assertStringContainsString('<caption class="team-table-caption">', $result);
        $this->assertStringContainsString('</caption>', $result);
        // Caption should appear after the opening <table> tag
        $this->assertMatchesRegularExpression('/<table[^>]*><caption/', $result);
    }

    public function testWrapOnlyInjectsIntoFirstTable(): void
    {
        $switcher = new TableViewSwitcher(
            $this->defaultTabs,
            'ratings',
            'modules.php?name=Team&op=team&teamID=1',
            'FF0000',
            '0000FF'
        );

        $tableHtml = '<table class="first"><tr><td>1</td></tr></table><table class="second"><tr><td>2</td></tr></table>';
        $result = $switcher->wrap($tableHtml);

        $this->assertSame(1, substr_count($result, '<caption'));
    }

    public function testActiveTabGetsActiveClass(): void
    {
        $switcher = new TableViewSwitcher(
            $this->defaultTabs,
            'total_s',
            'modules.php?name=Team&op=team&teamID=1',
            'FF0000',
            '0000FF'
        );

        $result = $switcher->renderTabs();

        // "Season Totals" tab should have active class
        $this->assertStringContainsString('class="ibl-tab ibl-tab--active">Season Totals</a>', $result);
        // Other tabs should not have active class
        $this->assertStringContainsString('class="ibl-tab">Ratings</a>', $result);
        $this->assertStringContainsString('class="ibl-tab">Season Averages</a>', $result);
    }

    public function testTabHrefsIncludeBaseUrlAndDisplayParam(): void
    {
        $switcher = new TableViewSwitcher(
            $this->defaultTabs,
            'ratings',
            'modules.php?name=Team&op=team&teamID=1',
            'FF0000',
            '0000FF'
        );

        $result = $switcher->renderTabs();

        $this->assertStringContainsString('modules.php?name=Team&amp;op=team&amp;teamID=1&amp;display=ratings', $result);
        $this->assertStringContainsString('modules.php?name=Team&amp;op=team&amp;teamID=1&amp;display=total_s', $result);
        $this->assertStringContainsString('modules.php?name=Team&amp;op=team&amp;teamID=1&amp;display=avg_s', $result);
    }

    public function testTeamColorsAppearAsCssCustomProperties(): void
    {
        $switcher = new TableViewSwitcher(
            $this->defaultTabs,
            'ratings',
            'modules.php?name=Team',
            'FF0000',
            '0000FF'
        );

        $result = $switcher->renderTabs();

        $this->assertStringContainsString('--team-tab-bg-color: #FF0000', $result);
        $this->assertStringContainsString('--team-tab-active-color: #0000FF', $result);
    }

    public function testLabelsAreXssEscaped(): void
    {
        $maliciousTabs = [
            'test' => '<script>alert("XSS")</script>',
        ];

        $switcher = new TableViewSwitcher(
            $maliciousTabs,
            'test',
            'modules.php?name=Team',
            'FF0000',
            '0000FF'
        );

        $result = $switcher->renderTabs();

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testInvalidHexColorsSanitizedToDefault(): void
    {
        $switcher = new TableViewSwitcher(
            $this->defaultTabs,
            'ratings',
            'modules.php?name=Team',
            'invalid<script>',
            'XYZ'
        );

        $result = $switcher->renderTabs();

        $this->assertStringContainsString('--team-tab-bg-color: #000000', $result);
        $this->assertStringContainsString('--team-tab-active-color: #000000', $result);
    }

    public function testBaseUrlWithAmpersandsIsHtmlEncoded(): void
    {
        $switcher = new TableViewSwitcher(
            ['ratings' => 'Ratings'],
            'ratings',
            'modules.php?name=Waivers&action=add',
            'FF0000',
            '0000FF'
        );

        $result = $switcher->renderTabs();

        // The & in the URL should be encoded as &amp;
        $this->assertStringContainsString('modules.php?name=Waivers&amp;action=add&amp;display=ratings', $result);
        // Should not contain unencoded & (except within &amp;)
        $this->assertStringNotContainsString('action=add&display', $result);
    }

    public function testRenderTabsWrapsInIblTabsDiv(): void
    {
        $switcher = new TableViewSwitcher(
            $this->defaultTabs,
            'ratings',
            'modules.php?name=Team',
            'FF0000',
            '0000FF'
        );

        $result = $switcher->renderTabs();

        $this->assertStringStartsWith('<div class="ibl-tabs"', $result);
        $this->assertStringEndsWith('</div>', $result);
    }

    public function testThreeCharacterHexColorsAccepted(): void
    {
        $switcher = new TableViewSwitcher(
            $this->defaultTabs,
            'ratings',
            'modules.php?name=Team',
            'F00',
            'FFF'
        );

        $result = $switcher->renderTabs();

        $this->assertStringContainsString('--team-tab-bg-color: #F00', $result);
        $this->assertStringContainsString('--team-tab-active-color: #FFF', $result);
    }

    public function testColorsWithHashPrefixHandled(): void
    {
        $switcher = new TableViewSwitcher(
            $this->defaultTabs,
            'ratings',
            'modules.php?name=Team',
            '#00FF00',
            '#FFFFFF'
        );

        $result = $switcher->renderTabs();

        $this->assertStringContainsString('--team-tab-bg-color: #00FF00', $result);
        $this->assertStringContainsString('--team-tab-active-color: #FFFFFF', $result);
    }

    public function testAllTabsRenderedInOrder(): void
    {
        $tabs = [
            'ratings' => 'Ratings',
            'total_s' => 'Season Totals',
            'avg_s' => 'Season Averages',
            'per36mins' => 'Per 36 Minutes',
            'contracts' => 'Contracts',
        ];

        $switcher = new TableViewSwitcher(
            $tabs,
            'ratings',
            'modules.php?name=Team',
            'FF0000',
            '0000FF'
        );

        $result = $switcher->renderTabs();

        // All tabs present
        $this->assertStringContainsString('Ratings</a>', $result);
        $this->assertStringContainsString('Season Totals</a>', $result);
        $this->assertStringContainsString('Season Averages</a>', $result);
        $this->assertStringContainsString('Per 36 Minutes</a>', $result);
        $this->assertStringContainsString('Contracts</a>', $result);

        // Verify order: Ratings before Season Totals before Season Averages etc.
        $posRatings = strpos($result, 'Ratings</a>');
        $posTotals = strpos($result, 'Season Totals</a>');
        $posAverages = strpos($result, 'Season Averages</a>');
        $posPer36 = strpos($result, 'Per 36 Minutes</a>');
        $posContracts = strpos($result, 'Contracts</a>');

        $this->assertLessThan($posTotals, $posRatings);
        $this->assertLessThan($posAverages, $posTotals);
        $this->assertLessThan($posPer36, $posAverages);
        $this->assertLessThan($posContracts, $posPer36);
    }
}
