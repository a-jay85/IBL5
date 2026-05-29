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
 * Tests for TradingController::rejectTradeOffer()
 *
 * All code paths in rejectTradeOffer() reach HtmxHelper::redirect() which calls
 * exit — full invocation tests require E2E coverage. These tests verify
 * instantiation and interface compliance only.
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
            $this->createStub(TradingServiceInterface::class),
            $this->createStub(TradeProcessorInterface::class),
            $offerRepo ?? $this->createStub(TradeOfferRepositoryInterface::class),
            $this->createStub(TradeOfferInterface::class),
            $this->createStub(TradingViewInterface::class),
            $this->createStub(TeamIdentityRepositoryInterface::class),
            $this->createStub(\Utilities\NukeCompat::class),
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
