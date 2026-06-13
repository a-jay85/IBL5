<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Security\CsrfGuard;
use Trading\Contracts\TradingServiceInterface;
use Trading\Contracts\TradeProcessorInterface;
use Trading\Contracts\TradeOfferRepositoryInterface;
use Trading\Contracts\TradeOfferInterface;
use Trading\Contracts\TradingViewInterface;
use Trading\TradingController;

/**
 * Tests for TradingController::counterTradeOffer()
 *
 * Most counter paths terminate in HtmxHelper::redirect() (which exits), so the
 * CSRF/IDOR/ordering invariants are verified end-to-end at E2E/API. These tests
 * cover instantiation, interface compliance, and the pre-redirect unauthenticated
 * bail (which returns rather than exiting).
 */
class TradingControllerCounterOfferTest extends TestCase
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
        ?TradeOfferRepositoryInterface $offerRepo = null,
    ): TradingController {
        return new TradingController(
            self::createStub(TradingServiceInterface::class),
            self::createStub(TradeProcessorInterface::class),
            $offerRepo ?? self::createStub(TradeOfferRepositoryInterface::class),
            self::createStub(TradeOfferInterface::class),
            self::createStub(TradingViewInterface::class),
            self::createStub(TeamIdentityRepositoryInterface::class),
            $nukeCompat ?? self::createStub(\Utilities\NukeCompat::class),
            $this->mockDb,
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

    public function testUnauthenticatedCallShowsLoginAndDoesNotTouchOfferRepo(): void
    {
        // Pass CSRF so execution reaches the auth gate (CSRF failure would exit first).
        $token = CsrfGuard::generateRawToken('trade_counter');
        $_POST['_csrf_token'] = $token;

        $loginBoxCalled = false;
        $nukeCompat = self::createStub(\Utilities\NukeCompat::class);
        $nukeCompat->method('isUser')->willReturn(false);
        $nukeCompat->method('loginBox')->willReturnCallback(function () use (&$loginBoxCalled): void {
            $loginBoxCalled = true;
        });

        $offerRepo = self::createMock(TradeOfferRepositoryInterface::class);
        $offerRepo->expects(self::never())->method('getTradesByOfferId');
        $offerRepo->expects(self::never())->method('deleteTradeOffer');

        $controller = $this->buildController(nukeCompat: $nukeCompat, offerRepo: $offerRepo);
        $controller->counterTradeOffer(null, ['offer' => '1', '_csrf_token' => $token]);

        $this->assertTrue($loginBoxCalled);
    }
}
