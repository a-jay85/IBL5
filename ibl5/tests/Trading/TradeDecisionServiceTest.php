<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Trading\Contracts\TradeExecutionServiceInterface;
use Trading\Contracts\TradeOfferRepositoryInterface;
use Trading\TradeDecisionService;
use Trading\TradeExecutionService;

class TradeDecisionServiceTest extends TestCase
{
    private function buildService(
        ?TradeOfferRepositoryInterface $offerRepo = null,
        ?TradeExecutionServiceInterface $executionService = null,
        ?LoggerInterface $auditLogger = null,
        ?LoggerInterface $tradeLogger = null,
    ): TradeDecisionService {
        return new TradeDecisionService(
            $offerRepo ?? self::createStub(TradeOfferRepositoryInterface::class),
            $executionService ?? self::createStub(TradeExecutionServiceInterface::class),
            self::createStub(TeamIdentityRepositoryInterface::class),
            $auditLogger ?? self::createStub(LoggerInterface::class),
            $tradeLogger ?? self::createStub(LoggerInterface::class),
        );
    }

    public function testRejectRefusesNonPartyAndDeletesNothing(): void
    {
        $offerRepo = self::createMock(TradeOfferRepositoryInterface::class);
        $offerRepo->method('getTradesByOfferId')->willReturn([['tradeofferid' => 1]]);
        $offerRepo->expects(self::never())->method('deleteTradeOffer');

        $executionService = self::createStub(TradeExecutionServiceInterface::class);
        $executionService->method('assertActingTeamIsParty')->willReturn(false);

        $service = $this->buildService(offerRepo: $offerRepo, executionService: $executionService);
        $verdict = $service->reject(1, 'Non Party Team', 'Metros', 'Stars');

        self::assertFalse($verdict['success']);
        self::assertSame('/ibl5/modules.php?name=Trading&op=reviewtrade&result=reject_error', $verdict['redirect']);
    }

    public function testRejectRefusesEmptyActingTeamAndDeletesNothing(): void
    {
        $offerRepo = self::createMock(TradeOfferRepositoryInterface::class);
        $offerRepo->method('getTradesByOfferId')->willReturn([['tradeofferid' => 1, 'trade_from' => 'Metros', 'trade_to' => 'Stars']]);
        $offerRepo->expects(self::never())->method('deleteTradeOffer');

        // Use a real TradeExecutionService backed by a stub offer repo so the test
        // asserts the composed empty-string refusal, not a hand-stubbed false.
        $executionOfferRepo = self::createStub(TradeOfferRepositoryInterface::class);
        $executionOfferRepo->method('getTradesByOfferId')->willReturn([['trade_from' => 'Metros', 'trade_to' => 'Stars']]);
        $teamIdentity = self::createStub(TeamIdentityRepositoryInterface::class);
        $executionService = new TradeExecutionService(
            $executionOfferRepo,
            self::createStub(\Trading\Contracts\TradeProcessorInterface::class),
            self::createStub(\Trading\Contracts\TradeValidatorInterface::class),
            self::createStub(\Repositories\Contracts\SalaryCapRepositoryInterface::class),
            $teamIdentity,
            self::createStub(\Trading\Contracts\TradeCashRepositoryInterface::class),
            self::createStub(\Season\Season::class),
        );

        $service = $this->buildService(offerRepo: $offerRepo, executionService: $executionService);
        $verdict = $service->reject(1, '', 'Metros', 'Stars');

        self::assertFalse($verdict['success']);
        self::assertSame('/ibl5/modules.php?name=Trading&op=reviewtrade&result=reject_error', $verdict['redirect']);
    }

    public function testRejectIgnoresPostSuppliedTeamRejectingForAuthorization(): void
    {
        $offerRepo = self::createMock(TradeOfferRepositoryInterface::class);
        $offerRepo->method('getTradesByOfferId')->willReturn([['tradeofferid' => 1]]);
        $offerRepo->expects(self::never())->method('deleteTradeOffer');

        $executionService = self::createMock(TradeExecutionServiceInterface::class);
        $executionService->expects(self::once())
            ->method('assertActingTeamIsParty')
            ->with(1, 'Outsider Team')
            ->willReturn(false);

        $service = $this->buildService(offerRepo: $offerRepo, executionService: $executionService);
        $verdict = $service->reject(1, 'Outsider Team', 'Metros', 'Stars');

        self::assertFalse($verdict['success']);
    }

