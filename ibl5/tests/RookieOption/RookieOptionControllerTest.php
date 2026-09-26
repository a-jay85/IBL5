<?php

declare(strict_types=1);

namespace Tests\RookieOption;

use League\League;
use PHPUnit\Framework\Attributes\DataProvider;
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

    // ============================================
    // OWNERSHIP GATE TESTS
    // ============================================

    public function testNullSessionTeamIsRefusedWithoutWrite(): void
    {
        $repository = $this->createMock(RookieOptionRepositoryInterface::class);
        $repository->expects(self::never())->method('updatePlayerRookieOption');

        $teams = $this->createMock(TeamIdentityRepositoryInterface::class);
        $teams->expects(self::never())->method('getTidFromTeamname');

        $result = $this->makeController($repository, $teams)->processRookieOption('Test Team', 1, 500, null);

        self::assertFalse($result['success']);
        self::assertSame('ownership_error', $result['type']);
        self::assertSame('You can only exercise options for your own team.', $result['message']);
        self::assertSame(1, $result['playerID']);
        self::assertCount(0, $this->mockDb->getExecutedQueries());
    }

    public function testFreeAgentsSessionTeamIsRefusedWithoutWrite(): void
    {
        $repository = $this->createMock(RookieOptionRepositoryInterface::class);
        $repository->expects(self::never())->method('updatePlayerRookieOption');

        $teams = $this->createMock(TeamIdentityRepositoryInterface::class);
        $teams->expects(self::never())->method('getTidFromTeamname');

        $result = $this->makeController($repository, $teams)->processRookieOption(
            League::FREE_AGENTS_TEAM_NAME,
            1,
            500,
            League::FREE_AGENTS_TEAM_NAME,
        );

        self::assertFalse($result['success']);
        self::assertSame('ownership_error', $result['type']);
        self::assertSame('You can only exercise options for your own team.', $result['message']);
        self::assertSame(1, $result['playerID']);
        self::assertCount(0, $this->mockDb->getExecutedQueries());
    }

    public function testMismatchedSessionTeamIsRefusedWithoutWrite(): void
    {
        $repository = $this->createMock(RookieOptionRepositoryInterface::class);
        $repository->expects(self::never())->method('updatePlayerRookieOption');

        $teams = $this->createMock(TeamIdentityRepositoryInterface::class);
        $teams->expects(self::never())->method('getTidFromTeamname');

        $result = $this->makeController($repository, $teams)->processRookieOption('Test Team', 1, 500, 'Other Team');

        self::assertFalse($result['success']);
        self::assertSame('ownership_error', $result['type']);
        self::assertSame('You can only exercise options for your own team.', $result['message']);
        self::assertSame(1, $result['playerID']);
        self::assertCount(0, $this->mockDb->getExecutedQueries());
    }

    public function testTeamlessSessionWithMissingParamsGetsOwnershipRefusal(): void
    {
        $repository = $this->createMock(RookieOptionRepositoryInterface::class);
        $repository->expects(self::never())->method('updatePlayerRookieOption');

        $teams = $this->createMock(TeamIdentityRepositoryInterface::class);
        $teams->expects(self::never())->method('getTidFromTeamname');

        $result = $this->makeController($repository, $teams)->processRookieOption('', 0, 0, null);

        self::assertFalse($result['success']);
        self::assertSame('ownership_error', $result['type']);
        self::assertSame('You can only exercise options for your own team.', $result['message']);
        self::assertSame(0, $result['playerID']);
        self::assertCount(0, $this->mockDb->getExecutedQueries());
    }

    /**
     * @return array<string, array{string, int, int, string}>
     */
    public static function missingParamsProvider(): array
    {
        return [
            'zero playerID'         => ['Test Team', 0, 500, 'Test Team'],
            'zero extensionAmount'  => ['Test Team', 1, 0, 'Test Team'],
            'empty team name'       => ['', 1, 500, ''],
        ];
    }

    #[DataProvider('missingParamsProvider')]
    public function testOwnTeamWithMissingParamsReturnsInvalidRequestWithoutQuery(
        string $teamName,
        int $playerID,
        int $extensionAmount,
        string $sessionTeam,
    ): void {
        $repository = $this->createMock(RookieOptionRepositoryInterface::class);
        $repository->expects(self::never())->method('updatePlayerRookieOption');

        $teams = $this->createMock(TeamIdentityRepositoryInterface::class);
        $teams->expects(self::never())->method('getTidFromTeamname');

        $result = $this->makeController($repository, $teams)->processRookieOption(
            $teamName,
            $playerID,
            $extensionAmount,
            $sessionTeam,
        );

        self::assertFalse($result['success']);
        self::assertSame('validation_error', $result['type']);
        self::assertSame('Invalid request. Missing required parameters.', $result['message']);
        self::assertSame($playerID, $result['playerID']);
        self::assertCount(0, $this->mockDb->getExecutedQueries());
    }

    public function testSessionTeamParameterIsRequiredNullableString(): void
    {
        foreach ([RookieOptionController::class, RookieOptionControllerInterface::class] as $class) {
            $params = (new \ReflectionMethod($class, 'processRookieOption'))->getParameters();
            self::assertCount(4, $params);
            $sessionTeam = $params[3];
            self::assertSame('sessionTeam', $sessionTeam->getName());
            $type = $sessionTeam->getType();
            self::assertInstanceOf(\ReflectionNamedType::class, $type);
            self::assertSame('string', $type->getName());
            self::assertTrue($type->allowsNull());
            self::assertFalse($sessionTeam->isOptional());
        }
    }

}
