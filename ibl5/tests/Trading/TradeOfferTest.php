<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Trading\CashTransactionHandler;
use Trading\Contracts\TradeOfferRepositoryInterface;
use Trading\Contracts\TradeAssetRepositoryInterface;
use Trading\Contracts\TradeCashRepositoryInterface;
use Trading\TradeOffer;
use Trading\TradeValidator;
use Season\Season;
use Discord\Discord;

/**
 * @covers \Trading\TradeOffer
 */
class TradeOfferTest extends TestCase
{
    /**
     * Create a TradeOffer with injected test doubles via anonymous subclass.
     */
    private function makeTradeOffer(
        TradeOfferRepositoryInterface $offerRepository,
        TradeAssetRepositoryInterface $assetRepository,
        TradeValidator $validator,
        CashTransactionHandler $cashHandler,
        \Services\CommonMysqliRepository $commonRepo,
        Season $season,
        ?Discord $discord = null,
        ?TradeCashRepositoryInterface $cashRepo = null,
    ): TradeOffer {
        $cashRepoStub = $cashRepo ?? $this->createStub(TradeCashRepositoryInterface::class);

        return new class ($offerRepository, $assetRepository, $validator, $cashHandler, $commonRepo, $season, $discord, $cashRepoStub) extends TradeOffer {
            public function __construct(
                TradeOfferRepositoryInterface $offerRepository,
                TradeAssetRepositoryInterface $assetRepository,
                TradeValidator $validator,
                CashTransactionHandler $cashHandler,
                \Services\CommonMysqliRepository $commonRepo,
                Season $season,
                ?Discord $discord,
                TradeCashRepositoryInterface $cashRepo,
            ) {
                // Skip parent constructor — inject directly
                $this->db = new class extends \mysqli {
                    public function __construct()
                    {
                    }
                };
                $this->offerRepository = $offerRepository;
                $this->assetRepository = $assetRepository;
                $this->cashRepository = $cashRepo;
                $this->commonRepository = $commonRepo;
                $this->season = $season;
                $this->cashHandler = $cashHandler;
                $this->validator = $validator;
                $this->discord = $discord;
            }
        };
    }

