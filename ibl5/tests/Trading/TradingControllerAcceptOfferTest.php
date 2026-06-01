<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Trading\Contracts\TradingServiceInterface;
use Trading\Contracts\TradeProcessorInterface;
use Trading\Contracts\TradeOfferRepositoryInterface;
use Trading\Contracts\TradeOfferInterface;
use Trading\Contracts\TradingViewInterface;
use Trading\TradingController;

/**
 * Tests for TradingController::acceptTradeOffer()
 *
 * All code paths in acceptTradeOffer() reach HtmxHelper::redirect() which calls
 * exit — full invocation tests require E2E coverage. These tests verify
 * instantiation and interface compliance only.
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
        ?TradeProcessorInterface $processor = null,
    ): TradingController {
        return new TradingController(
            self::createStub(TradingServiceInterface::class),
            $processor ?? self::createStub(TradeProcessorInterface::class),
            $offerRepo ?? self::createStub(TradeOfferRepositoryInterface::class),
            self::createStub(TradeOfferInterface::class),
            self::createStub(TradingViewInterface::class),
            self::createStub(TeamIdentityRepositoryInterface::class),
            self::createStub(\Utilities\NukeCompat::class),
            $this->mockDb,
        );
    }

    public function testCanBeInstantiated(): void
    {
        $controller = $this->buildController();
        $this->assertInstanceOf(TradingController::class, $controller);
    }

    public function testImplementsInterface(): void
    {
        $controller = $this->buildController();
        $this->assertInstanceOf(\Trading\Contracts\TradingControllerInterface::class, $controller);
    }
}
