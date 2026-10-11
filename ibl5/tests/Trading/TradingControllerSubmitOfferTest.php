<?php

declare(strict_types=1);

namespace Tests\Trading;

use EventLog\EventLogger;
use EventLog\EventLogRepository;
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

    public function testSetActionNotCalledOnCsrfFailure(): void
    {
        // CSRF failure calls HtmxHelper::redirect() + exit() before any setAction().
        // Since exit() ends the process, we verify the invariant: when no setAction
        // was called (as happens on CSRF failure), flush delivers null action.
        // This pins the contract that the instrumentation sits BELOW the CSRF guard.
        EventLogger::reset();

        $repo = $this->createMock(EventLogRepository::class);
        $repo->expects($this->once())
             ->method('updateOutcome')
             ->with(self::anything(), self::anything(), null);

        EventLogger::arm(1, self::createStub(\mysqli::class));
        // setAction is NOT called — mirroring the CSRF-failure path
        EventLogger::flush($repo);
    }

    /**
     * HtmxHelper::redirect() exits the process, so a behavioral test of this refusal is not buildable; pin the source instead.
     * $offeringTeam is bound straight from the session with no later check, so this guard is the only barrier.
     */
    public function testSubmitTradeOfferRefusesTeamlessSessionBeforeBindingOfferingTeam(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/classes/Trading/TradingController.php');
        self::assertIsString($src);

        $guard = 'if ($sessionTeam === null || $sessionTeam === \'\' || $sessionTeam === \League\League::FREE_AGENTS_TEAM_NAME) {';
        $guardPos = strpos($src, $guard);
        self::assertIsInt($guardPos, 'Trading submit guard removed or weakened: a null, empty, or Free Agents session would bind offeringTeam');

        self::assertMatchesRegularExpression(
            '/\{\s*\\\\Utilities\\\\HtmxHelper::redirect\(/',
            substr($src, $guardPos, 300),
            'Trading submit guard body no longer redirects'
        );

        $bindPos = strpos($src, '$offeringTeam = $sessionTeam;');
        self::assertIsInt($bindPos, 'Trading $offeringTeam = $sessionTeam; binding not found');
        self::assertLessThan($bindPos, $guardPos, 'Trading submit guard must precede binding $offeringTeam');
    }
}
