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

class TradingControllerReviewTest extends TestCase
{
    private MockDatabase $mockDb;
    private TradingServiceInterface $stubService;
    private TradeOfferRepositoryInterface $stubOfferRepo;
    private TradeOfferInterface $stubTradeOffer;
    private TeamIdentityRepositoryInterface $stubTeamIdentityRepo;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();

        $this->stubService = self::createStub(TradingServiceInterface::class);
        $this->stubOfferRepo = self::createStub(TradeOfferRepositoryInterface::class);
        $this->stubTradeOffer = self::createStub(TradeOfferInterface::class);
        $this->stubTeamIdentityRepo = self::createStub(TeamIdentityRepositoryInterface::class);
    }

    private function buildController(
        ?\Utilities\NukeCompat $nukeCompat = null,
        ?TradingViewInterface $view = null,
        ?TradingServiceInterface $service = null,
    ): TradingController {
        return new TradingController(
            $service ?? $this->stubService,
            $this->stubOfferRepo,
            $this->stubTradeOffer,
            $view ?? self::createStub(TradingViewInterface::class),
            $this->stubTeamIdentityRepo,
            $nukeCompat ?? self::createStub(\Utilities\NukeCompat::class),
            $this->mockDb,
            self::createStub(TradeExecutionServiceInterface::class),
            self::createStub(AuthServiceInterface::class),
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
        $controller->handleTradeReview(null);

        $this->assertTrue($loginBoxCalled);
    }

    public function testImplementsInterface(): void
    {
        self::assertContains(
            \Trading\Contracts\TradingControllerInterface::class,
            (array) class_implements(TradingController::class)
        );
    }
}
