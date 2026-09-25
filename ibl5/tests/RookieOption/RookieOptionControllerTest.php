<?php

declare(strict_types=1);

namespace Tests\RookieOption;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use RookieOption\RookieOptionController;
use RookieOption\Contracts\RookieOptionControllerInterface;
use RookieOption\Contracts\RookieOptionRepositoryInterface;
use Season\Season;
use Tests\WideUnit\Mocks\MockDatabase;
use Tests\WideUnit\Mocks\TestDataFactory;
use Topics\News\Contracts\NewsRepositoryInterface;

/**
 * RookieOptionControllerTest - Tests for RookieOptionController
 */
class RookieOptionControllerTest extends TestCase
{
    private const ELIGIBILITY_MESSAGE = "This player's experience doesn't match their rookie status; please let the commish know about this error.";

    private MockDatabase $mockDb;

    private function makeController(
        RookieOptionRepositoryInterface $repository,
        ?TeamIdentityRepositoryInterface $teams = null,
    ): RookieOptionController {
        $this->mockDb = new MockDatabase();
        $player = TestDataFactory::createPlayer(['pid' => 1, 'exp' => 10, 'draftround' => 3]);
        $this->mockDb->setMockData([$player]);
        $this->mockDb->onQuery('FROM ibl_plr', [$player]);

        $season = self::createStub(Season::class);
        $season->phase = 'Regular Season';

        return new RookieOptionController(
            $this->mockDb,
            $teams ?? self::createStub(TeamIdentityRepositoryInterface::class),
            $repository,
            self::createStub(NewsRepositoryInterface::class),
            self::createStub(LoggerInterface::class),
            self::createStub(LoggerInterface::class),
            $season,
        );
    }

    // ============================================
    // INSTANTIATION TESTS
    // ============================================

    public function testImplementsInterface(): void
    {
        self::assertContains(RookieOptionControllerInterface::class, (array) class_implements(RookieOptionController::class));
    }

    public function testIneligiblePlayerOnOwnTeamReturnsEligibilityValidationError(): void
    {
        $repository = $this->createMock(RookieOptionRepositoryInterface::class);
        $repository->expects(self::never())->method('updatePlayerRookieOption');

        $result = $this->makeController($repository)->processRookieOption('Test Team', 1, 500, 'Test Team');

        self::assertFalse($result['success']);
        self::assertSame('validation_error', $result['type']);
        self::assertSame(self::ELIGIBILITY_MESSAGE, $result['message']);
    }

}
