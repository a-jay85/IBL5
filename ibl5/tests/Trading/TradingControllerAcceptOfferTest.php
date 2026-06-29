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
 * Tests for TradingController::acceptTradeOffer()
 *
 * All code paths in acceptTradeOffer() reach HtmxHelper::redirect() (or
 * loginBox()) which calls exit — full invocation tests require E2E coverage, so
 * these verify instantiation/interface compliance only. The accept-path IDOR
 * gate (Matrix #12) is asserted exit-free in
 * {@see TradeExecutionServiceTest::testValidateAndExecuteRejectsNonPartyWithoutExecuting()}.
 */
class TradingControllerAcceptOfferTest extends TestCase
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

    public function testCanBeInstantiated(): void
    {
        $controller = $this->buildController();
        $this->assertIsObject($controller);
    }
}
