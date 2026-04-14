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

    public function testRenderFilterFormContainsSelects(): void
    {
        $html = $this->view->renderFilterForm('current', 'active_teams', 'regular');

        self::assertStringContainsString('<form method="POST"', $html);
        self::assertStringContainsString('name="scope"', $html);
        self::assertStringContainsString('name="dimension"', $html);
        self::assertStringContainsString('name="phase"', $html);
    }

    public function testRenderFilterFormSelectsCorrectOptions(): void
    {
        $html = $this->view->renderFilterForm('all_time', 'gms', 'playoffs');

        self::assertStringContainsString('value="all_time" selected', $html);
        self::assertStringContainsString('value="gms" selected', $html);
        self::assertStringContainsString('value="playoffs" selected', $html);
    }

    public function testRenderMatrixEmptyAxisShowsEmptyState(): void
    {
        $payload = ['axis' => [], 'matrix' => []];

        $html = $this->view->renderMatrix($payload, []);

        self::assertStringContainsString('h2h-empty-state', $html);
        self::assertStringContainsString('No records found', $html);
    }

    public function testRenderMatrixWithDataShowsTable(): void
    {
        $payload = [
            'axis' => [
                ['key' => 1, 'label' => 'Warriors', 'logo' => 'images/logo/new1.png', 'franchise_id' => 1],
                ['key' => 2, 'label' => 'Metros', 'logo' => 'images/logo/new2.png', 'franchise_id' => 2],
            ],
            'matrix' => [
                1 => [2 => ['wins' => 5, 'losses' => 3]],
                2 => [1 => ['wins' => 3, 'losses' => 5]],
            ],
        ];

        $html = $this->view->renderMatrix($payload, []);

        self::assertStringContainsString('h2h-table', $html);
        self::assertStringContainsString('Warriors', $html);
        self::assertStringContainsString('Metros', $html);
        self::assertStringContainsString('5-3', $html);
        self::assertStringContainsString('3-5', $html);
    }

    public function testRenderMatrixDiagonalCellsHaveDiagClass(): void
    {
        $payload = [
            'axis' => [
                ['key' => 1, 'label' => 'Warriors', 'logo' => '', 'franchise_id' => 1],
            ],
            'matrix' => [],
        ];

        $html = $this->view->renderMatrix($payload, []);

        self::assertStringContainsString('h2h-diag', $html);
    }

    public function testRenderMatrixEmptyCellsShowEmDash(): void
    {
        $payload = [
            'axis' => [
                ['key' => 1, 'label' => 'Warriors', 'logo' => '', 'franchise_id' => 1],
                ['key' => 2, 'label' => 'Metros', 'logo' => '', 'franchise_id' => 2],
            ],
            'matrix' => [],
        ];

        $html = $this->view->renderMatrix($payload, []);

        self::assertStringContainsString('&mdash;', $html);
        self::assertStringContainsString('h2h-empty', $html);
    }

    public function testRenderMatrixBoldsUserMatchKeys(): void
    {
        $payload = [
            'axis' => [
                ['key' => 1, 'label' => 'Warriors', 'logo' => '', 'franchise_id' => 1],
                ['key' => 2, 'label' => 'Metros', 'logo' => '', 'franchise_id' => 2],
            ],
            'matrix' => [
                1 => [2 => ['wins' => 5, 'losses' => 3]],
                2 => [1 => ['wins' => 3, 'losses' => 5]],
            ],
        ];

        $html = $this->view->renderMatrix($payload, [1]);

        self::assertStringContainsString('<strong>Warriors</strong>', $html);
        self::assertStringContainsString('<strong>5-3</strong>', $html);
    }

    public function testRenderMatrixCellsHaveWinPctTooltip(): void
    {
        $payload = [
            'axis' => [
                ['key' => 1, 'label' => 'Warriors', 'logo' => '', 'franchise_id' => 1],
                ['key' => 2, 'label' => 'Metros', 'logo' => '', 'franchise_id' => 2],
            ],
            'matrix' => [
                1 => [2 => ['wins' => 5, 'losses' => 5]],
            ],
        ];

        $html = $this->view->renderMatrix($payload, []);

        self::assertStringContainsString('title="0.500"', $html);
    }

    public function testRenderMatrixAppliesStatusClasses(): void
    {
        $payload = [
            'axis' => [
                ['key' => 1, 'label' => 'A', 'logo' => '', 'franchise_id' => 1],
                ['key' => 2, 'label' => 'B', 'logo' => '', 'franchise_id' => 2],
            ],
            'matrix' => [
                1 => [2 => ['wins' => 5, 'losses' => 3]],
                2 => [1 => ['wins' => 3, 'losses' => 5]],
            ],
        ];

        $html = $this->view->renderMatrix($payload, []);

        self::assertStringContainsString('h2h-winning', $html);
        self::assertStringContainsString('h2h-losing', $html);
    }

    public function testRenderTapTooltipScriptContainsJavascript(): void
    {
        $html = $this->view->renderTapTooltipScript();

        self::assertStringContainsString('<script>', $html);
        self::assertStringContainsString('touchend', $html);
        self::assertStringContainsString('h2h-tooltip', $html);
    }

    public function testRenderMatrixGmDimensionShowsGmHeaders(): void
    {
        $payload = [
            'axis' => [
                ['key' => 'A-Jay', 'label' => 'A-Jay', 'logo' => '', 'franchise_id' => 0],
                ['key' => 'Bob', 'label' => 'Bob', 'logo' => '', 'franchise_id' => 0],
            ],
            'matrix' => [
                'A-Jay' => ['Bob' => ['wins' => 10, 'losses' => 5]],
            ],
        ];

        $html = $this->view->renderMatrix($payload, []);

        self::assertStringContainsString('h2h-gm-header', $html);
        self::assertStringContainsString('A-Jay', $html);
    }
}
