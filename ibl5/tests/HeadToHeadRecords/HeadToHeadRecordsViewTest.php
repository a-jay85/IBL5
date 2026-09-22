<?php

declare(strict_types=1);

namespace Tests\HeadToHeadRecords;

use HeadToHeadRecords\HeadToHeadRecordsView;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HeadToHeadRecords\HeadToHeadRecordsView
 */
class HeadToHeadRecordsViewTest extends TestCase
{
    private HeadToHeadRecordsView $view;

    protected function setUp(): void
    {
        $this->view = new HeadToHeadRecordsView();
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $overrides
     * @return array{key: string, franchise_id: int, label: string, sublabel: string, color1: string, color2: string, logo: string, link_franchise_id: int}
     */
    private function makeEntry(array $overrides = []): array
    {
        return [
            'key'               => (string) ($overrides['key']               ?? 'celtics'),
            'franchise_id'      => (int)    ($overrides['franchise_id']      ?? 1),
            'label'             => (string) ($overrides['label']             ?? 'Celtics'),
            'sublabel'          => (string) ($overrides['sublabel']          ?? '2020-present'),
            'color1'            => (string) ($overrides['color1']            ?? '00653A'),
            'color2'            => (string) ($overrides['color2']            ?? 'FFFFFF'),
            'logo'              => (string) ($overrides['logo']              ?? 'celtics.png'),
            'link_franchise_id' => (int)    ($overrides['link_franchise_id'] ?? 0),
        ];
    }

    /**
     * @param list<array{key: string, franchise_id: int, label: string, sublabel: string, color1: string, color2: string, logo: string, link_franchise_id: int}> $axis
     * @param array<string, array<string, array{wins: int, losses: int}>> $records
     * @return array{dimension: string, phase: string, scope: string, axis: list<array{key: string, franchise_id: int, label: string, sublabel: string, color1: string, color2: string, logo: string, link_franchise_id: int}>, records: array<string, array<string, array{wins: int, losses: int}>>}
     */
    private function makePayload(array $axis = [], array $records = [], string $dimension = 'franchises'): array
    {
        return [
            'dimension' => $dimension,
            'phase'     => 'regular',
            'scope'     => 'current',
            'axis'      => $axis,
            'records'   => $records,
        ];
    }

    /**
     * A two-entry payload where the first entry has one game so it stays visible.
     *
     * @param array<string, mixed> $firstOverrides
     * @return array{dimension: string, phase: string, scope: string, axis: list<array{key: string, franchise_id: int, label: string, sublabel: string, color1: string, color2: string, logo: string, link_franchise_id: int}>, records: array<string, array<string, array{wins: int, losses: int}>>}
     */
    private function makePlayedPayload(array $firstOverrides = [], string $dimension = 'franchises'): array
    {
        $a = $this->makeEntry($firstOverrides);
        $b = $this->makeEntry(['key' => 'lakers', 'franchise_id' => 2, 'label' => 'Lakers', 'logo' => 'lakers.png']);

        return $this->makePayload(
            [$a, $b],
            [
                $a['key'] => ['lakers' => ['wins' => 1, 'losses' => 0]],
                'lakers'  => [$a['key'] => ['wins' => 0, 'losses' => 1]],
            ],
            $dimension,
        );
    }

    // ---------------------------------------------------------------------------
    // Row label tests
    // ---------------------------------------------------------------------------

    public function testRowLabelRendersTeamCellColorCustomProperties(): void
    {
        $payload = $this->makePlayedPayload(['color1' => '00653A', 'color2' => 'FFFFFF']);
        $html    = $this->view->renderMatrix($payload, []);

        self::assertStringContainsString('--team-cell-bg: #00653A', $html);
        self::assertStringContainsString('--team-cell-color: #FFFFFF', $html);
        self::assertStringContainsString('class="sticky-col h2h-row-label ibl-team-cell--colored"', $html);
    }

    public function testRowLabelWithoutColorsHasNoStyleAttributeAndNoColoredModifier(): void
    {
        $payload = $this->makePlayedPayload(['color1' => '', 'color2' => '']);
        $html    = $this->view->renderMatrix($payload, []);

        // The first row (no colours) must not carry a style attribute.
        $firstRow = (string) strstr($html, '<tbody><tr>');
        $firstRow = (string) strstr($firstRow, '</tr>', true);
        self::assertStringNotContainsString('style=', $firstRow);
        self::assertStringNotContainsString('ibl-team-cell--colored', $firstRow);
        self::assertStringContainsString('class="sticky-col h2h-row-label"', $firstRow);
    }

    public function testRowLabelLinksWhenLinkFranchiseIdIsSet(): void
    {
        // link_franchise_id = 1 → row label cell should link to franchise 1's team page.
        $html = $this->view->renderMatrix($this->makePlayedPayload(['link_franchise_id' => 1], 'franchises'), []);
        self::assertStringContainsString('href="modules.php?name=Team&amp;op=team&amp;teamid=1"', $html);
    }

    public function testRowLabelDoesNotLinkWhenLinkFranchiseIdIsZero(): void
    {
        // Default link_franchise_id = 0 → no link.
        $html = $this->view->renderMatrix($this->makePlayedPayload([], 'gms'), []);
        self::assertStringNotContainsString('href="modules.php?name=Team', $html);
    }

    // ---------------------------------------------------------------------------
    // Table shell: sticky pattern + corner cell
    // ---------------------------------------------------------------------------

    public function testMatrixUsesSharedStickyTablePattern(): void
    {
        $html = $this->view->renderMatrix($this->makePlayedPayload(), []);

        self::assertStringContainsString('<div class="sticky-scroll-wrapper page-sticky"><div class="sticky-scroll-container">', $html);
        self::assertStringContainsString('class="ibl-data-table sticky-table h2h-matrix"', $html);
        self::assertStringContainsString('style="--h2h-col-chars: 3; --h2h-col-count: 2;"', $html);
        self::assertStringContainsString('<th class="sticky-col sticky-corner h2h-corner">', $html);
        self::assertStringContainsString('&rarr;&rarr;', $html);
        self::assertStringContainsString('&uarr;', $html);
    }

    public function testColumnWidthCharsTracksTheLongestRecordString(): void
    {
        $a = $this->makeEntry();
        $b = $this->makeEntry(['key' => 'lakers', 'franchise_id' => 2, 'label' => 'Lakers', 'logo' => 'lakers.png']);

        // "125-118" is 7 characters, so every column must be sized for 7.
        $html = $this->view->renderMatrix($this->makePayload(
            [$a, $b],
            [
                'celtics' => ['lakers' => ['wins' => 125, 'losses' => 118]],
                'lakers'  => ['celtics' => ['wins' => 118, 'losses' => 125]],
            ],
        ), []);

        self::assertStringContainsString('--h2h-col-chars: 7;', $html);
    }

    public function testColumnWidthCharsNeverDropsBelowThree(): void
    {
        // Longest record here is "1-0" (3 chars); the floor keeps it at 3, not 1.
        $html = $this->view->renderMatrix($this->makePlayedPayload(), []);

        self::assertStringContainsString('--h2h-col-chars: 3;', $html);
    }

    public function testColumnWidthCountMatchesTheRenderedAxisSize(): void
    {
        $axis = [];
        $records = [];
        foreach (['a', 'b', 'c', 'd'] as $i => $key) {
            $axis[] = $this->makeEntry(['key' => $key, 'franchise_id' => $i + 1, 'logo' => "{$key}.png"]);
            $records[$key] = ['a' => ['wins' => 1, 'losses' => 1]];
        }

        $html = $this->view->renderMatrix($this->makePayload($axis, $records), []);

        self::assertStringContainsString('--h2h-col-count: 4;', $html);
    }

    // ---------------------------------------------------------------------------
    // Column headers
    // ---------------------------------------------------------------------------

    public function testColumnHeadersAreLogoOnlyWhenLogosAreUnique(): void
    {
        $html = $this->view->renderMatrix($this->makePlayedPayload(), []);

        self::assertStringNotContainsString('h2h-col-header__text', $html);
        self::assertStringContainsString('class="series-logo-img" alt="Celtics (2020-present)"', $html);
        self::assertStringContainsString('<th class="h2h-col-header" title="Celtics (2020-present)">', $html);
    }

    public function testColumnHeaderLinksWhenLinkFranchiseIdIsSet(): void
    {
        // A franchise/team entry with link_franchise_id = 3 wraps the header in <a href="...">
        $a = $this->makeEntry(['key' => 'a', 'franchise_id' => 3, 'label' => 'Alpha', 'logo' => 'a.png', 'link_franchise_id' => 3]);
        $b = $this->makeEntry(['key' => 'b', 'franchise_id' => 4, 'label' => 'Bravo', 'logo' => 'b.png', 'link_franchise_id' => 4]);
        $payload = $this->makePayload([$a, $b], ['a' => ['b' => ['wins' => 1, 'losses' => 0]]], 'franchises');

        $html = $this->view->renderMatrix($payload, []);

        self::assertStringContainsString('<a href="modules.php?name=Team&amp;op=team&amp;teamid=3" aria-label="Alpha (2020-present)">', $html);
        self::assertStringContainsString('<a href="modules.php?name=Team&amp;op=team&amp;teamid=4" aria-label="Bravo (2020-present)">', $html);
    }

    public function testColumnHeaderDoesNotLinkWhenLinkFranchiseIdIsZero(): void
    {
        // GM axis entries with link_franchise_id = 0 must not produce a link in the header.
        $html = $this->view->renderMatrix($this->makePlayedPayload([], 'gms'), []);

        self::assertStringNotContainsString('<a href="modules.php?name=Team', $html);
    }

    public function testColumnHeaderLinksForActiveTenureGmEntryOnly(): void
    {
        // Active-tenure GM: link_franchise_id = 5.
        $activGm = $this->makeEntry(['key' => 'ActiveGM', 'franchise_id' => 5, 'label' => 'ActiveGM', 'logo' => 'new5.png', 'link_franchise_id' => 5]);
        // Retired GM: link_franchise_id = 0.
        $retiredGm = $this->makeEntry(['key' => 'RetiredGM', 'franchise_id' => 6, 'label' => 'RetiredGM', 'logo' => 'new6.png', 'link_franchise_id' => 0]);
        $payload = $this->makePayload(
            [$activGm, $retiredGm],
            ['ActiveGM' => ['RetiredGM' => ['wins' => 2, 'losses' => 1]], 'RetiredGM' => ['ActiveGM' => ['wins' => 1, 'losses' => 2]]],
            'gms',
        );

        $html = $this->view->renderMatrix($payload, []);

        // Column header for ActiveGM links to franchise 5 (sublabel '2020-present' is the default).
        self::assertStringContainsString('<a href="modules.php?name=Team&amp;op=team&amp;teamid=5" aria-label="ActiveGM (2020-present)">', $html);
        // Column header for RetiredGM does not link.
        self::assertStringNotContainsString('teamid=6', $html);
    }

    public function testColumnHeadersGainRotatedTextWhenTwoEntriesShareALogo(): void
    {
        $a = $this->makeEntry(['key' => 'nj',  'franchise_id' => 4, 'label' => 'New Jersey Nets', 'logo' => 'nets.png']);
        $b = $this->makeEntry(['key' => 'bkn', 'franchise_id' => 4, 'label' => 'Brooklyn Nets',   'logo' => 'nets.png']);
        $payload = $this->makePayload([$a, $b], ['nj' => ['bkn' => ['wins' => 2, 'losses' => 1]]], 'teams');

        $html = $this->view->renderMatrix($payload, []);

        self::assertStringContainsString('<th class="h2h-col-header h2h-col-header--labeled" title="New Jersey Nets (2020-present)">', $html);
        self::assertStringContainsString('<span class="h2h-col-header__text">New Jersey Nets</span>', $html);
        self::assertStringContainsString('<span class="h2h-col-header__text">Brooklyn Nets</span>', $html);
        // With text present the logo is decorative.
        self::assertStringContainsString('class="series-logo-img" alt=""', $html);
    }

    // ---------------------------------------------------------------------------
    // Hidden 0-0 participants
    // ---------------------------------------------------------------------------

    public function testEntriesWithNoGamesAreHiddenFromRowsAndColumns(): void
    {
        $a = $this->makeEntry(['key' => 'a', 'franchise_id' => 1, 'label' => 'Alpha', 'logo' => 'a.png']);
        $b = $this->makeEntry(['key' => 'b', 'franchise_id' => 2, 'label' => 'Bravo', 'logo' => 'b.png']);
        $c = $this->makeEntry(['key' => 'c', 'franchise_id' => 3, 'label' => 'NeverPlayed', 'logo' => 'c.png']);

        $records = [
            'a' => ['b' => ['wins' => 3, 'losses' => 1], 'c' => ['wins' => 0, 'losses' => 0]],
            'b' => ['a' => ['wins' => 1, 'losses' => 3], 'c' => ['wins' => 0, 'losses' => 0]],
            'c' => ['a' => ['wins' => 0, 'losses' => 0], 'b' => ['wins' => 0, 'losses' => 0]],
        ];
        $html = $this->view->renderMatrix($this->makePayload([$a, $b, $c], $records), []);

        self::assertStringNotContainsString('NeverPlayed', $html);
        self::assertSame(2, substr_count($html, '<th class="h2h-col-header"'));
        self::assertSame(2, substr_count($html, 'h2h-row-label'));
    }

    public function testEntryWithGamesOnlyInAColumnStaysVisible(): void
    {
        // Records are stored per row; 'b' has no row of its own but appears in a's row.
        $a = $this->makeEntry(['key' => 'a', 'franchise_id' => 1, 'label' => 'Alpha', 'logo' => 'a.png']);
        $b = $this->makeEntry(['key' => 'b', 'franchise_id' => 2, 'label' => 'Bravo', 'logo' => 'b.png']);

        $html = $this->view->renderMatrix(
            $this->makePayload([$a, $b], ['a' => ['b' => ['wins' => 0, 'losses' => 2]]]),
            [],
        );

        self::assertStringContainsString('Bravo', $html);
        self::assertSame(2, substr_count($html, 'h2h-row-label'));
    }

    public function testAllZeroPayloadRendersEmptyState(): void
    {
        $a = $this->makeEntry(['key' => 'a', 'franchise_id' => 1]);
        $b = $this->makeEntry(['key' => 'b', 'franchise_id' => 2]);

        $html = $this->view->renderMatrix($this->makePayload([$a, $b], []), []);

        self::assertStringContainsString('table-empty-message', $html);
        self::assertStringNotContainsString('<table', $html);
    }

    // ---------------------------------------------------------------------------
    // Diagonal / self cell
    // ---------------------------------------------------------------------------

    public function testDiagonalCellIsBlankAndMarkedSelf(): void
    {
        $html = $this->view->renderMatrix($this->makePlayedPayload(), []);

        self::assertMatchesRegularExpression('/<td class="h2h-self"><\/td>/', $html);
    }

    // ---------------------------------------------------------------------------
    // User-match row and column
    // ---------------------------------------------------------------------------

    public function testUserMatchMarksRowAndColumn(): void
    {
        $html = $this->view->renderMatrix($this->makePlayedPayload(['key' => 'celtics']), ['celtics']);

        self::assertStringContainsString('<tr class="h2h-user-row">', $html);
        self::assertSame(1, substr_count($html, '<tr class="h2h-user-row">'));
        // Header for the user's column and the record cell in the other row both carry h2h-user-col.
        self::assertStringContainsString('<th class="h2h-col-header h2h-user-col"', $html);
        self::assertStringContainsString('h2h-losing h2h-user-col', $html);
        // The user's own diagonal cell is also in the user column.
        self::assertStringContainsString('<td class="h2h-self h2h-user-col"></td>', $html);
    }

    public function testAnonymousUserGetsNoUserMarkers(): void
    {
        $html = $this->view->renderMatrix($this->makePlayedPayload(), []);

        self::assertStringNotContainsString('h2h-user-row', $html);
        self::assertStringNotContainsString('h2h-user-col', $html);
    }

    // ---------------------------------------------------------------------------
    // Cell title / win percentage
    // ---------------------------------------------------------------------------

    public function testCellTitleCarriesWinPercentage(): void
    {
        $a = $this->makeEntry(['key' => 'celtics', 'franchise_id' => 1]);
        $b = $this->makeEntry(['key' => 'lakers',  'franchise_id' => 2, 'label' => 'Lakers', 'logo' => 'lakers.png']);

        $records = ['celtics' => ['lakers' => ['wins' => 3, 'losses' => 1]]];
        $payload = $this->makePayload([$a, $b], $records);
        $html    = $this->view->renderMatrix($payload, []);

        // 3 wins out of 4 = 75%
        self::assertStringContainsString('3-1 (75%)', $html);
    }

    // ---------------------------------------------------------------------------
    // Absent matchup among visible participants
    // ---------------------------------------------------------------------------

    public function testAbsentMatchupRendersMutedZeroZero(): void
    {
        $a = $this->makeEntry(['key' => 'a', 'franchise_id' => 1, 'label' => 'A', 'logo' => 'a.png']);
        $b = $this->makeEntry(['key' => 'b', 'franchise_id' => 2, 'label' => 'B', 'logo' => 'b.png']);
        $c = $this->makeEntry(['key' => 'c', 'franchise_id' => 3, 'label' => 'C', 'logo' => 'c.png']);

        $records = [
            'a' => ['b' => ['wins' => 1, 'losses' => 0]],
            'b' => ['a' => ['wins' => 0, 'losses' => 1], 'c' => ['wins' => 2, 'losses' => 0]],
            'c' => ['b' => ['wins' => 0, 'losses' => 2]],
        ];
        $html = $this->view->renderMatrix($this->makePayload([$a, $b, $c], $records), []);

        // a vs c never met: tied colour class plus the muted unplayed modifier.
        self::assertStringContainsString('<td class="series-record-cell h2h-tied h2h-unplayed" title="0-0 (0%)">0-0</td>', $html);
    }

    // ---------------------------------------------------------------------------
    // Winning / Losing / Tied classes
    // ---------------------------------------------------------------------------

    public function testWinningLosingTiedClassesFollowRecord(): void
    {
        $a = $this->makeEntry(['key' => 'a', 'franchise_id' => 1, 'label' => 'A', 'logo' => 'a.png']);
        $b = $this->makeEntry(['key' => 'b', 'franchise_id' => 2, 'label' => 'B', 'logo' => 'b.png']);
        $c = $this->makeEntry(['key' => 'c', 'franchise_id' => 3, 'label' => 'C', 'logo' => 'c.png']);

        $records = [
            'a' => [
                'b' => ['wins' => 5, 'losses' => 2],  // winning
                'c' => ['wins' => 3, 'losses' => 3],  // tied
            ],
            'b' => [
                'a' => ['wins' => 2, 'losses' => 5],  // losing
                'c' => ['wins' => 1, 'losses' => 0],
            ],
            'c' => [
                'a' => ['wins' => 3, 'losses' => 3],
                'b' => ['wins' => 0, 'losses' => 1],
            ],
        ];
        $payload = $this->makePayload([$a, $b, $c], $records);
        $html    = $this->view->renderMatrix($payload, []);

        self::assertStringContainsString('<td class="series-record-cell h2h-winning" title="5-2 (71%)">5-2</td>', $html);
        self::assertStringContainsString('<td class="series-record-cell h2h-losing" title="2-5 (29%)">2-5</td>', $html);
        self::assertStringContainsString('<td class="series-record-cell h2h-tied" title="3-3 (50%)">3-3</td>', $html);
        self::assertStringNotContainsString('h2h-unplayed', $html);
    }

    // ---------------------------------------------------------------------------
    // Empty axis
    // ---------------------------------------------------------------------------

    public function testEmptyAxisRendersEmptyState(): void
    {
        $payload = $this->makePayload([]);
        $html    = $this->view->renderMatrix($payload, []);

        self::assertStringContainsString('table-empty-message', $html);
        self::assertStringNotContainsString('<table', $html);
    }

    // ---------------------------------------------------------------------------
    // Filter form
    // ---------------------------------------------------------------------------

    public function testFilterFormMarksCurrentSelectionSelected(): void
    {
        $html = $this->view->renderFilterForm('gms', 'playoffs', 'all');

        self::assertStringContainsString('value="gms" selected', $html);
        self::assertStringContainsString('value="playoffs" selected', $html);
        self::assertStringContainsString('value="all" selected', $html);
    }

    public function testFilterFormUsesSharedFormClasses(): void
    {
        $html = $this->view->renderFilterForm('franchises', 'all', 'current');

        self::assertStringContainsString('<form method="post" action="modules.php?name=HeadToHeadRecords" class="h2h-filter">', $html);
        self::assertStringContainsString('<select name="dimension" class="ibl-select">', $html);
        self::assertStringContainsString('<span class="ibl-label ibl-label--sm">Dimension</span>', $html);
        self::assertStringContainsString('<button type="submit" class="ibl-btn ibl-btn--primary ibl-btn--sm">Filter</button>', $html);
        self::assertStringNotContainsString('<noscript>', $html);
    }

    // ---------------------------------------------------------------------------
    // XSS / escaping
    // ---------------------------------------------------------------------------

    public function testLabelsAreHtmlEscaped(): void
    {
        $payload = $this->makePlayedPayload(['label' => '<script>alert(1)</script>', 'color1' => '']);
        $html    = $this->view->renderMatrix($payload, []);

        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testLogoFileNameIsHtmlEscaped(): void
    {
        $payload = $this->makePlayedPayload(['logo' => 'x" onerror="alert(1)']);
        $html    = $this->view->renderMatrix($payload, []);

        self::assertStringNotContainsString('onerror="alert(1)', $html);
        self::assertStringContainsString('images/logo/x&quot; onerror=&quot;alert(1)', $html);
    }

    // ---------------------------------------------------------------------------
    // Color sanitization
    // ---------------------------------------------------------------------------

    public function testInvalidColorCollapsesToBlack(): void
    {
        $payload = $this->makePlayedPayload(['color1' => 'INVALID', 'color2' => 'ALSOINVALID']);
        $html    = $this->view->renderMatrix($payload, []);

        // TableStyles::sanitizeColor returns '000000' for invalid colors
        self::assertStringContainsString('--team-cell-bg: #000000', $html);
    }

    // ---------------------------------------------------------------------------
    // Inline script
    // ---------------------------------------------------------------------------

    public function testScriptWiresTooltipColumnHoverAndAutoSubmit(): void
    {
        $html = $this->view->renderTapTooltipScript();

        self::assertStringContainsString('h2h-tip-open', $html);
        self::assertStringContainsString('h2h-col-hover', $html);
        self::assertStringContainsString('form.h2h-filter', $html);
    }

    // ---------------------------------------------------------------------------
    // Plan-required method names (behavioral counterparts delivered under renamed names)
    // ---------------------------------------------------------------------------

    public function testRowLabelRendersColorCustomProperties(): void
    {
        $payload = $this->makePlayedPayload(['color1' => '00653A', 'color2' => 'FFFFFF']);
        $html    = $this->view->renderMatrix($payload, []);

        self::assertStringContainsString('--team-cell-bg: #00653A', $html);
        self::assertStringContainsString('--team-cell-color: #FFFFFF', $html);
    }

    public function testRowLabelWithoutColorsHasNoStyleAttribute(): void
    {
        $payload  = $this->makePlayedPayload(['color1' => '', 'color2' => '']);
        $html     = $this->view->renderMatrix($payload, []);
        $firstRow = (string) strstr($html, '<tbody><tr>');
        $firstRow = (string) strstr($firstRow, '</tr>', true);

        self::assertStringNotContainsString('style=', $firstRow);
    }

    public function testDiagonalCellIsSkipped(): void
    {
        $html = $this->view->renderMatrix($this->makePlayedPayload(), []);

        self::assertMatchesRegularExpression('/<td class="h2h-self"><\/td>/', $html);
    }

    public function testUserMatchRowIsBold(): void
    {
        $html = $this->view->renderMatrix($this->makePlayedPayload(['key' => 'celtics']), ['celtics']);

        self::assertStringContainsString('<tr class="h2h-user-row">', $html);
    }

    public function testAbsentMatchupRendersZeroZeroTied(): void
    {
        $a = $this->makeEntry(['key' => 'a', 'franchise_id' => 1, 'label' => 'A', 'logo' => 'a.png']);
        $b = $this->makeEntry(['key' => 'b', 'franchise_id' => 2, 'label' => 'B', 'logo' => 'b.png']);
        $c = $this->makeEntry(['key' => 'c', 'franchise_id' => 3, 'label' => 'C', 'logo' => 'c.png']);

        $records = [
            'a' => ['b' => ['wins' => 1, 'losses' => 0]],
            'b' => ['a' => ['wins' => 0, 'losses' => 1], 'c' => ['wins' => 1, 'losses' => 0]],
            'c' => ['b' => ['wins' => 0, 'losses' => 1]],
        ];
        $html = $this->view->renderMatrix($this->makePayload([$a, $b, $c], $records), []);

        self::assertStringContainsString('h2h-tied', $html);
        self::assertStringContainsString('>0-0<', $html);
    }
}
