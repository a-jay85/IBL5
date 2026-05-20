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

/**
 * Tests for TradingController::acceptTradeOffer()
 *
 * All code paths in acceptTradeOffer() reach HtmxHelper::redirect() which calls
 * exit — full invocation tests require E2E coverage. These tests verify
 * instantiation and interface compliance only.
 */
class TradingControllerAcceptOfferTest extends TestCase
{
    private \mysqli $mockDb;

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
    }

    private function buildController(
        ?TradeOfferRepositoryInterface $offerRepo = null,
        ?TradeProcessorInterface $processor = null,
    ): TradingController {
        return new TradingController(
            $this->createStub(TradingServiceInterface::class),
            $processor ?? $this->createStub(TradeProcessorInterface::class),
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
