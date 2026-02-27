<?php

declare(strict_types=1);

namespace Tests\DraftOrder;

use DraftOrder\Contracts\DraftOrderViewInterface;
use DraftOrder\DraftOrderView;
use PHPUnit\Framework\TestCase;

/**
 * @covers \DraftOrder\DraftOrderView
 */
class DraftOrderViewTest extends TestCase
{
    private DraftOrderView $view;

    protected function setUp(): void
    {
        $this->view = new DraftOrderView();
    }

    public function testImplementsViewInterface(): void
    {
        $this->assertInstanceOf(DraftOrderViewInterface::class, $this->view);
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

        $this->assertStringContainsString('draft-order-description', $result);
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
        $this->assertStringNotContainsString('draft-order-separator', $round2Html);
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

        $this->assertStringContainsString('draft-order-traded', $result);
    }

    public function testOwnPickDoesNotHaveTradedClass(): void
    {
        $order = $this->emptyDraftOrder();
        $order['round1'] = [
            $this->makeSlot(1, 1, 'Heat', 20, 62, '98002E', 'F9A01B', 1, 'Heat', '98002E', 'F9A01B', false, ''),
        ];

        $result = $this->view->render($order, 2026);

        $this->assertStringNotContainsString('draft-order-traded', $result);
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

        $this->assertMatchesRegularExpression('/background-color: #98002E;.*20-62.*<\/td><td>/s', $result);
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

    public function testUsesDataTableClass(): void
    {
        $result = $this->view->render($this->sampleDraftOrder(), 2026);

        $this->assertStringContainsString('ibl-data-table', $result);
        $this->assertStringContainsString('draft-order-table', $result);
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    /**
     * @return array{round1: list<array{pick: int, teamId: int, teamName: string, wins: int, losses: int, color1: string, color2: string, ownerId: int, ownerName: string, ownerColor1: string, ownerColor2: string, isTraded: bool, notes: string}>, round2: list<array{pick: int, teamId: int, teamName: string, wins: int, losses: int, color1: string, color2: string, ownerId: int, ownerName: string, ownerColor1: string, ownerColor2: string, isTraded: bool, notes: string}>}
     */
    private function emptyDraftOrder(): array
    {
        return ['round1' => [], 'round2' => []];
    }

    /**
     * @return array{round1: list<array{pick: int, teamId: int, teamName: string, wins: int, losses: int, color1: string, color2: string, ownerId: int, ownerName: string, ownerColor1: string, ownerColor2: string, isTraded: bool, notes: string}>, round2: list<array{pick: int, teamId: int, teamName: string, wins: int, losses: int, color1: string, color2: string, ownerId: int, ownerName: string, ownerColor1: string, ownerColor2: string, isTraded: bool, notes: string}>}
     */
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

    /**
     * @return array{round1: list<array{pick: int, teamId: int, teamName: string, wins: int, losses: int, color1: string, color2: string, ownerId: int, ownerName: string, ownerColor1: string, ownerColor2: string, isTraded: bool, notes: string}>, round2: list<array{pick: int, teamId: int, teamName: string, wins: int, losses: int, color1: string, color2: string, ownerId: int, ownerName: string, ownerColor1: string, ownerColor2: string, isTraded: bool, notes: string}>}
     */
    private function sampleDraftOrderWithPlayoffSeparator(): array
    {
        $slots = [];
        for ($i = 1; $i <= 28; $i++) {
            $slots[] = $this->makeSlot($i, $i, 'Team' . $i, 20 + $i, 62 - $i, '000000', 'FFFFFF', $i, 'Team' . $i, '000000', 'FFFFFF', false, '');
        }

        return ['round1' => $slots, 'round2' => $slots];
    }

    /**
     * @return array{pick: int, teamId: int, teamName: string, wins: int, losses: int, color1: string, color2: string, ownerId: int, ownerName: string, ownerColor1: string, ownerColor2: string, isTraded: bool, notes: string}
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
        ];
    }
}
