<?php

declare(strict_types=1);

namespace Tests\ProjectedDraftOrder;

use ProjectedDraftOrder\Contracts\ProjectedDraftOrderViewInterface;
use ProjectedDraftOrder\ProjectedDraftOrderView;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ProjectedDraftOrder\ProjectedDraftOrderView
 */
class ProjectedDraftOrderViewTest extends TestCase
{
    private ProjectedDraftOrderView $view;

    protected function setUp(): void
    {
        $this->view = new ProjectedDraftOrderView();
    }

    public function testImplementsViewInterface(): void
    {
        $this->assertInstanceOf(ProjectedDraftOrderViewInterface::class, $this->view);
    }

    public function testRenderReturnsString(): void
    {
        $result = $this->view->render($this->emptyDraftOrder(), 2026);

        $this->assertIsString($result);
    }

    public function testRenderContainsSeasonYear(): void
    {
        $result = $this->view->render($this->emptyDraftOrder(), 2026);

        $this->assertStringContainsString('2026', $result);
    }

    public function testRenderContainsTitle(): void
    {
        $result = $this->view->render($this->emptyDraftOrder(), 2026);

        $this->assertStringContainsString('Projected Draft Order', $result);
    }

    public function testRenderContainsRoundHeaders(): void
    {
        $result = $this->view->render($this->emptyDraftOrder(), 2026);

        $this->assertStringContainsString('Round 1', $result);
        $this->assertStringContainsString('Round 2', $result);
    }

    public function testRenderContainsTableStructure(): void
    {
        $result = $this->view->render($this->sampleDraftOrder(), 2026);

        $this->assertStringContainsString('<table', $result);
        $this->assertStringContainsString('</table>', $result);
        $this->assertStringContainsString('<thead>', $result);
        $this->assertStringContainsString('<tbody>', $result);
    }

    public function testRenderContainsColumnHeaders(): void
    {
        $result = $this->view->render($this->sampleDraftOrder(), 2026);

        $this->assertStringContainsString('Pick', $result);
        $this->assertStringContainsString('Team', $result);
        $this->assertStringContainsString('Record', $result);
        $this->assertStringContainsString('Notes', $result);
    }

    public function testRenderDoesNotContainOwnerColumnHeader(): void
    {
        $result = $this->view->render($this->sampleDraftOrder(), 2026);

        $this->assertStringNotContainsString('<th>Owner</th>', $result);
    }

    public function testRenderContainsDescription(): void
    {
        $result = $this->view->render($this->emptyDraftOrder(), 2026);

        $this->assertStringContainsString('projected-draft-order-description', $result);
        $this->assertStringContainsString('If the draft were held today', $result);
    }

    public function testRenderShowsTeamRecord(): void
    {
        $result = $this->view->render($this->sampleDraftOrder(), 2026);

        $this->assertStringContainsString('20-62', $result);
    }

    public function testRenderShowsAllSeparatorRowsInRound1(): void
    {
        $order = $this->sampleDraftOrderWithPlayoffSeparator();
        $result = $this->view->render($order, 2026);

        $round1Start = strpos($result, 'Round 1');
        $round2Start = strpos($result, 'Round 2');
        $this->assertNotFalse($round1Start);
        $this->assertNotFalse($round2Start);

        $round1Html = substr($result, $round1Start, $round2Start - $round1Start);
        $this->assertStringContainsString('Lottery Teams', $round1Html);
        $this->assertStringNotContainsString('Lottery Results', $round1Html);
        $this->assertStringContainsString('Playoff Teams', $round1Html);
        $this->assertStringContainsString('Division Winners', $round1Html);
        $this->assertStringContainsString('Conference Winners', $round1Html);
    }

    public function testRenderDoesNotShowSeparatorsInRound2(): void
    {
        $order = $this->sampleDraftOrderWithPlayoffSeparator();
        $result = $this->view->render($order, 2026);

        $round2Start = strpos($result, 'Round 2');
        $this->assertNotFalse($round2Start);

        $round2Html = substr($result, $round2Start);
        $this->assertStringNotContainsString('projected-draft-order-separator', $round2Html);
    }

