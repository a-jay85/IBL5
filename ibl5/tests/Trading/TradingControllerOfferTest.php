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
            self::createStub(TradeOfferRepositoryInterface::class),
            self::createStub(TradeOfferInterface::class),
            self::createStub(TradingViewInterface::class),
            self::createStub(TeamIdentityRepositoryInterface::class),
            $nukeCompat ?? self::createStub(\Utilities\NukeCompat::class),
            $this->mockDb,
            self::createStub(TradeExecutionServiceInterface::class),
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
}
