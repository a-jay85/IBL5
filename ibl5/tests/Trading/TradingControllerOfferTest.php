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

class TradingControllerOfferTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    private function buildController(
        ?\Utilities\NukeCompat $nukeCompat = null,
    ): TradingController {
        return new TradingController(
            self::createStub(TradingServiceInterface::class),
            self::createStub(TradeProcessorInterface::class),
            self::createStub(TradeOfferRepositoryInterface::class),
            self::createStub(TradeOfferInterface::class),
            self::createStub(TradingViewInterface::class),
            self::createStub(TeamIdentityRepositoryInterface::class),
            $nukeCompat ?? self::createStub(\Utilities\NukeCompat::class),
            $this->mockDb,
        );
    }

    public function testRedirectsToLoginWhenUserNotAuthenticated(): void
    {
        $loginBoxCalled = false;
        $nukeCompat = self::createStub(\Utilities\NukeCompat::class);
        $nukeCompat->method('isUser')->willReturn(false);
        $nukeCompat->method('loginBox')->willReturnCallback(function () use (&$loginBoxCalled): void {
            $loginBoxCalled = true;
        });

        $controller = $this->buildController(nukeCompat: $nukeCompat);
        $controller->handleTradeOffer(null, 'Boston');

        $this->assertTrue($loginBoxCalled);
    }

    public function testCanBeInstantiated(): void
    {
        $controller = $this->buildController();
        $this->assertIsObject($controller);
    }

    // ============================================
    // PER-CHANNEL LOGGER SEAM (Matrix #8 boundary)
    // ============================================

    /**
     * Boundary (Matrix #8): constructing TradingController with injected audit/trade logger
     * spies (the new trailing args 9 & 10) does not throw and produces a usable instance.
     * Full invocation tests for the logging paths require E2E — all TradingController
     * action methods call HtmxHelper::redirect() → exit after logging.
     */
    public function testConstructableWithAuditAndTradeLoggerSpies(): void
    {
        $auditSpy = self::createStub(\Psr\Log\LoggerInterface::class);
        $tradeSpy = self::createStub(\Psr\Log\LoggerInterface::class);

        $controller = new TradingController(
            self::createStub(TradingServiceInterface::class),
            self::createStub(TradeProcessorInterface::class),
            self::createStub(TradeOfferRepositoryInterface::class),
            self::createStub(TradeOfferInterface::class),
            self::createStub(TradingViewInterface::class),
            self::createStub(TeamIdentityRepositoryInterface::class),
            self::createStub(\Utilities\NukeCompat::class),
            $this->mockDb,
            auditLogger: $auditSpy,
            tradeLogger: $tradeSpy,
        );

        $this->assertIsObject($controller);
    }
}
