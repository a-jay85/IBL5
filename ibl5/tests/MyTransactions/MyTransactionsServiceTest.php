<?php

declare(strict_types=1);

namespace Tests\MyTransactions;

use FreeAgency\Contracts\FreeAgencyRepositoryInterface;
use League\League;
use MyTransactions\MyTransactionsService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use TransactionHistory\Contracts\TransactionHistoryRepositoryInterface;
use Trading\Contracts\TradeOfferRepositoryInterface;

#[AllowMockObjectsWithoutExpectations]
final class MyTransactionsServiceTest extends TestCase
{
    private TransactionHistoryRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $transactionRepo;
    private TradeOfferRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $tradeOfferRepo;
    private FreeAgencyRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $faRepo;
    private TeamIdentityRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $teamIdentityRepo;

    protected function setUp(): void
    {
        $this->transactionRepo = $this->createMock(TransactionHistoryRepositoryInterface::class);
        $this->tradeOfferRepo = $this->createMock(TradeOfferRepositoryInterface::class);
        $this->faRepo = $this->createMock(FreeAgencyRepositoryInterface::class);
        $this->teamIdentityRepo = $this->createMock(TeamIdentityRepositoryInterface::class);
    }

    private function service(): MyTransactionsService
    {
        return new MyTransactionsService(
            $this->transactionRepo,
            $this->tradeOfferRepo,
            $this->faRepo,
            $this->teamIdentityRepo,
        );
    }

    /**
     * @param array{tradeofferid?: int, itemid?: int, itemtype?: string, trade_from?: string, trade_to?: string, approval?: string, created_at?: string, updated_at?: string} $overrides
     * @return array{tradeofferid: int, itemid: int, itemtype: string, trade_from: string, trade_to: string, approval: string, created_at: string, updated_at: string}
     */
    private function tradeRow(array $overrides = []): array
    {
        return array_merge([
            'tradeofferid' => 1,
            'itemid' => 100,
            'itemtype' => 'player',
            'trade_from' => 'Metros',
            'trade_to' => 'Stars',
            'approval' => 'pending',
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
        ], $overrides);
    }

    public function testNoTeamReturnsEmptySections(): void
    {
        $this->teamIdentityRepo->method('getTeamnameFromUsername')->willReturn(null);

        // No downstream queries should run for a teamless user.
        $this->transactionRepo->expects(self::never())->method('getTransactionsForTeam');
        $this->tradeOfferRepo->expects(self::never())->method('getAllTradeOffers');
        $this->faRepo->expects(self::never())->method('getOffersByTeam');

        $data = $this->service()->getPageData('orphan');

        self::assertFalse($data['hasTeam']);
        self::assertSame('', $data['teamName']);
        self::assertSame(0, $data['teamId']);
        self::assertSame([], $data['transactions']);
        self::assertSame([], $data['pendingTrades']);
        self::assertSame([], $data['pendingFaBids']);
    }

    public function testFreeAgentsSentinelIsTreatedAsNoTeam(): void
    {
        $this->teamIdentityRepo->method('getTeamnameFromUsername')
            ->willReturn(League::FREE_AGENTS_TEAM_NAME);
        $this->transactionRepo->expects(self::never())->method('getTransactionsForTeam');

        $data = $this->service()->getPageData(null);

        self::assertFalse($data['hasTeam']);
    }

    public function testResolvesTeamFromUsernameOnly(): void
    {
        // The ledger must be scoped to the RESOLVED team name, never to any input.
        $this->teamIdentityRepo->method('getTeamnameFromUsername')->willReturn('Metros');
        $this->teamIdentityRepo->method('getTidFromTeamname')->willReturn(1);
        $this->transactionRepo->expects(self::once())
            ->method('getTransactionsForTeam')
            ->with('Metros')
            ->willReturn([]);
        $this->tradeOfferRepo->method('getAllTradeOffers')->willReturn([]);
        $this->faRepo->expects(self::once())
            ->method('getOffersByTeam')
            ->with(1)
            ->willReturn([]);

        $data = $this->service()->getPageData('testgm');

        self::assertTrue($data['hasTeam']);
        self::assertSame('Metros', $data['teamName']);
        self::assertSame(1, $data['teamId']);
    }

