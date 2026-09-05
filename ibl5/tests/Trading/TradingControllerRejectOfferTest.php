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
 * Tests for TradingController::rejectTradeOffer()
 *
 * The unauthenticated bail returns rather than exiting and is asserted exit-free
 * here. Post-auth decision paths move to Trading\TradeDecisionService in the
 * stacked unit authz-verdict-refactor-1b-trading-reject-service and are asserted
 * exit-free there. The reject-path IDOR gate (Matrix #13) is asserted exit-free in
 * {@see TradeExecutionServiceTest::testAssertActingTeamIsPartyDistinguishesPartyFromNonParty()}.
 */
class TradingControllerRejectOfferTest extends TestCase
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
        ?TradeOfferRepositoryInterface $offerRepo = null,
        ?\Utilities\NukeCompat $nukeCompat = null,
    ): TradingController {
        return new TradingController(
            self::createStub(TradingServiceInterface::class),
            $offerRepo ?? self::createStub(TradeOfferRepositoryInterface::class),
            self::createStub(TradeOfferInterface::class),
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

    public function testUnauthenticatedRejectShowsLoginAndDoesNotDelete(): void
    {
        // The auth gate fires before CSRF; this token is set only to keep the harness identical to the submit-path pin.
        $token = CsrfGuard::generateRawToken('trade_reject');
        $_POST['_csrf_token'] = $token;

        $loginBoxCalled = false;
        $nukeCompat = self::createStub(\Utilities\NukeCompat::class);
        $nukeCompat->method('isUser')->willReturn(false);
        $nukeCompat->method('loginBox')->willReturnCallback(function () use (&$loginBoxCalled): void {
            $loginBoxCalled = true;
        });

        $offerRepo = self::createMock(TradeOfferRepositoryInterface::class);
        $offerRepo->expects(self::never())->method('deleteTradeOffer');

        $controller = $this->buildController(offerRepo: $offerRepo, nukeCompat: $nukeCompat);
        $controller->rejectTradeOffer(null, ['offer' => '1', 'teamRejecting' => 'Stars', '_csrf_token' => $token]);

        $this->assertTrue($loginBoxCalled);
    }
}
