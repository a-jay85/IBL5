<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Trading\Contracts\TradingServiceInterface;
use Trading\Contracts\TradeProcessorInterface;
use Trading\Contracts\TradeOfferRepositoryInterface;
use Trading\Contracts\TradeOfferInterface;
use Trading\Contracts\TradingViewInterface;
use Trading\TradingController;

class TradingControllerReviewTest extends TestCase
{
    private \mysqli $mockDb;
    private TradingServiceInterface $stubService;
    private TradeProcessorInterface $stubProcessor;
    private TradeOfferRepositoryInterface $stubOfferRepo;
    private TradeOfferInterface $stubTradeOffer;
    private TeamIdentityRepositoryInterface $stubTeamIdentityRepo;

    protected function setUp(): void
    {
        $this->mockDb = new class extends \mysqli {
            public int $connect_errno = 0;
            public ?string $connect_error = null;

            public function __construct()
            {
            }

            #[\ReturnTypeWillChange]
            public function prepare(string $query): \mysqli_stmt|false
            {
                return false;
            }

            #[\ReturnTypeWillChange]
            public function query(string $query, int $resultMode = MYSQLI_STORE_RESULT): \mysqli_result|bool
            {
                return false;
            }
        };

        $this->stubService = $this->createStub(TradingServiceInterface::class);
        $this->stubProcessor = $this->createStub(TradeProcessorInterface::class);
        $this->stubOfferRepo = $this->createStub(TradeOfferRepositoryInterface::class);
        $this->stubTradeOffer = $this->createStub(TradeOfferInterface::class);
        $this->stubTeamIdentityRepo = $this->createStub(TeamIdentityRepositoryInterface::class);
    }

    private function buildController(
        ?\Utilities\NukeCompat $nukeCompat = null,
        ?TradingViewInterface $view = null,
        ?TradingServiceInterface $service = null,
    ): TradingController {
        return new TradingController(
            $service ?? $this->stubService,
            $this->stubProcessor,
            $this->stubOfferRepo,
            $this->stubTradeOffer,
            $view ?? $this->createStub(TradingViewInterface::class),
            $this->stubTeamIdentityRepo,
            $nukeCompat ?? $this->createStub(\Utilities\NukeCompat::class),
            $this->mockDb,
        );
    }

    public function testRedirectsToLoginWhenUserNotAuthenticated(): void
    {
        $loginBoxCalled = false;
        $nukeCompat = $this->createStub(\Utilities\NukeCompat::class);
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
        $controller = $this->buildController();
        $this->assertInstanceOf(\Trading\Contracts\TradingControllerInterface::class, $controller);
    }
}
