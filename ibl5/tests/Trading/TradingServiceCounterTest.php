<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Trading\TradingService;
use Trading\Contracts\TradeOfferRepositoryInterface;
use Trading\Contracts\TradeAssetRepositoryInterface;
use Trading\Contracts\TradeFormRepositoryInterface;
use Trading\Contracts\TradeCashRepositoryInterface;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * Tests for TradingService::buildCounterFormData() — the counter-offer swap/pre-fill logic.
 *
 * Pure method: maps a source offer's assets + cash into the make-offer form's
 * session pre-fill structure (checkedItems keyed "itemtype:id", year-indexed cash).
 */
class TradingServiceCounterTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    private function createService(): TradingService
    {
        return new TradingService(
            self::createStub(TradeOfferRepositoryInterface::class),
            self::createStub(TradeAssetRepositoryInterface::class),
            self::createStub(TradeFormRepositoryInterface::class),
            self::createStub(TeamIdentityRepositoryInterface::class),
            $this->mockDb,
            self::createStub(TradeCashRepositoryInterface::class),
        );
    }

    /**
     * @return array{tradeofferid: int, itemid: int, itemtype: string, trade_from: string, trade_to: string, approval: string, created_at: string, updated_at: string}
     */
    private function infoRow(int $offerId, int $itemId, string $itemType, string $from, string $to): array
    {
        return [
            'tradeofferid' => $offerId,
            'itemid' => $itemId,
            'itemtype' => $itemType,
            'trade_from' => $from,
            'trade_to' => $to,
            'approval' => '',
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ];
    }

    /**
     * @return array{trade_offer_id: int, sending_team: string, receiving_team: string, salary_yr1: ?int, salary_yr2: ?int, salary_yr3: ?int, salary_yr4: ?int, salary_yr5: ?int, salary_yr6: ?int}
     */
    private function cashRow(int $offerId, string $sending, string $receiving, ?int $yr1 = 0, ?int $yr2 = 0, ?int $yr3 = 0, ?int $yr4 = 0, ?int $yr5 = 0, ?int $yr6 = 0): array
    {
        return [
            'trade_offer_id' => $offerId,
            'sending_team' => $sending,
            'receiving_team' => $receiving,
            'salary_yr1' => $yr1,
            'salary_yr2' => $yr2,
            'salary_yr3' => $yr3,
            'salary_yr4' => $yr4,
            'salary_yr5' => $yr5,
            'salary_yr6' => $yr6,
        ];
    }

    public function testHappyPathTwoPlayersChecksBothNoCash(): void
    {
        $service = $this->createService();
        $rows = [
            $this->infoRow(1, 4, '1', 'Stars', 'Metros'),
            $this->infoRow(1, 2, '1', 'Metros', 'Stars'),
        ];

        $result = $service->buildCounterFormData($rows, [], 1, 'Metros');

        $this->assertSame(['1:4' => true, '1:2' => true], $result['checkedItems']);
        $this->assertSame([], $result['userSendsCash']);
        $this->assertSame([], $result['partnerSendsCash']);
    }

    public function testPicksOnlyOfferUsesPickKeys(): void
    {
        $service = $this->createService();
        $rows = [
            $this->infoRow(1, 7, '0', 'Stars', 'Metros'),
            $this->infoRow(1, 9, '0', 'Metros', 'Stars'),
        ];

        $result = $service->buildCounterFormData($rows, [], 1, 'Metros');

        $this->assertSame(['0:7' => true, '0:9' => true], $result['checkedItems']);
    }

    public function testCashOnlyOfferSplitsBySendingTeam(): void
    {
        $service = $this->createService();
        $cashMap = [
            '5:Metros' => $this->cashRow(5, 'Metros', 'Stars', yr1: 100),
            '5:Stars' => $this->cashRow(5, 'Stars', 'Metros', yr2: 50),
        ];

        $result = $service->buildCounterFormData([], $cashMap, 5, 'Metros');

        $this->assertSame([], $result['checkedItems']);
        $this->assertSame(100, $result['userSendsCash'][1]);
        $this->assertSame(50, $result['partnerSendsCash'][2]);
    }

    public function testEmptyOfferProducesEmptyData(): void
    {
        $service = $this->createService();

        $result = $service->buildCounterFormData([], [], 99, 'Metros');

        $this->assertSame([], $result['checkedItems']);
        $this->assertSame([], $result['userSendsCash']);
        $this->assertSame([], $result['partnerSendsCash']);
    }

    public function testMixedPlayerPickCashAllRepresentedOnCorrectSide(): void
    {
        $service = $this->createService();
        $rows = [
            $this->infoRow(3, 4, '1', 'Stars', 'Metros'),
            $this->infoRow(3, 7, '0', 'Metros', 'Stars'),
            // The cash row itemtype is 'cash' — must NOT produce a checkedItems key.
            $this->infoRow(3, 10101, 'cash', 'Metros', 'Stars'),
        ];
        $cashMap = [
            '3:Metros' => $this->cashRow(3, 'Metros', 'Stars', yr1: 200),
            '3:Stars' => $this->cashRow(3, 'Stars', 'Metros', yr3: 75),
        ];

        $result = $service->buildCounterFormData($rows, $cashMap, 3, 'Metros');

        $this->assertSame(['1:4' => true, '0:7' => true], $result['checkedItems']);
        $this->assertSame(200, $result['userSendsCash'][1]);
        $this->assertSame(75, $result['partnerSendsCash'][3]);
    }

    public function testNullAndZeroCashAmountsSkipped(): void
    {
        $service = $this->createService();
        $cashMap = [
            '5:Metros' => $this->cashRow(5, 'Metros', 'Stars', yr1: 0, yr2: null, yr3: 80),
        ];

        $result = $service->buildCounterFormData([], $cashMap, 5, 'Metros');

        $this->assertArrayNotHasKey(1, $result['userSendsCash']);
        $this->assertArrayNotHasKey(2, $result['userSendsCash']);
        $this->assertSame(80, $result['userSendsCash'][3]);
    }

    public function testCashFromOtherOfferIdIgnored(): void
    {
        $service = $this->createService();
        $cashMap = [
            '5:Metros' => $this->cashRow(5, 'Metros', 'Stars', yr1: 100),
            '6:Metros' => $this->cashRow(6, 'Metros', 'Stars', yr1: 999),
        ];

        $result = $service->buildCounterFormData([], $cashMap, 5, 'Metros');

        $this->assertSame([1 => 100], $result['userSendsCash']);
    }
}
