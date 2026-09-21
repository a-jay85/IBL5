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
     * @return array{key: string, franchise_id: int, label: string, sublabel: string, color1: string, color2: string, logo: string}
     */
    private function makeEntry(array $overrides = []): array
    {
        return [
            'key'          => (string) ($overrides['key']          ?? 'celtics'),
            'franchise_id' => (int)    ($overrides['franchise_id'] ?? 1),
            'label'        => (string) ($overrides['label']        ?? 'Celtics'),
            'sublabel'     => (string) ($overrides['sublabel']     ?? '2020-present'),
            'color1'       => (string) ($overrides['color1']       ?? '00653A'),
            'color2'       => (string) ($overrides['color2']       ?? 'FFFFFF'),
            'logo'         => (string) ($overrides['logo']         ?? 'celtics.png'),
        ];
    }

    /**
     * @param list<array{key: string, franchise_id: int, label: string, sublabel: string, color1: string, color2: string, logo: string}> $axis
     * @param array<string, array<string, array{wins: int, losses: int}>> $records
     * @return array{dimension: string, phase: string, scope: string, axis: list<array{key: string, franchise_id: int, label: string, sublabel: string, color1: string, color2: string, logo: string}>, records: array<string, array<string, array{wins: int, losses: int}>>}
     */
    private function makePayload(array $axis = [], array $records = []): array
    {
        return [
            'dimension' => 'franchises',
            'phase'     => 'regular',
            'scope'     => 'current',
            'axis'      => $axis,
            'records'   => $records,
        ];
    }

    // ---------------------------------------------------------------------------
    // Row label tests
    // ---------------------------------------------------------------------------

    public function testRowLabelRendersColorCustomProperties(): void
    {
        $entry   = $this->makeEntry(['color1' => '00653A', 'color2' => 'FFFFFF']);
        $payload = $this->makePayload([$entry]);
        $html    = $this->view->renderMatrix($payload, []);

        self::assertStringContainsString('--h2h-row-bg:#00653A', $html);
        self::assertStringContainsString('--h2h-row-fg:#FFFFFF', $html);
    }

    public function testRowLabelWithoutColorsHasNoStyleAttribute(): void
    {
        $entry   = $this->makeEntry(['color1' => '', 'color2' => '']);
        $payload = $this->makePayload([$entry]);
        $html    = $this->view->renderMatrix($payload, []);

        self::assertStringNotContainsString('style=', $html);
    }

    // ---------------------------------------------------------------------------
    // Diagonal / self cell
    // ---------------------------------------------------------------------------

    public function testDiagonalCellIsSkipped(): void
    {
        $entry   = $this->makeEntry();
        $payload = $this->makePayload([$entry]);
        $html    = $this->view->renderMatrix($payload, []);

        self::assertStringContainsString('h2h-self', $html);
        // The self cell must contain no record text
        self::assertMatchesRegularExpression('/<td class="h2h-self"><\/td>/', $html);
    }

    // ---------------------------------------------------------------------------
    // User-match row
    // ---------------------------------------------------------------------------

    public function testUserMatchRowIsBold(): void
    {
        $entry   = $this->makeEntry(['key' => 'celtics']);
        $payload = $this->makePayload([$entry]);
        $html    = $this->view->renderMatrix($payload, ['celtics']);

        self::assertStringContainsString('h2h-user-row', $html);
    }

    // ---------------------------------------------------------------------------
    // Cell title / win percentage
    // ---------------------------------------------------------------------------

    public function testCellTitleCarriesWinPercentage(): void
    {
        $a = $this->makeEntry(['key' => 'celtics', 'franchise_id' => 1]);
        $b = $this->makeEntry(['key' => 'lakers',  'franchise_id' => 2, 'label' => 'Lakers']);

        $records = ['celtics' => ['lakers' => ['wins' => 3, 'losses' => 1]]];
        $payload = $this->makePayload([$a, $b], $records);
        $html    = $this->view->renderMatrix($payload, []);

        // 3 wins out of 4 = 75%
        self::assertStringContainsString('3-1 (75%)', $html);
    }

    // ---------------------------------------------------------------------------
    // Absent matchup
    // ---------------------------------------------------------------------------

    public function testAbsentMatchupRendersZeroZeroTied(): void
    {
        $a = $this->makeEntry(['key' => 'celtics', 'franchise_id' => 1]);
        $b = $this->makeEntry(['key' => 'lakers',  'franchise_id' => 2, 'label' => 'Lakers']);

        $payload = $this->makePayload([$a, $b], []);
        $html    = $this->view->renderMatrix($payload, []);

        self::assertStringContainsString('h2h-tied', $html);
        self::assertStringContainsString('>0-0<', $html);
    }

    // ---------------------------------------------------------------------------
    // Winning / Losing / Tied classes
    // ---------------------------------------------------------------------------

    public function testWinningLosingTiedClassesFollowRecord(): void
    {
        $a = $this->makeEntry(['key' => 'a', 'franchise_id' => 1, 'label' => 'A']);
        $b = $this->makeEntry(['key' => 'b', 'franchise_id' => 2, 'label' => 'B']);
        $c = $this->makeEntry(['key' => 'c', 'franchise_id' => 3, 'label' => 'C']);

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

        self::assertStringContainsString('h2h-winning', $html);
        self::assertStringContainsString('h2h-losing', $html);
        self::assertStringContainsString('h2h-tied', $html);
    }

    // ---------------------------------------------------------------------------
    // Empty axis
    // ---------------------------------------------------------------------------

    public function testEmptyAxisRendersEmptyState(): void
    {
        $payload = $this->makePayload([]);
        $html    = $this->view->renderMatrix($payload, []);

        self::assertStringContainsString('h2h-empty', $html);
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

    // ---------------------------------------------------------------------------
    // XSS / escaping
    // ---------------------------------------------------------------------------

    public function testLabelsAreHtmlEscaped(): void
    {
        $entry   = $this->makeEntry(['label' => '<script>alert(1)</script>', 'color1' => '']);
        $payload = $this->makePayload([$entry]);
        $html    = $this->view->renderMatrix($payload, []);

        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    // ---------------------------------------------------------------------------
    // Color sanitization
    // ---------------------------------------------------------------------------

    public function testInvalidColorCollapsesToBlack(): void
    {
        $entry   = $this->makeEntry(['color1' => 'INVALID', 'color2' => 'ALSOINVALID']);
        $payload = $this->makePayload([$entry]);
        $html    = $this->view->renderMatrix($payload, []);

        // TableStyles::sanitizeColor returns '000000' for invalid colors
        self::assertStringContainsString('--h2h-row-bg:#000000', $html);
    }
}