    public function testTradeGroupingResolvesOppositeTeamAndFiltersUnrelated(): void
    {
        $this->teamIdentityRepo->method('getTeamnameFromUsername')->willReturn('Metros');
        $this->teamIdentityRepo->method('getTidFromTeamname')->willReturn(1);
        $this->transactionRepo->method('getTransactionsForTeam')->willReturn([]);
        $this->faRepo->method('getOffersByTeam')->willReturn([]);
        $this->tradeOfferRepo->method('getAllTradeOffers')->willReturn([
            // Offer 1: Metros sends to Stars (two rows, same offer) -> opposite = Stars.
            $this->tradeRow(['tradeofferid' => 1, 'trade_from' => 'Metros', 'trade_to' => 'Stars']),
            $this->tradeRow(['tradeofferid' => 1, 'trade_from' => 'Stars', 'trade_to' => 'Metros', 'itemid' => 200]),
            // Offer 2: Cougars send to Metros -> opposite = Cougars, incoming.
            $this->tradeRow(['tradeofferid' => 2, 'trade_from' => 'Cougars', 'trade_to' => 'Metros', 'approval' => 'Metros']),
            // Offer 3: unrelated (Stars <-> Cougars) -> filtered out.
            $this->tradeRow(['tradeofferid' => 3, 'trade_from' => 'Stars', 'trade_to' => 'Cougars']),
        ]);

        $trades = $this->service()->getPageData('testgm')['pendingTrades'];

        self::assertCount(2, $trades);
        self::assertSame(1, $trades[0]['tradeofferid']);
        self::assertSame('Stars', $trades[0]['oppositeTeam']);
        self::assertSame(2, $trades[1]['tradeofferid']);
        self::assertSame('Cougars', $trades[1]['oppositeTeam']);
    }

    public function testFaBidsPassThrough(): void
    {
        $this->teamIdentityRepo->method('getTeamnameFromUsername')->willReturn('Metros');
        $this->teamIdentityRepo->method('getTidFromTeamname')->willReturn(1);
        $this->transactionRepo->method('getTransactionsForTeam')->willReturn([]);
        $this->tradeOfferRepo->method('getAllTradeOffers')->willReturn([]);
        $bids = [
            ['name' => 'Player A', 'pid' => 10, 'offer1' => 1500, 'offer2' => 1600, 'offer3' => 0, 'offer4' => 0, 'offer5' => 0, 'offer6' => 0],
        ];
        $this->faRepo->method('getOffersByTeam')->willReturn($bids);

        self::assertSame($bids, $this->service()->getPageData('testgm')['pendingFaBids']);
    }

    public function testTransactionsPassThrough(): void
    {
        $this->teamIdentityRepo->method('getTeamnameFromUsername')->willReturn('Metros');
        $this->teamIdentityRepo->method('getTidFromTeamname')->willReturn(1);
        $rows = [['sid' => '5', 'catid' => '1', 'title' => 'Metros sign X', 'time' => '2025-01-01 00:00:00']];
        $this->transactionRepo->method('getTransactionsForTeam')->willReturn($rows);
        $this->tradeOfferRepo->method('getAllTradeOffers')->willReturn([]);
        $this->faRepo->method('getOffersByTeam')->willReturn([]);

        self::assertSame($rows, $this->service()->getPageData('testgm')['transactions']);
    }

    public function testUnknownTeamIdReturnsEmptySections(): void
    {
        $this->teamIdentityRepo->method('getTeamnameFromUsername')->willReturn('Ghost');
        $this->teamIdentityRepo->method('getTidFromTeamname')->willReturn(null);
        $this->transactionRepo->expects(self::never())->method('getTransactionsForTeam');

        $data = $this->service()->getPageData('testgm');

        self::assertFalse($data['hasTeam']);
    }
}