    public function testRenderEscapesHtmlEntities(): void
    {
        $order = $this->emptyDraftOrder();
        $order['round1'] = [
            $this->makeSlot(1, 1, 'Team<script>', 20, 62, '000000', 'FFFFFF', 1, 'Team<script>', '000000', 'FFFFFF', false, ''),
        ];

        $result = $this->view->render($order, 2026);

        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testTradedPickHasTradedRowClass(): void
    {
        $order = $this->emptyDraftOrder();
        $order['round1'] = [
            $this->makeSlot(1, 1, 'Heat', 20, 62, '98002E', 'F9A01B', 2, 'Celtics', '007A33', 'FFFFFF', true, 'via trade'),
        ];

        $result = $this->view->render($order, 2026);

        $this->assertStringContainsString('projected-draft-order-traded', $result);
    }

    public function testOwnPickDoesNotHaveTradedClass(): void
    {
        $order = $this->emptyDraftOrder();
        $order['round1'] = [
            $this->makeSlot(1, 1, 'Heat', 20, 62, '98002E', 'F9A01B', 1, 'Heat', '98002E', 'F9A01B', false, ''),
        ];

        $result = $this->view->render($order, 2026);

        $this->assertStringNotContainsString('projected-draft-order-traded', $result);
    }

    public function testTradedPickShowsOwnerInTeamColumnWithColors(): void
    {
        $order = $this->emptyDraftOrder();
        $order['round1'] = [
            $this->makeSlot(1, 1, 'Heat', 20, 62, '98002E', 'F9A01B', 2, 'Celtics', '007A33', 'FFFFFF', true, 'via trade'),
        ];

        $result = $this->view->render($order, 2026);

        $this->assertStringContainsString('ibl-team-cell--colored', $result);
        $this->assertStringContainsString('007A33', $result);
    }

    public function testTradedPickShowsAsteriskOnOwnerName(): void
    {
        $order = $this->emptyDraftOrder();
        $order['round1'] = [
            $this->makeSlot(1, 1, 'Heat', 20, 62, '98002E', 'F9A01B', 2, 'Celtics', '007A33', 'FFFFFF', true, 'via trade'),
        ];

        $result = $this->view->render($order, 2026);

        $this->assertStringContainsString('Celtics*', $result);
    }

    public function testTradedPickRecordCellUsesOriginalTeamColors(): void
    {
        $order = $this->emptyDraftOrder();
        $order['round1'] = [
            $this->makeSlot(1, 1, 'Heat', 20, 62, '98002E', 'F9A01B', 2, 'Celtics', '007A33', 'FFFFFF', true, 'via trade'),
        ];

        $result = $this->view->render($order, 2026);

        $this->assertStringContainsString('98002E', $result);
        $this->assertStringContainsString('20-62', $result);
    }

    public function testRecordCellHasTeamLogo(): void
    {
        $order = $this->emptyDraftOrder();
        $order['round1'] = [
            $this->makeSlot(1, 1, 'Heat', 20, 62, '98002E', 'F9A01B', 1, 'Heat', '98002E', 'F9A01B', false, ''),
        ];

        $result = $this->view->render($order, 2026);

        $this->assertMatchesRegularExpression('/<img[^>]+new1\.png.*20-62/', $result);
    }

    public function testOwnPickRecordCellUsesTeamColors(): void
    {
        $order = $this->emptyDraftOrder();
        $order['round1'] = [
            $this->makeSlot(1, 1, 'Heat', 20, 62, '98002E', 'F9A01B', 1, 'Heat', '98002E', 'F9A01B', false, ''),
        ];

        $result = $this->view->render($order, 2026);

        $this->assertMatchesRegularExpression('/--team-cell-bg: #98002E.*20-62.*<\/td><td\b/s', $result);
    }

    public function testNoTooltipsInOutput(): void
    {
        $order = $this->emptyDraftOrder();
        $order['round1'] = [
            $this->makeSlot(1, 1, 'Heat', 20, 62, '98002E', 'F9A01B', 2, 'Celtics', '007A33', 'FFFFFF', true, 'via trade'),
        ];

        $result = $this->view->render($order, 2026);

        $this->assertStringNotContainsString('ibl-tooltip', $result);
    }

    public function testOwnPickDoesNotShowAsterisk(): void
    {
        $order = $this->emptyDraftOrder();
        $order['round1'] = [
            $this->makeSlot(1, 1, 'Heat', 20, 62, '98002E', 'F9A01B', 1, 'Heat', '98002E', 'F9A01B', false, ''),
        ];

        $result = $this->view->render($order, 2026);

        $this->assertStringNotContainsString('Heat*', $result);
    }

    public function testTradeNotesAreRendered(): void
    {
        $order = $this->emptyDraftOrder();
        $order['round1'] = [
            $this->makeSlot(1, 1, 'Heat', 20, 62, '98002E', 'F9A01B', 2, 'Celtics', '007A33', 'FFFFFF', true, 'via 2025 trade'),
        ];

        $result = $this->view->render($order, 2026);

        $this->assertStringContainsString('via 2025 trade', $result);
    }

    public function testNotesCellIsTruncatable(): void
    {
        $order = $this->emptyDraftOrder();
        $order['round1'] = [
            $this->makeSlot(1, 1, 'Heat', 20, 62, '98002E', 'F9A01B', 2, 'Celtics', '007A33', 'FFFFFF', true, 'via trade'),
        ];

        $result = $this->view->render($order, 2026);

        $this->assertStringContainsString('projected-draft-order-notes', $result);
        $this->assertStringContainsString('is-expanded', $result);
    }

    public function testUsesDataTableClass(): void
    {
        $result = $this->view->render($this->sampleDraftOrder(), 2026);

        $this->assertStringContainsString('ibl-data-table', $result);
        $this->assertStringContainsString('projected-draft-order-table', $result);
    }

    // =========================================================================
    // Finalization & Admin Drag-and-Drop Tests
    // =========================================================================

    public function testRenderShowsDraftOrderTitleWhenFinalized(): void
    {
        $result = $this->view->render($this->sampleDraftOrder(), 2026, false, true);

        $this->assertStringContainsString('Draft Order', $result);
        $this->assertStringNotContainsString('Projected Draft Order', $result);
    }

    public function testDescriptionHiddenWhenFinalized(): void
    {
        $result = $this->view->render($this->sampleDraftOrder(), 2026, false, true);

        $this->assertStringNotContainsString('projected-draft-order-description', $result);
    }

    public function testAdminAlertShownWhenNotFinalized(): void
    {
        $result = $this->view->render($this->sampleDraftOrder(), 2026, true, false);

        $this->assertStringContainsString('ibl-alert--info', $result);
        $this->assertStringContainsString('Drag the lottery teams', $result);
    }

    public function testAdminAlertHiddenWhenFinalized(): void
    {
        $result = $this->view->render($this->sampleDraftOrder(), 2026, true, true);

        $this->assertStringNotContainsString('ibl-alert--info', $result);
    }

    public function testAdminAlertHiddenForNonAdmin(): void
    {
        $result = $this->view->render($this->sampleDraftOrder(), 2026, false, false);

        $this->assertStringNotContainsString('ibl-alert--info', $result);
    }

    public function testWarningShownWhenFinalizedButDraftNotStarted(): void
    {
        $result = $this->view->render($this->sampleDraftOrder(), 2026, true, true, false);

        $this->assertStringContainsString('ibl-alert--warning', $result);
        $this->assertStringContainsString('can still adjust', $result);
    }

    public function testWarningHiddenWhenDraftStarted(): void
    {
        $result = $this->view->render($this->sampleDraftOrder(), 2026, true, true, true);

        $this->assertStringNotContainsString('ibl-alert--warning', $result);
    }

    public function testWarningHiddenForNonAdmin(): void
    {
        $result = $this->view->render($this->sampleDraftOrder(), 2026, false, true, false);

        $this->assertStringNotContainsString('ibl-alert--warning', $result);
    }

    public function testSaveButtonRenderedForAdmin(): void
    {
        $result = $this->view->render($this->sampleDraftOrder(), 2026, true, false);

        $this->assertStringContainsString('draft-order-save-btn', $result);
    }

    public function testSaveButtonNotRenderedForNonAdmin(): void
    {
        $result = $this->view->render($this->sampleDraftOrder(), 2026, false, false);

        $this->assertStringNotContainsString('draft-order-save-btn', $result);
    }

    public function testDraggableAttributesOnLotteryRowsForAdmin(): void
    {
        $order = $this->sampleDraftOrderWithPlayoffSeparator();
        $result = $this->view->render($order, 2026, true, false);

        $this->assertStringContainsString('draggable="true"', $result);
        $this->assertStringContainsString('data-team-id=', $result);
    }

    public function testDragHandleShownOnDraggableRows(): void
    {
        $order = $this->sampleDraftOrderWithPlayoffSeparator();
        $result = $this->view->render($order, 2026, true, false);

        $this->assertStringContainsString('draft-drag-handle', $result);
        $this->assertStringContainsString('draft-pick-cell', $result);
    }

    public function testDragHandleNotShownWhenNotDraggable(): void
    {
        $order = $this->sampleDraftOrderWithPlayoffSeparator();
        $result = $this->view->render($order, 2026, false, false);

        $this->assertStringNotContainsString('draft-drag-handle', $result);
        $this->assertStringNotContainsString('draft-pick-cell', $result);
    }

    public function testNoDraggableAttributesForNonAdmin(): void
    {
        $order = $this->sampleDraftOrderWithPlayoffSeparator();
        $result = $this->view->render($order, 2026, false, false);

        $this->assertStringNotContainsString('draggable="true"', $result);
    }

    public function testDraggableWhenFinalizedButDraftNotStarted(): void
    {
        $order = $this->sampleDraftOrderWithPlayoffSeparator();
        $result = $this->view->render($order, 2026, true, true, false);

        $this->assertStringContainsString('draggable="true"', $result);
        $this->assertStringContainsString('draft-draggable', $result);
    }

    public function testNoDraggableAttributesWhenDraftStarted(): void
    {
        $order = $this->sampleDraftOrderWithPlayoffSeparator();
        $result = $this->view->render($order, 2026, true, true, true);

        $this->assertStringNotContainsString('draggable="true"', $result);
        $this->assertStringNotContainsString('draft-draggable', $result);
    }

    public function testSaveButtonNotRenderedWhenDraftStarted(): void
    {
        $result = $this->view->render($this->sampleDraftOrder(), 2026, true, true, true);

        $this->assertStringNotContainsString('draft-order-save-btn', $result);
    }

    public function testNonLotteryRowsNotDraggableForAdmin(): void
    {
        $order = $this->sampleDraftOrderWithPlayoffSeparator();
        $result = $this->view->render($order, 2026, true, false);

        // Count draggable rows — should be exactly 12 (picks 1-12)
        $draggableCount = substr_count($result, 'draggable="true"');
        $this->assertSame(12, $draggableCount);
    }

    public function testMovedUpRowGetsGreenHighlight(): void
    {
        $order = [
            'round1' => [
                $this->makeSlot(1, 1, 'Heat', 20, 62, '98002E', 'F9A01B', 1, 'Heat', '98002E', 'F9A01B', false, '', movement: 2),
            ],
            'round2' => [],
        ];
        $result = $this->view->render($order, 2026);

        $this->assertStringContainsString('draft-moved-up', $result);
    }

    public function testMovedDownRowGetsRedHighlight(): void
    {
        $order = [
            'round1' => [
                $this->makeSlot(1, 1, 'Heat', 20, 62, '98002E', 'F9A01B', 1, 'Heat', '98002E', 'F9A01B', false, '', movement: -3),
            ],
            'round2' => [],
        ];
        $result = $this->view->render($order, 2026);

        $this->assertStringContainsString('draft-moved-down', $result);
    }

    public function testNoMovementNoHighlight(): void
    {
        $order = [
            'round1' => [
                $this->makeSlot(1, 1, 'Heat', 20, 62, '98002E', 'F9A01B', 1, 'Heat', '98002E', 'F9A01B', false, '', movement: 0),
            ],
            'round2' => [],
        ];
        $result = $this->view->render($order, 2026);

        $this->assertStringNotContainsString('draft-moved-up', $result);
        $this->assertStringNotContainsString('draft-moved-down', $result);
    }

    public function testLotteryResultsLabelWhenFinalized(): void
    {
        $order = $this->sampleDraftOrderWithPlayoffSeparator();
        $result = $this->view->render($order, 2026, false, true);

        $round1Start = strpos($result, 'Round 1');
        $round2Start = strpos($result, 'Round 2');
        $this->assertNotFalse($round1Start);
        $this->assertNotFalse($round2Start);

        $round1Html = substr($result, $round1Start, $round2Start - $round1Start);
        $this->assertStringContainsString('Lottery Results', $round1Html);
        $this->assertStringNotContainsString('Lottery Teams', $round1Html);
        $this->assertStringContainsString('projected-draft-order-lottery-results', $round1Html);
    }

    public function testLotteryResultsClassNotPresentWhenNotFinalized(): void
    {
        $order = $this->sampleDraftOrderWithPlayoffSeparator();
        $result = $this->view->render($order, 2026, false, false);

        $this->assertStringNotContainsString('projected-draft-order-lottery-results', $result);
    }

    public function testRound1TableHasId(): void
    {
        $result = $this->view->render($this->sampleDraftOrder(), 2026, true, false);

        $this->assertStringContainsString('id="draft-order-round1"', $result);
    }

    public function testPlayerColumnShownWhenDraftStarted(): void
    {
        $result = $this->view->render($this->sampleDraftOrder(), 2026, false, true, true);

        $this->assertStringContainsString('<th>Player</th>', $result);
    }

    public function testPlayerColumnHiddenWhenDraftNotStarted(): void
    {
        $result = $this->view->render($this->sampleDraftOrder(), 2026, false, true, false);

        $this->assertStringNotContainsString('<th>Player</th>', $result);
    }

    public function testPlayerNameRenderedWhenFinalized(): void
    {
        $order = [
            'round1' => [
                $this->makeSlot(1, 1, 'Heat', 20, 62, '98002E', 'F9A01B', 1, 'Heat', '98002E', 'F9A01B', false, '', player: 'LeBron James'),
            ],
            'round2' => [],
        ];
        $result = $this->view->render($order, 2026, false, true, true);

        $this->assertStringContainsString('LeBron James', $result);
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    private function emptyDraftOrder(): array
    {
        return ['round1' => [], 'round2' => []];
    }

    private function sampleDraftOrder(): array
    {
        return [
            'round1' => [
                $this->makeSlot(1, 1, 'Heat', 20, 62, '98002E', 'F9A01B', 1, 'Heat', '98002E', 'F9A01B', false, ''),
            ],
            'round2' => [
                $this->makeSlot(1, 1, 'Heat', 20, 62, '98002E', 'F9A01B', 1, 'Heat', '98002E', 'F9A01B', false, ''),
            ],
        ];
    }

    private function sampleDraftOrderWithPlayoffSeparator(): array
    {
        $slots = [];
        for ($i = 1; $i <= 28; $i++) {
            $slots[] = $this->makeSlot($i, $i, 'Team' . $i, 20 + $i, 62 - $i, '000000', 'FFFFFF', $i, 'Team' . $i, '000000', 'FFFFFF', false, '');
        }

        return ['round1' => $slots, 'round2' => $slots];
    }

    /**
     * @return array{pick: int, teamId: int, teamName: string, wins: int, losses: int, color1: string, color2: string, ownerId: int, ownerName: string, ownerColor1: string, ownerColor2: string, isTraded: bool, notes: string, movement: int, player: string}
     */
    private function makeSlot(
        int $pick,
        int $teamId,
        string $teamName,
        int $wins,
        int $losses,
        string $color1,
        string $color2,
        int $ownerId,
        string $ownerName,
        string $ownerColor1,
        string $ownerColor2,
        bool $isTraded,
        string $notes,
        int $movement = 0,
        string $player = '',
    ): array {
        return [
            'pick' => $pick,
            'teamId' => $teamId,
            'teamName' => $teamName,
            'wins' => $wins,
            'losses' => $losses,
            'color1' => $color1,
            'color2' => $color2,
            'ownerId' => $ownerId,
            'ownerName' => $ownerName,
            'ownerColor1' => $ownerColor1,
            'ownerColor2' => $ownerColor2,
            'isTraded' => $isTraded,
            'notes' => $notes,
            'movement' => $movement,
            'player' => $player,
        ];
    }
}
