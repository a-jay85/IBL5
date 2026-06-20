<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;
use Trading\CashTransactionHandler;
use Trading\Contracts\TradeOfferRepositoryInterface;
use Trading\Contracts\TradeAssetRepositoryInterface;
use Trading\Contracts\TradeCashRepositoryInterface;
use Trading\Contracts\BuyoutLedgerRepositoryInterface;
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
        \Repositories\Contracts\TeamIdentityRepositoryInterface $commonRepo,
        Season $season,
        ?Discord $discord = null,
        ?TradeCashRepositoryInterface $cashRepo = null,
        ?BuyoutLedgerRepositoryInterface $cashConsiderationRepo = null,
    ): TradeOffer {
        $cashRepoStub = $cashRepo ?? self::createStub(TradeCashRepositoryInterface::class);
        $cashConsiderationRepoStub = $cashConsiderationRepo ?? self::createStub(BuyoutLedgerRepositoryInterface::class);

        return new class ($offerRepository, $assetRepository, $validator, $cashHandler, $commonRepo, $season, $discord, $cashRepoStub, $cashConsiderationRepoStub) extends TradeOffer {
            // @phpstan-ignore constructor.missingParentCall (intentional: TradeOffer's real constructor wires concrete Season/TradeValidator/CashTransactionHandler/Discord against a live DB; this double skips it to inject test stubs directly)
            public function __construct(
                TradeOfferRepositoryInterface $offerRepository,
                TradeAssetRepositoryInterface $assetRepository,
                TradeValidator $validator,
                CashTransactionHandler $cashHandler,
                \Repositories\Contracts\TeamIdentityRepositoryInterface $commonRepo,
                Season $season,
                ?Discord $discord,
                TradeCashRepositoryInterface $cashRepo,
                BuyoutLedgerRepositoryInterface $cashConsiderationRepo,
            ) {
                // Skip parent constructor — inject directly
                $this->db = new MockDatabase();
                $this->offerRepository = $offerRepository;
                $this->assetRepository = $assetRepository;
                $this->cashRepository = $cashRepo;
                $this->cashConsiderationRepository = $cashConsiderationRepo;
                $this->commonRepository = $commonRepo;
                $this->season = $season;
                $this->cashHandler = $cashHandler;
                $this->validator = $validator;
                $this->discord = $discord;
                $this->logger = \Logging\LoggerFactory::getChannel('trade');
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
     * @return array{TradeOfferRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject, TradeAssetRepositoryInterface&\PHPUnit\Framework\MockObject\Stub, TradeValidator&\PHPUnit\Framework\MockObject\Stub, CashTransactionHandler&\PHPUnit\Framework\MockObject\Stub, \Repositories\Contracts\TeamIdentityRepositoryInterface&\PHPUnit\Framework\MockObject\Stub, Season&\PHPUnit\Framework\MockObject\Stub}
     */
    private function makeStubs(): array
    {
        $offerRepository = $this->createMock(TradeOfferRepositoryInterface::class);
        $assetRepository = self::createStub(TradeAssetRepositoryInterface::class);
        $validator = self::createStub(TradeValidator::class);
        $cashHandler = self::createStub(CashTransactionHandler::class);
        $commonRepo = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $season = self::createStub(Season::class);

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
        $assetRepository = self::createStub(TradeAssetRepositoryInterface::class);
        $validator = $this->createMock(TradeValidator::class);
        $cashHandler = self::createStub(CashTransactionHandler::class);
        $commonRepo = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $season = self::createStub(Season::class);

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
                self::anything(),
                self::anything(),
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
        $discord->expects($this->never())->method(self::anything());

        $offer = $this->makeTradeOffer($offerRepository, $assetRepository, $validator, $cashHandler, $commonRepo, $season, $discord);
        $result = $offer->createTradeOffer($this->makeTradeData());

        $this->assertFalse($result['success']);
    }

    public function testCreateTradeOfferSelfTradeZeroesCapSent(): void
    {
        $offerRepository = $this->createMock(TradeOfferRepositoryInterface::class);
        $assetRepository = self::createStub(TradeAssetRepositoryInterface::class);
        $validator = $this->createMock(TradeValidator::class);
        $cashHandler = self::createStub(CashTransactionHandler::class);
        $commonRepo = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $season = self::createStub(Season::class);

        $offerRepository->expects($this->once())->method('generateNextTradeOfferId')->willReturn(1);
        $validator->expects($this->once())->method('validateMinimumCashAmounts')->willReturn(['valid' => true, 'error' => null]);
        $validator->expects($this->once())->method('getCurrentSeasonCashConsiderations')->willReturn([
            'cashSentToThem' => 0,
            'cashSentToMe' => 0,
        ]);
        $commonRepo->method('getTidFromTeamname')->willReturn(1);

        // Cap validation: for self-trade, sent amounts should be zeroed
        $validator->expects($this->once())->method('validateSalaryCaps')
            ->with(self::callback(static function (array $capData): bool {
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

    // ── Constructor injection (real ctor, no anonymous subclass) ──

    /**
     * The real constructor accepts every collaborator as an injected double and
     * wires them in place of its internal `new`s — provable without a live DB.
     */
    public function testConstructableWithInjectedCollaborators(): void
    {
        $offerRepository = $this->createMock(TradeOfferRepositoryInterface::class);
        $offerRepository->expects($this->once())
            ->method('generateNextTradeOfferId')
            ->willReturn(99);

        $assetRepository = self::createStub(TradeAssetRepositoryInterface::class);
        $cashRepository = self::createStub(TradeCashRepositoryInterface::class);
        $cashConsiderationRepository = self::createStub(BuyoutLedgerRepositoryInterface::class);
        $season = self::createStub(Season::class);
        $commonRepo = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);

        // Injected validator short-circuits createTradeOffer, proving it is the
        // collaborator actually consulted (not an internally-constructed one).
        $validator = self::createStub(TradeValidator::class);
        $validator->method('validateMinimumCashAmounts')
            ->willReturn(['valid' => false, 'error' => 'injected validator reached']);

        $offer = new TradeOffer(
            new MockDatabase(),
            $commonRepo,
            '',
            $offerRepository,
            $assetRepository,
            $cashRepository,
            $cashConsiderationRepository,
            $season,
            $validator,
        );

        $result = $offer->createTradeOffer($this->makeTradeData());

        $this->assertFalse($result['success']);
        $this->assertSame('injected validator reached', $result['error']);
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

    // ── Characterization pin — PRE-IMPL — do not edit ────────────

    /**
     * Exposes the protected calculateSalaryCapData() for the characterization pin.
     * The test double sets only the four props that method reads; it never sets
     * tradeCapCalculator (added later) — the Phase-4 delegator must tolerate this.
     */
    private function makeCapPinSubject(
        \Repositories\Contracts\TeamIdentityRepositoryInterface $commonRepo,
        BuyoutLedgerRepositoryInterface $cashConsiderationRepo,
        Season $season,
        TradeValidator $validator
    ): TradeOfferCapPinDouble {
        return new TradeOfferCapPinDouble($commonRepo, $cashConsiderationRepo, $season, $validator);
    }

    /**
     * @return array{cy: int, salary_yr1: int, salary_yr2: int, salary_yr3: int, salary_yr4: int, salary_yr5: int, salary_yr6: int}
     */
    private function makeDiscriminatorRow(): array
    {
        // yr1=100, yr2=500: yr1 ≠ yr2 proves cy-offset changes the sum
        return ['cy' => 1, 'salary_yr1' => 100, 'salary_yr2' => 500, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0];
    }

    /** V1: advances=true → cy 1→2 → yr2=500 per team */
    public function testCapPinCalculateSalaryCapDataAdvancesTrue(): void
    {
        $commonRepo = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $commonRepo->method('getTidFromTeamname')->willReturn(1);
        $cashConsiderationRepo = self::createStub(BuyoutLedgerRepositoryInterface::class);
        $cashConsiderationRepo->method('getTeamCashForSalary')->willReturn([$this->makeDiscriminatorRow()]);
        $season = self::createStub(Season::class);
        $season->method('advancesContractYears')->willReturn(true);
        $validator = self::createStub(TradeValidator::class);
        $validator->method('getCurrentSeasonCashConsiderations')->willReturn(['cashSentToThem' => 0, 'cashSentToMe' => 0]);

        $subject = $this->makeCapPinSubject($commonRepo, $cashConsiderationRepo, $season, $validator);

        $this->assertSame(
            ['userCurrentSeasonCapTotal' => 500, 'partnerCurrentSeasonCapTotal' => 500, 'userCapSentToPartner' => 0, 'partnerCapSentToUser' => 0],
            $subject->exposeCalculateSalaryCapData($this->makeTradeData())
        );
    }

    /** V2: advances=false → cy stays 1 → yr1=100 per team */
    public function testCapPinCalculateSalaryCapDataAdvancesFalse(): void
    {
        $commonRepo = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $commonRepo->method('getTidFromTeamname')->willReturn(1);
        $cashConsiderationRepo = self::createStub(BuyoutLedgerRepositoryInterface::class);
        $cashConsiderationRepo->method('getTeamCashForSalary')->willReturn([$this->makeDiscriminatorRow()]);
        $season = self::createStub(Season::class);
        $season->method('advancesContractYears')->willReturn(false);
        $validator = self::createStub(TradeValidator::class);
        $validator->method('getCurrentSeasonCashConsiderations')->willReturn(['cashSentToThem' => 0, 'cashSentToMe' => 0]);

        $subject = $this->makeCapPinSubject($commonRepo, $cashConsiderationRepo, $season, $validator);

        $this->assertSame(
            ['userCurrentSeasonCapTotal' => 100, 'partnerCurrentSeasonCapTotal' => 100, 'userCapSentToPartner' => 0, 'partnerCapSentToUser' => 0],
            $subject->exposeCalculateSalaryCapData($this->makeTradeData())
        );
    }

    /** V3: self-trade (offeringTeam === listeningTeam) zeroes sent keys despite checked contracts */
    public function testCapPinSelfTradeZeroesCapSentKeys(): void
    {
        $commonRepo = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $commonRepo->method('getTidFromTeamname')->willReturn(1);
        $cashConsiderationRepo = self::createStub(BuyoutLedgerRepositoryInterface::class);
        $cashConsiderationRepo->method('getTeamCashForSalary')->willReturn([]);
        $season = self::createStub(Season::class);
        $season->method('advancesContractYears')->willReturn(false);
        $validator = self::createStub(TradeValidator::class);
        $validator->method('getCurrentSeasonCashConsiderations')->willReturn(['cashSentToThem' => 0, 'cashSentToMe' => 0]);

        $subject = $this->makeCapPinSubject($commonRepo, $cashConsiderationRepo, $season, $validator);
        $tradeData = $this->makeTradeData(1, 2);
        $tradeData['offeringTeam'] = 'Lakers';
        $tradeData['listeningTeam'] = 'Lakers';
        $tradeData['check'] = [0 => 'on', 1 => 'on'];
        $tradeData['type'] = [0 => '1', 1 => '1'];
        $tradeData['contract'] = [0 => '500', 1 => '600'];

        $this->assertSame(
            ['userCurrentSeasonCapTotal' => 500, 'partnerCurrentSeasonCapTotal' => 600, 'userCapSentToPartner' => 0, 'partnerCapSentToUser' => 0],
            $subject->exposeCalculateSalaryCapData($tradeData)
        );
    }

    /** V4: cash record CY-offset row summed into cap total alongside contracts */
    public function testCapPinCashRecordCyOffsetIncludedInTotal(): void
    {
        // advances=true → cy 1→2 → yr2=500 per team; contracts 300 (user), 400 (partner)
        // user total = 300+500=800, partner total = 400+500=900, sent = 300, received = 400
        $commonRepo = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $commonRepo->method('getTidFromTeamname')->willReturn(1);
        $cashConsiderationRepo = self::createStub(BuyoutLedgerRepositoryInterface::class);
        $cashConsiderationRepo->method('getTeamCashForSalary')->willReturn([$this->makeDiscriminatorRow()]);
        $season = self::createStub(Season::class);
        $season->method('advancesContractYears')->willReturn(true);
        $validator = self::createStub(TradeValidator::class);
        $validator->method('getCurrentSeasonCashConsiderations')->willReturn(['cashSentToThem' => 0, 'cashSentToMe' => 0]);

        $subject = $this->makeCapPinSubject($commonRepo, $cashConsiderationRepo, $season, $validator);
        $tradeData = $this->makeTradeData(1, 2);
        $tradeData['check'] = [0 => 'on', 1 => 'on'];
        $tradeData['type'] = [0 => '1', 1 => '1'];
        $tradeData['contract'] = [0 => '300', 1 => '400'];

        $this->assertSame(
            ['userCurrentSeasonCapTotal' => 800, 'partnerCurrentSeasonCapTotal' => 900, 'userCapSentToPartner' => 300, 'partnerCapSentToUser' => 400],
            $subject->exposeCalculateSalaryCapData($tradeData)
        );
    }

    // ── Additive cap-math pins (gaps the V1–V4 pins above do not cover) ──
    // V1–V4 pin self-trade zeroing, both cy branches, and the cash-record
    // cy-offset. The pins below add: the switchCounter checked/unchecked split,
    // the empty-offer boundary, the new-cash-consideration direction (both ways),
    // and the null-team-id fallback. No DB, no Discord.

    /**
     * Build a cap-pin subject with no cash records, in-season (advances=false),
     * a fixed tid, and caller-supplied new-cash considerations.
     *
     * @param array{cashSentToThem: int, cashSentToMe: int} $cashConsiderations
     */
    private function makeNoCashRecordSubject(array $cashConsiderations, ?int $tid = 1): TradeOfferCapPinDouble
    {
        $commonRepo = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $commonRepo->method('getTidFromTeamname')->willReturn($tid);
        $cashConsiderationRepo = self::createStub(BuyoutLedgerRepositoryInterface::class);
        $cashConsiderationRepo->method('getTeamCashForSalary')->willReturn([]);
        $season = self::createStub(Season::class);
        $season->method('advancesContractYears')->willReturn(false);
        $validator = self::createStub(TradeValidator::class);
        $validator->method('getCurrentSeasonCashConsiderations')->willReturn($cashConsiderations);

        return $this->makeCapPinSubject($commonRepo, $cashConsiderationRepo, $season, $validator);
    }

    /**
     * Pins the switchCounter boundary AND the checked/unchecked split for the
     * sent/received keys: user contracts are indices < switchCounter, partner
     * contracts are indices >= switchCounter; only "on"-checked contracts add to
     * the sent/received totals while ALL contracts add to the season totals.
     */
    public function testCapPinMultiPlayerSplitAcrossSwitchCounter(): void
    {
        $subject = $this->makeNoCashRecordSubject(['cashSentToThem' => 0, 'cashSentToMe' => 0]);

        // switchCounter=2 (indices 0,1 = user), fieldsCounter=4 (indices 2,3 = partner)
        $tradeData = $this->makeTradeData(2, 4);
        $tradeData['check'] = [0 => 'on', 1 => null, 2 => 'on', 3 => null];
        $tradeData['type'] = [0 => '1', 1 => '1', 2 => '1', 3 => '1'];
        $tradeData['contract'] = [0 => '500', 1 => '1200', 2 => '700', 3 => '300'];

        // user total = 500+1200=1700, sent = 500 (only index 0 checked)
        // partner total = 700+300=1000, received = 700 (only index 2 checked)
        $this->assertSame(
            ['userCurrentSeasonCapTotal' => 1700, 'partnerCurrentSeasonCapTotal' => 1000, 'userCapSentToPartner' => 500, 'partnerCapSentToUser' => 700],
            $subject->exposeCalculateSalaryCapData($tradeData)
        );
    }

    /** Boundary: an empty offer (no players, no cash) freezes all four keys to 0. */
    public function testCapPinEmptyOfferAllZeros(): void
    {
        $subject = $this->makeNoCashRecordSubject(['cashSentToThem' => 0, 'cashSentToMe' => 0]);

        $this->assertSame(
            ['userCurrentSeasonCapTotal' => 0, 'partnerCurrentSeasonCapTotal' => 0, 'userCapSentToPartner' => 0, 'partnerCapSentToUser' => 0],
            $subject->exposeCalculateSalaryCapData($this->makeTradeData())
        );
    }

    /** New cash the user SENDS raises the user total and lowers the partner total. */
    public function testCapPinCashSentToThemRaisesUserLowersPartner(): void
    {
        $subject = $this->makeNoCashRecordSubject(['cashSentToThem' => 250, 'cashSentToMe' => 0]);

        $this->assertSame(
            ['userCurrentSeasonCapTotal' => 250, 'partnerCurrentSeasonCapTotal' => -250, 'userCapSentToPartner' => 0, 'partnerCapSentToUser' => 0],
            $subject->exposeCalculateSalaryCapData($this->makeTradeData())
        );
    }

    /** New cash the user RECEIVES lowers the user total and raises the partner total. */
    public function testCapPinCashSentToMeLowersUserRaisesPartner(): void
    {
        $subject = $this->makeNoCashRecordSubject(['cashSentToThem' => 0, 'cashSentToMe' => 400]);

        $this->assertSame(
            ['userCurrentSeasonCapTotal' => -400, 'partnerCurrentSeasonCapTotal' => 400, 'userCapSentToPartner' => 0, 'partnerCapSentToUser' => 0],
            $subject->exposeCalculateSalaryCapData($this->makeTradeData())
        );
    }

    /**
     * Negative/fallback: when getTidFromTeamname() returns null (e.g. an unknown
     * or Free-Agents team), the `?? 0` fallback resolves the tid to 0 and the cap
     * math proceeds without exception.
     */
    public function testCapPinNullTeamIdResolvesToZero(): void
    {
        $subject = $this->makeNoCashRecordSubject(['cashSentToThem' => 0, 'cashSentToMe' => 0], tid: null);

        $this->assertSame(
            ['userCurrentSeasonCapTotal' => 0, 'partnerCurrentSeasonCapTotal' => 0, 'userCapSentToPartner' => 0, 'partnerCapSentToUser' => 0],
            $subject->exposeCalculateSalaryCapData($this->makeTradeData())
        );
    }
}

/**
 * Test double for the characterization pin: exposes the protected
 * calculateSalaryCapData() without going through the real constructor.
 * Sets only the four props that method reads; never sets tradeCapCalculator
 * (added in Phase 4) — the fallback delegator must tolerate this.
 *
 * @phpstan-import-type TradeFormData from \Trading\TradeOffer
 */
class TradeOfferCapPinDouble extends TradeOffer
{
    // @phpstan-ignore constructor.missingParentCall (test double: skip real ctor, inject the four cap collaborators directly)
    public function __construct(
        \Repositories\Contracts\TeamIdentityRepositoryInterface $commonRepo,
        \Trading\Contracts\BuyoutLedgerRepositoryInterface $cashConsiderationRepo,
        Season $season,
        TradeValidator $validator
    ) {
        $this->db = new MockDatabase();
        $this->commonRepository = $commonRepo;
        $this->cashConsiderationRepository = $cashConsiderationRepo;
        $this->season = $season;
        $this->validator = $validator;
        $this->logger = \Logging\LoggerFactory::getChannel('trade');
    }

    /**
     * @phpstan-param TradeFormData $tradeData
     * @return array{userCurrentSeasonCapTotal: int, partnerCurrentSeasonCapTotal: int, userCapSentToPartner: int, partnerCapSentToUser: int}
     */
    public function exposeCalculateSalaryCapData(array $tradeData): array
    {
        return $this->calculateSalaryCapData($tradeData);
    }
}