    /**
     * Build minimal valid trade data.
     *
     * @return array{offeringTeam: string, listeningTeam: string, switchCounter: int, fieldsCounter: int, check: array<int, string|null>, index: array<int, string>, type: array<int, string>, contract: array<int, string>, userSendsCash: array<int, int>, partnerSendsCash: array<int, int>}
     */
    private function makeTradeData(int $switchCounter = 0, int $fieldsCounter = 0): array
    {
        return [
            'offeringTeam' => 'Lakers',
            'listeningTeam' => 'Celtics',
            'switchCounter' => $switchCounter,
            'fieldsCounter' => $fieldsCounter,
            'check' => [],
            'index' => [],
            'type' => [],
            'contract' => [],
            'userSendsCash' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0],
            'partnerSendsCash' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0],
        ];
    }

    /**
     * @return array{TradeOfferRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject, TradeAssetRepositoryInterface&\PHPUnit\Framework\MockObject\Stub, TradeValidator&\PHPUnit\Framework\MockObject\Stub, CashTransactionHandler&\PHPUnit\Framework\MockObject\Stub, \Services\CommonMysqliRepository&\PHPUnit\Framework\MockObject\Stub, Season&\PHPUnit\Framework\MockObject\Stub}
     */
    private function makeStubs(): array
    {
        $offerRepository = $this->createMock(TradeOfferRepositoryInterface::class);
        $assetRepository = $this->createStub(TradeAssetRepositoryInterface::class);
        $validator = $this->createStub(TradeValidator::class);
        $cashHandler = $this->createStub(CashTransactionHandler::class);
        $commonRepo = $this->createStub(\Services\CommonMysqliRepository::class);
        $season = $this->createStub(Season::class);

        return [$offerRepository, $assetRepository, $validator, $cashHandler, $commonRepo, $season];
    }

    // ── Cash validation ──────────────────────────────────────────

    public function testCreateTradeOfferFailsWhenCashValidationRejects(): void
    {
        [$offerRepository, $assetRepository, $validator, $cashHandler, $commonRepo, $season] = $this->makeStubs();

        $offerRepository->expects($this->once())->method('generateNextTradeOfferId')->willReturn(1);
        $validator->method('validateMinimumCashAmounts')->willReturn([
            'valid' => false,
            'error' => 'Cash too low',
        ]);

        $offer = $this->makeTradeOffer($offerRepository, $assetRepository, $validator, $cashHandler, $commonRepo, $season);
        $result = $offer->createTradeOffer($this->makeTradeData());

        $this->assertFalse($result['success']);
        $this->assertSame('Cash too low', $result['error']);
    }

    public function testCreateTradeOfferPassesCashValidation(): void
    {
        [$offerRepository, $assetRepository, $validator, $cashHandler, $commonRepo, $season] = $this->makeStubs();

        $offerRepository->expects($this->once())->method('generateNextTradeOfferId')->willReturn(1);
        $validator->method('validateMinimumCashAmounts')->willReturn(['valid' => true, 'error' => null]);
        $validator->method('validateSalaryCaps')->willReturn([
            'valid' => false,
            'errors' => ['Over hard cap'],
            'userPostTradeCapTotal' => 999999,
            'partnerPostTradeCapTotal' => 0,
        ]);
        $validator->method('getCurrentSeasonCashConsiderations')->willReturn([
            'cashSentToThem' => 0,
            'cashSentToMe' => 0,
        ]);
        $commonRepo->method('getTidFromTeamname')->willReturn(1);
        $cashHandler->method('hasCashInTrade')->willReturn(false);

        $offer = $this->makeTradeOffer($offerRepository, $assetRepository, $validator, $cashHandler, $commonRepo, $season);
        $result = $offer->createTradeOffer($this->makeTradeData());

        // Cash validation passed, but salary cap failed
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('errors', $result);
    }

    // ── Salary cap ───────────────────────────────────────────────

    public function testCreateTradeOfferFailsOnCapExceeded(): void
    {
        [$offerRepository, $assetRepository, $validator, $cashHandler, $commonRepo, $season] = $this->makeStubs();

        $offerRepository->expects($this->once())->method('generateNextTradeOfferId')->willReturn(1);
        $validator->method('validateMinimumCashAmounts')->willReturn(['valid' => true, 'error' => null]);
        $validator->method('validateSalaryCaps')->willReturn([
            'valid' => false,
            'errors' => ['Over hard cap'],
            'userPostTradeCapTotal' => 999999,
            'partnerPostTradeCapTotal' => 0,
        ]);
        $validator->method('getCurrentSeasonCashConsiderations')->willReturn([
            'cashSentToThem' => 0,
            'cashSentToMe' => 0,
        ]);
        $commonRepo->method('getTidFromTeamname')->willReturn(1);
        $cashHandler->method('hasCashInTrade')->willReturn(false);

        $offer = $this->makeTradeOffer($offerRepository, $assetRepository, $validator, $cashHandler, $commonRepo, $season);
        $result = $offer->createTradeOffer($this->makeTradeData());

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('capData', $result);
    }

    public function testCreateTradeOfferIncludesCapDataOnFailure(): void
    {
        [$offerRepository, $assetRepository, $validator, $cashHandler, $commonRepo, $season] = $this->makeStubs();

        $offerRepository->expects($this->once())->method('generateNextTradeOfferId')->willReturn(1);
        $validator->method('validateMinimumCashAmounts')->willReturn(['valid' => true, 'error' => null]);
        $capResult = [
            'valid' => false,
            'errors' => ['Over hard cap'],
            'userPostTradeCapTotal' => 150000,
            'partnerPostTradeCapTotal' => 0,
        ];
        $validator->method('validateSalaryCaps')->willReturn($capResult);
        $validator->method('getCurrentSeasonCashConsiderations')->willReturn([
            'cashSentToThem' => 0,
            'cashSentToMe' => 0,
        ]);
        $commonRepo->method('getTidFromTeamname')->willReturn(1);
        $cashHandler->method('hasCashInTrade')->willReturn(false);

        $offer = $this->makeTradeOffer($offerRepository, $assetRepository, $validator, $cashHandler, $commonRepo, $season);
        $result = $offer->createTradeOffer($this->makeTradeData());

        $this->assertFalse($result['success']);
        $this->assertSame($capResult, $result['capData']);
    }

    // ── Roster limits ────────────────────────────────────────────

    public function testCreateTradeOfferFailsOnRosterLimit(): void
    {
        [$offerRepository, $assetRepository, $validator, $cashHandler, $commonRepo, $season] = $this->makeStubs();

        $offerRepository->expects($this->once())->method('generateNextTradeOfferId')->willReturn(1);
        $validator->method('validateMinimumCashAmounts')->willReturn(['valid' => true, 'error' => null]);
        $validator->method('validateSalaryCaps')->willReturn([
            'valid' => true,
            'errors' => [],
            'userPostTradeCapTotal' => 50000,
            'partnerPostTradeCapTotal' => 50000,
        ]);
        $validator->method('validateRosterLimits')->willReturn([
            'valid' => false,
            'errors' => ['Over roster limit'],
        ]);
        $validator->method('getCurrentSeasonCashConsiderations')->willReturn([
            'cashSentToThem' => 0,
            'cashSentToMe' => 0,
        ]);
        $commonRepo->method('getTidFromTeamname')->willReturn(1);
        $cashHandler->method('hasCashInTrade')->willReturn(false);

        $offer = $this->makeTradeOffer($offerRepository, $assetRepository, $validator, $cashHandler, $commonRepo, $season);
        $result = $offer->createTradeOffer($this->makeTradeData());

        $this->assertFalse($result['success']);
        $this->assertSame(['Over roster limit'], $result['errors']);
    }

    public function testCreateTradeOfferCountsUserPlayersSent(): void
    {
        $offerRepository = $this->createMock(TradeOfferRepositoryInterface::class);
        $assetRepository = $this->createStub(TradeAssetRepositoryInterface::class);
        $validator = $this->createMock(TradeValidator::class);
        $cashHandler = $this->createStub(CashTransactionHandler::class);
        $commonRepo = $this->createStub(\Services\CommonMysqliRepository::class);
        $season = $this->createStub(Season::class);

        $offerRepository->expects($this->once())->method('generateNextTradeOfferId')->willReturn(1);
        $validator->expects($this->once())->method('validateMinimumCashAmounts')->willReturn(['valid' => true, 'error' => null]);
        $validator->expects($this->once())->method('validateSalaryCaps')->willReturn([
            'valid' => true,
            'errors' => [],
            'userPostTradeCapTotal' => 50000,
            'partnerPostTradeCapTotal' => 50000,
        ]);
        $validator->expects($this->once())->method('getCurrentSeasonCashConsiderations')->willReturn([
            'cashSentToThem' => 0,
            'cashSentToMe' => 0,
        ]);
        $commonRepo->method('getTidFromTeamname')->willReturn(1);
        $cashHandler->method('hasCashInTrade')->willReturn(false);

        // 2 user players checked, 1 partner player
        $tradeData = $this->makeTradeData(2, 3);
        $tradeData['check'] = [0 => 'on', 1 => 'on', 2 => 'on'];
        $tradeData['type'] = [0 => '1', 1 => '1', 2 => '1'];
        $tradeData['index'] = [0 => '100', 1 => '200', 2 => '300'];
        $tradeData['contract'] = [0 => '500', 1 => '600', 2 => '700'];

        $validator->expects($this->once())->method('validateRosterLimits')
            ->with(
                $this->anything(),
                $this->anything(),
                2, // 2 user players sent (indices 0,1 < switchCounter=2)
                1, // 1 partner player sent (index 2 >= switchCounter=2)
            )
            ->willReturn(['valid' => true, 'errors' => []]);

        $offerRepository->method('insertTradeItem')->willReturn(1);
        $assetRepository->method('getDraftPickById')->willReturn(null);
        $assetRepository->method('getPlayerById')->willReturn(['name' => 'Player', 'pos' => 'PG']);

        $offer = $this->makeTradeOffer($offerRepository, $assetRepository, $validator, $cashHandler, $commonRepo, $season, null);
        $result = $offer->createTradeOffer($tradeData);

        $this->assertTrue($result['success']);
    }

    // ── Insertion / happy path ────────────────────────────────────

    public function testCreateTradeOfferCallsGenerateTradeOfferId(): void
    {
        [$offerRepository, $assetRepository, $validator, $cashHandler, $commonRepo, $season] = $this->makeStubs();

        $offerRepository->expects($this->once())->method('generateNextTradeOfferId')->willReturn(42);
        $validator->method('validateMinimumCashAmounts')->willReturn(['valid' => true, 'error' => null]);
        $validator->method('validateSalaryCaps')->willReturn([
            'valid' => true,
            'errors' => [],
            'userPostTradeCapTotal' => 50000,
            'partnerPostTradeCapTotal' => 50000,
        ]);
        $validator->method('validateRosterLimits')->willReturn(['valid' => true, 'errors' => []]);
        $validator->method('getCurrentSeasonCashConsiderations')->willReturn([
            'cashSentToThem' => 0,
            'cashSentToMe' => 0,
        ]);
        $commonRepo->method('getTidFromTeamname')->willReturn(1);
        $cashHandler->method('hasCashInTrade')->willReturn(false);

        $offer = $this->makeTradeOffer($offerRepository, $assetRepository, $validator, $cashHandler, $commonRepo, $season, null);
        $result = $offer->createTradeOffer($this->makeTradeData());

        $this->assertTrue($result['success']);
        $this->assertSame(42, $result['tradeOfferId']);
    }

    public function testCreateTradeOfferReturnsSuccessOnHappyPath(): void
    {
        [$offerRepository, $assetRepository, $validator, $cashHandler, $commonRepo, $season] = $this->makeStubs();

        $offerRepository->expects($this->once())->method('generateNextTradeOfferId')->willReturn(1);
        $validator->method('validateMinimumCashAmounts')->willReturn(['valid' => true, 'error' => null]);
        $validator->method('validateSalaryCaps')->willReturn([
            'valid' => true,
            'errors' => [],
            'userPostTradeCapTotal' => 50000,
            'partnerPostTradeCapTotal' => 50000,
        ]);
        $validator->method('validateRosterLimits')->willReturn(['valid' => true, 'errors' => []]);
        $validator->method('getCurrentSeasonCashConsiderations')->willReturn([
            'cashSentToThem' => 0,
            'cashSentToMe' => 0,
        ]);
        $commonRepo->method('getTidFromTeamname')->willReturn(1);
        $cashHandler->method('hasCashInTrade')->willReturn(false);

        $offer = $this->makeTradeOffer($offerRepository, $assetRepository, $validator, $cashHandler, $commonRepo, $season, null);
        $result = $offer->createTradeOffer($this->makeTradeData());

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('tradeText', $result);
        $this->assertArrayHasKey('tradeOfferId', $result);
    }

    public function testCreateTradeOfferCallsInsertTradeItemPerCheckedItem(): void
    {
        [$offerRepository, $assetRepository, $validator, $cashHandler, $commonRepo, $season] = $this->makeStubs();

        $offerRepository->expects($this->once())->method('generateNextTradeOfferId')->willReturn(1);
        $validator->method('validateMinimumCashAmounts')->willReturn(['valid' => true, 'error' => null]);
        $validator->method('validateSalaryCaps')->willReturn([
            'valid' => true,
            'errors' => [],
            'userPostTradeCapTotal' => 50000,
            'partnerPostTradeCapTotal' => 50000,
        ]);
        $validator->method('validateRosterLimits')->willReturn(['valid' => true, 'errors' => []]);
        $validator->method('getCurrentSeasonCashConsiderations')->willReturn([
            'cashSentToThem' => 0,
            'cashSentToMe' => 0,
        ]);
        $commonRepo->method('getTidFromTeamname')->willReturn(1);
        $cashHandler->method('hasCashInTrade')->willReturn(false);

        // 2 checked items
        $tradeData = $this->makeTradeData(1, 2);
        $tradeData['check'] = [0 => 'on', 1 => 'on'];
        $tradeData['type'] = [0 => '1', 1 => '1'];
        $tradeData['index'] = [0 => '100', 1 => '200'];
        $tradeData['contract'] = [0 => '500', 1 => '600'];

        $offerRepository->expects($this->exactly(2))->method('insertTradeItem')->willReturn(1);
        $assetRepository->method('getPlayerById')->willReturn(['name' => 'Player', 'pos' => 'PG']);

        $offer = $this->makeTradeOffer($offerRepository, $assetRepository, $validator, $cashHandler, $commonRepo, $season, null);
        $result = $offer->createTradeOffer($tradeData);

        $this->assertTrue($result['success']);
    }

    // ── Special cases ────────────────────────────────────────────

    public function testCreateTradeOfferDoesNotNotifyOnFailure(): void
    {
        [$offerRepository, $assetRepository, $validator, $cashHandler, $commonRepo, $season] = $this->makeStubs();

        $offerRepository->expects($this->once())->method('generateNextTradeOfferId')->willReturn(1);
        $validator->method('validateMinimumCashAmounts')->willReturn([
            'valid' => false,
            'error' => 'Cash too low',
        ]);

        // Discord should never be called
        $discord = $this->createMock(Discord::class);
        $discord->expects($this->never())->method($this->anything());

        $offer = $this->makeTradeOffer($offerRepository, $assetRepository, $validator, $cashHandler, $commonRepo, $season, $discord);
        $result = $offer->createTradeOffer($this->makeTradeData());

        $this->assertFalse($result['success']);
    }

    public function testCreateTradeOfferSelfTradeZeroesCapSent(): void
    {
        $offerRepository = $this->createMock(TradeOfferRepositoryInterface::class);
        $assetRepository = $this->createStub(TradeAssetRepositoryInterface::class);
        $validator = $this->createMock(TradeValidator::class);
        $cashHandler = $this->createStub(CashTransactionHandler::class);
        $commonRepo = $this->createStub(\Services\CommonMysqliRepository::class);
        $season = $this->createStub(Season::class);

        $offerRepository->expects($this->once())->method('generateNextTradeOfferId')->willReturn(1);
        $validator->expects($this->once())->method('validateMinimumCashAmounts')->willReturn(['valid' => true, 'error' => null]);
        $validator->expects($this->once())->method('getCurrentSeasonCashConsiderations')->willReturn([
            'cashSentToThem' => 0,
            'cashSentToMe' => 0,
        ]);
        $commonRepo->method('getTidFromTeamname')->willReturn(1);

        // Cap validation: for self-trade, sent amounts should be zeroed
        $validator->expects($this->once())->method('validateSalaryCaps')
            ->with($this->callback(static function (array $capData): bool {
                return $capData['userCapSentToPartner'] === 0
                    && $capData['partnerCapSentToUser'] === 0;
            }))
            ->willReturn([
                'valid' => true,
                'errors' => [],
                'userPostTradeCapTotal' => 50000,
                'partnerPostTradeCapTotal' => 50000,
            ]);
        $validator->expects($this->once())->method('validateRosterLimits')->willReturn(['valid' => true, 'errors' => []]);
        $cashHandler->method('hasCashInTrade')->willReturn(false);

        // Self-trade: same team on both sides
        $tradeData = $this->makeTradeData();
        $tradeData['offeringTeam'] = 'Lakers';
        $tradeData['listeningTeam'] = 'Lakers';

        $offer = $this->makeTradeOffer($offerRepository, $assetRepository, $validator, $cashHandler, $commonRepo, $season, null);
        $result = $offer->createTradeOffer($tradeData);

        $this->assertTrue($result['success']);
    }

    public function testCreateTradeOfferNullDiscordSkipsNotification(): void
    {
        [$offerRepository, $assetRepository, $validator, $cashHandler, $commonRepo, $season] = $this->makeStubs();

        $offerRepository->expects($this->once())->method('generateNextTradeOfferId')->willReturn(1);
        $validator->method('validateMinimumCashAmounts')->willReturn(['valid' => true, 'error' => null]);
        $validator->method('validateSalaryCaps')->willReturn([
            'valid' => true,
            'errors' => [],
            'userPostTradeCapTotal' => 50000,
            'partnerPostTradeCapTotal' => 50000,
        ]);
        $validator->method('validateRosterLimits')->willReturn(['valid' => true, 'errors' => []]);
        $validator->method('getCurrentSeasonCashConsiderations')->willReturn([
            'cashSentToThem' => 0,
            'cashSentToMe' => 0,
        ]);
        $commonRepo->method('getTidFromTeamname')->willReturn(1);
        $cashHandler->method('hasCashInTrade')->willReturn(false);

        // Null discord — should not throw
        $offer = $this->makeTradeOffer($offerRepository, $assetRepository, $validator, $cashHandler, $commonRepo, $season, null);
        $result = $offer->createTradeOffer($this->makeTradeData());

        $this->assertTrue($result['success']);
    }
}
