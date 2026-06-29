<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Trading\Contracts\TradeOfferRepositoryInterface;
use Trading\Contracts\TradeAssetRepositoryInterface;
use Trading\Contracts\TradeCashRepositoryInterface;
use Trading\Contracts\TradeExecutionRepositoryInterface;
use Trading\Contracts\BuyoutLedgerRepositoryInterface;
use Trading\TradeProcessor;
use Season\Season;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * TradeProcessorTest - Tests for TradeProcessor
 */
class TradeProcessorTest extends TestCase
{
    private MockDatabase $mockDb;
    private TeamIdentityRepositoryInterface $mockCommonRepo;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->mockCommonRepo = self::createStub(TeamIdentityRepositoryInterface::class);
    }

    // ============================================
    // PROCESS TRADE TESTS
    // ============================================

    public function testProcessTradeReturnsErrorWhenNoTradeDataFound(): void
    {
        $processor = new TradeProcessor($this->mockDb, $this->mockCommonRepo);

        $result = $processor->processTrade(99999);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No trade data found', $result['error']);
    }

    // ============================================
    // CONSTRUCTOR INJECTION
    // ============================================

    /**
     * The real constructor accepts every collaborator as an injected double and
     * wires them in place of its internal `new`s — the injected offer repository
     * is the one consulted by processTrade().
     */
    public function testConstructableWithInjectedCollaborators(): void
    {
        $offerRepository = $this->createMock(TradeOfferRepositoryInterface::class);
        $offerRepository->expects($this->once())
            ->method('getTradesByOfferIdForUpdate')
            ->with(99999)
            ->willReturn([]);

        $assetRepository = self::createStub(TradeAssetRepositoryInterface::class);
        $cashRepository = self::createStub(TradeCashRepositoryInterface::class);
        $cashConsiderationRepository = self::createStub(BuyoutLedgerRepositoryInterface::class);
        $executionRepository = self::createStub(TradeExecutionRepositoryInterface::class);
        $season = self::createStub(Season::class);

        $processor = new TradeProcessor(
            $this->mockDb,
            $this->mockCommonRepo,
            '',
            $offerRepository,
            $assetRepository,
            $cashRepository,
            $cashConsiderationRepository,
            $executionRepository,
            $season,
        );

        $result = $processor->processTrade(99999);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No trade data found', $result['error']);
    }

    // ============================================
    // PER-CHANNEL LOGGER SEAM (Matrix #5, #7, #8)
    // ============================================

    /**
     * Positive seam: injected auditLogger spy receives trade_executed info.
     * Cross-talk negative: injected tradeLogger spy receives NO info call (Matrix #7).
     */
    public function testAuditLoggerSpyReceivesTradeExecutedAndTradeSpyDoesNot(): void
    {
        $auditSpy = $this->createMock(\Psr\Log\LoggerInterface::class);
        $auditSpy->expects($this->once())
            ->method('info')
            ->with('trade_executed', self::arrayHasKey('offer_id'));

        $tradeSpy = $this->createMock(\Psr\Log\LoggerInterface::class);
        $tradeSpy->expects($this->never())->method('info');

        $offerRepo = self::createStub(TradeOfferRepositoryInterface::class);
        $offerRepo->method('getTradesByOfferIdForUpdate')->willReturn([
            ['itemid' => 1, 'itemtype' => 'unknown', 'trade_from' => 'Team A', 'trade_to' => 'Team B'],
        ]);

        // Pull stubs out before new class(...) to keep self:: in outer-class scope
        $assetStub = self::createStub(TradeAssetRepositoryInterface::class);
        $cashStub = self::createStub(TradeCashRepositoryInterface::class);
        $buyoutStub = self::createStub(BuyoutLedgerRepositoryInterface::class);
        $execStub = self::createStub(TradeExecutionRepositoryInterface::class);
        $seasonStub = self::createStub(Season::class);

        $processor = new class(
            $this->mockDb,
            $this->mockCommonRepo,
            '',
            $offerRepo,
            $assetStub,
            $cashStub,
            $buyoutStub,
            $execStub,
            $seasonStub,
            auditLogger: $auditSpy,
            tradeLogger: $tradeSpy,
        ) extends TradeProcessor {
            protected function createNewsStory(string $storytitle, string $storytext): void {}
            protected function sendNotifications(array $parties, string $offeringTeamName, string $listeningTeamName, string $storytext): void {}
        };

        $result = $processor->processTrade(42);

        $this->assertTrue($result['success']);
    }

    /**
     * Seam wiring: the trade channel logger is the injected spy, proven via reflection.
     * (The natural trade-channel log paths fire inside Discord's isPhpUnit() guard
     * and cannot be triggered in unit tests without network calls — reflection is the
     * correct way to assert the injection wired correctly.)
     */
    public function testTradeLoggerSpyIsWiredToTradeChannel(): void
    {
        $tradeSpy = self::createStub(\Psr\Log\LoggerInterface::class);

        $processor = new TradeProcessor(
            $this->mockDb,
            $this->mockCommonRepo,
            tradeLogger: $tradeSpy,
        );

        $ref = new \ReflectionProperty(TradeProcessor::class, 'tradeLogger');
        $this->assertSame($tradeSpy, $ref->getValue($processor));
    }

    /**
     * Boundary (Matrix #8): constructing without logger args does not throw;
     * the fallback-to-LoggerFactory path fires and the class remains usable.
     */
    public function testConstructsWithoutLoggerArgsDoesNotThrow(): void
    {
        $processor = new TradeProcessor($this->mockDb, $this->mockCommonRepo);
        $this->assertIsObject($processor);
    }

    // ============================================
    // PARTY-NAME JOINING (Matrix #8)
    // ============================================

    public function testJoinPartyNamesTwoTeamsMatchesLegacyWording(): void
    {
        $this->assertSame('Metros and Stars', TradeProcessor::joinPartyNames(['Metros', 'Stars']));
    }

    public function testJoinPartyNamesThreeTeamsUsesOxfordlessAndList(): void
    {
        $this->assertSame('Metros, Stars and Cougars', TradeProcessor::joinPartyNames(['Metros', 'Stars', 'Cougars']));
    }

    public function testJoinPartyNamesSingleNameReturnsItVerbatim(): void
    {
        $this->assertSame('Metros', TradeProcessor::joinPartyNames(['Metros']));
    }
}
