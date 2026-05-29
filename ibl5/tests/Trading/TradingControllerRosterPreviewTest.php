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

class TradingControllerRosterPreviewTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    protected function tearDown(): void
    {
        $_GET = [];
    }

    private function buildController(
        ?\Utilities\NukeCompat $nukeCompat = null,
        ?TeamIdentityRepositoryInterface $teamIdentityRepo = null,
    ): TradingController {
        return new TradingController(
            $this->createStub(TradingServiceInterface::class),
            $this->createStub(TradeProcessorInterface::class),
            $this->createStub(TradeOfferRepositoryInterface::class),
            $this->createStub(TradeOfferInterface::class),
            $this->createStub(TradingViewInterface::class),
            $teamIdentityRepo ?? $this->createStub(TeamIdentityRepositoryInterface::class),
            $nukeCompat ?? $this->createStub(\Utilities\NukeCompat::class),
            $this->mockDb,
        );
    }

    private function captureOutput(callable $fn): string
    {
        ob_start();
        try {
            $fn();
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    public function testReturnsEmptyHtmlJsonWhenNotAuthenticated(): void
    {
        $nukeCompat = $this->createStub(\Utilities\NukeCompat::class);
        $nukeCompat->method('isUser')->willReturn(false);

        $controller = $this->buildController(nukeCompat: $nukeCompat);

        $output = $this->captureOutput(fn () => $controller->handleRosterPreviewApi(null));

        /** @var array{html: string}|null $decoded */
        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testDelegatesHandlerForAuthenticatedUser(): void
    {
        $nukeCompat = $this->createStub(\Utilities\NukeCompat::class);
        $nukeCompat->method('isUser')->willReturn(true);
        $nukeCompat->method('cookieDecode')->willReturn(['', 'testuser']);

        $mockTeamIdentityRepo = $this->createMock(TeamIdentityRepositoryInterface::class);
        $mockTeamIdentityRepo->expects($this->once())
            ->method('getTeamnameFromUsername')
            ->with('testuser')
            ->willReturn('Lakers');
        $mockTeamIdentityRepo->method('getTidFromTeamname')->willReturn(1);

        $controller = $this->buildController(nukeCompat: $nukeCompat, teamIdentityRepo: $mockTeamIdentityRepo);

        $this->captureOutput(fn () => $controller->handleRosterPreviewApi('user-cookie'));
    }
}