    public function testRejectShortCircuitsOnAlreadyProcessedOffer(): void
    {
        $offerRepo = self::createMock(TradeOfferRepositoryInterface::class);
        $offerRepo->method('getTradesByOfferId')->willReturn([]);
        $offerRepo->expects(self::never())->method('deleteTradeOffer');

        $executionService = self::createMock(TradeExecutionServiceInterface::class);
        $executionService->expects(self::never())->method('assertActingTeamIsParty');

        $service = $this->buildService(offerRepo: $offerRepo, executionService: $executionService);
        $verdict = $service->reject(99, 'Metros', 'Metros', 'Stars');

        self::assertFalse($verdict['success']);
        self::assertStringContainsString('already_processed', $verdict['redirect']);
    }

    public function testRejectDeletesAndReturnsSuccessForParty(): void
    {
        $offerRepo = self::createMock(TradeOfferRepositoryInterface::class);
        $offerRepo->method('getTradesByOfferId')->willReturn([['tradeofferid' => 42]]);
        $offerRepo->expects(self::once())->method('deleteTradeOffer')->with(42);

        $executionService = self::createStub(TradeExecutionServiceInterface::class);
        $executionService->method('assertActingTeamIsParty')->willReturn(true);

        $service = $this->buildService(offerRepo: $offerRepo, executionService: $executionService);
        $verdict = $service->reject(42, 'Metros', 'Metros', 'Stars');

        self::assertTrue($verdict['success']);
        self::assertSame('/ibl5/modules.php?name=Trading&op=reviewtrade&result=trade_rejected', $verdict['redirect']);
    }

    public function testRejectLogsRefusalAndAuditsSuccessOnTheRightChannels(): void
    {
        // Refusal case: warning on trade channel, nothing on audit channel
        $offerRepo = self::createStub(TradeOfferRepositoryInterface::class);
        $offerRepo->method('getTradesByOfferId')->willReturn([['tradeofferid' => 7]]);

        $executionService = self::createStub(TradeExecutionServiceInterface::class);
        $executionService->method('assertActingTeamIsParty')->willReturn(false);

        $tradeLogger = self::createMock(LoggerInterface::class);
        $tradeLogger->expects(self::once())->method('warning')
            ->with('Rejected non-party trade reject attempt', ['offer_id' => 7]);
        $auditLogger = self::createMock(LoggerInterface::class);
        $auditLogger->expects(self::never())->method('info');

        $service = $this->buildService(
            offerRepo: $offerRepo,
            executionService: $executionService,
            auditLogger: $auditLogger,
            tradeLogger: $tradeLogger,
        );
        $service->reject(7, 'Outsider', 'Metros', 'Stars');

        // Success case: audit entry, no warning
        $offerRepo2 = self::createStub(TradeOfferRepositoryInterface::class);
        $offerRepo2->method('getTradesByOfferId')->willReturn([['tradeofferid' => 7]]);

        $executionService2 = self::createStub(TradeExecutionServiceInterface::class);
        $executionService2->method('assertActingTeamIsParty')->willReturn(true);

        $tradeLogger2 = self::createMock(LoggerInterface::class);
        $tradeLogger2->expects(self::never())->method('warning');
        $auditLogger2 = self::createMock(LoggerInterface::class);
        $auditLogger2->expects(self::once())->method('info')
            ->with('trade_offer_rejected', ['offer_id' => 7]);

        $service2 = $this->buildService(
            offerRepo: $offerRepo2,
            executionService: $executionService2,
            auditLogger: $auditLogger2,
            tradeLogger: $tradeLogger2,
        );
        $service2->reject(7, 'Metros', 'Metros', 'Stars');
    }
}
