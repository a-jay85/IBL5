<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Security\CsrfGuard;
use Trading\Contracts\TradingServiceInterface;
use Trading\Contracts\TradeOfferRepositoryInterface;
use Trading\Contracts\TradeOfferInterface;
use Trading\Contracts\TradingViewInterface;
use Trading\Contracts\TradeExecutionServiceInterface;
use Auth\Contracts\AuthServiceInterface;
use Trading\TradingController;

/**
 * Tests for TradingController::submitTradeOffer()
 *
 * Post-auth paths in submitTradeOffer() reach HtmxHelper::redirect() which calls
 * exit — full invocation (happy path + IDOR override) requires E2E coverage. These
 * tests verify interface compliance and the pre-redirect unauthenticated bail
 * (which returns rather than exiting), proving the auth gate guards the endpoint.
 */
class TradingControllerSubmitOfferTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $_SESSION = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
    }

    private function buildController(
        ?\Utilities\NukeCompat $nukeCompat = null,
        ?TradeOfferInterface $tradeOffer = null,
    ): TradingController {
        return new TradingController(
            self::createStub(TradingServiceInterface::class),
            self::createStub(TradeOfferRepositoryInterface::class),
            $tradeOffer ?? self::createStub(TradeOfferInterface::class),
            self::createStub(TradingViewInterface::class),
            self::createStub(TeamIdentityRepositoryInterface::class),
            $nukeCompat ?? self::createStub(\Utilities\NukeCompat::class),
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

    public function testUnauthenticatedCallShowsLoginAndDoesNotCreateOffer(): void
    {
        // Pass CSRF so execution reaches the auth gate (CSRF failure would exit first).
        $token = CsrfGuard::generateRawToken('trade_offer');
        $_POST['_csrf_token'] = $token;

        $loginBoxCalled = false;
        $nukeCompat = self::createStub(\Utilities\NukeCompat::class);
        $nukeCompat->method('isUser')->willReturn(false);
        $nukeCompat->method('loginBox')->willReturnCallback(function () use (&$loginBoxCalled): void {
            $loginBoxCalled = true;
        });

        $tradeOffer = self::createMock(TradeOfferInterface::class);
        $tradeOffer->expects(self::never())->method('createTradeOffer');

        $controller = $this->buildController(nukeCompat: $nukeCompat, tradeOffer: $tradeOffer);
        $controller->submitTradeOffer(null, ['offeringTeam' => 'Stars', '_csrf_token' => $token]);

        $this->assertTrue($loginBoxCalled);
    }
}
