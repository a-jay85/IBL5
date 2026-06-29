<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Trading\Contracts\TradingServiceInterface;
use Trading\Contracts\TradeOfferRepositoryInterface;
use Trading\Contracts\TradeOfferInterface;
use Trading\Contracts\TradingViewInterface;
use Trading\Contracts\TradeExecutionServiceInterface;
use Auth\Contracts\AuthServiceInterface;
use Trading\TradingController;

/**
 * Tests for TradingController::rejectTradeOffer()
 *
 * All code paths in rejectTradeOffer() reach HtmxHelper::redirect() (or
 * loginBox()) which calls exit — full invocation tests require E2E coverage, so
 * these verify instantiation/interface compliance only. The reject-path IDOR
 * gate (Matrix #13) is asserted exit-free in
 * {@see TradeExecutionServiceTest::testAssertActingTeamIsPartyDistinguishesPartyFromNonParty()}.
 */
class TradingControllerRejectOfferTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    private function buildController(
        ?TradeOfferRepositoryInterface $offerRepo = null,
    ): TradingController {
        return new TradingController(
            self::createStub(TradingServiceInterface::class),
            $offerRepo ?? self::createStub(TradeOfferRepositoryInterface::class),
            self::createStub(TradeOfferInterface::class),
            self::createStub(TradingViewInterface::class),
            self::createStub(TeamIdentityRepositoryInterface::class),
            self::createStub(\Utilities\NukeCompat::class),
            $this->mockDb,
            self::createStub(TradeExecutionServiceInterface::class),
            self::createStub(AuthServiceInterface::class),
        );
    }

    public function testImplementsInterface(): void
    {
        $controller = $this->buildController();
        self::assertContains(
            \Trading\Contracts\TradingControllerInterface::class,
            (array) class_implements($controller)
        );
    }
}
