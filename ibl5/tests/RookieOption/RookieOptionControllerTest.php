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

    /**
     * Pins today's behavior: the ownership gate has no `''` clause, so a `''` session
     * with a `''` posted team is refused by the validation block, before any query.
     */
    public function testEmptySessionTeamWithEmptyPostedTeamIsRefusedWithoutWrite(): void
    {
        $repository = $this->createMock(RookieOptionRepositoryInterface::class);
        $repository->expects(self::never())->method('updatePlayerRookieOption');

        $teams = $this->createMock(TeamIdentityRepositoryInterface::class);
        $teams->expects(self::never())->method('getTidFromTeamname');

        $result = $this->makeController($repository, $teams)->processRookieOption('', 1, 500, '');

        self::assertFalse($result['success']);
        self::assertSame('validation_error', $result['type']);
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

    // ============================================
    // handleSubmission() GATE + REDIRECT TESTS
    // ============================================

    /**
     * Partial mock: only processRookieOption() is replaced; handleSubmission() runs for real.
     *
     * @return RookieOptionController&\PHPUnit\Framework\MockObject\MockObject
     */
    private function buildSubmissionController(?TeamIdentityRepositoryInterface $teams = null): RookieOptionController
    {
        return $this->getMockBuilder(RookieOptionController::class)
            ->setConstructorArgs([
                new MockDatabase(),
                $teams ?? self::createStub(TeamIdentityRepositoryInterface::class),
                self::createStub(RookieOptionRepositoryInterface::class),
                self::createStub(NewsRepositoryInterface::class),
                self::createStub(LoggerInterface::class),
                self::createStub(LoggerInterface::class),
                self::createStub(Season::class),
            ])
            ->onlyMethods(['processRookieOption'])
            ->getMock();
    }

    private function teamsMapping(string $username, ?string $team): TeamIdentityRepositoryInterface
    {
        $teams = self::createStub(TeamIdentityRepositoryInterface::class);
        $teams->method('getTeamnameFromUsername')
            ->willReturnCallback(static fn (?string $name): ?string => $name === $username ? $team : null);

        return $teams;
    }

    public function testHandleSubmissionUnauthenticatedReturnsNullAndRunsNoLaterGate(): void
    {
        $controller = $this->buildSubmissionController();
        $controller->expects(self::never())->method('processRookieOption');
        /** @var list<string> $calls */
        $calls = [];

        $result = $controller->handleSubmission(
            static function () use (&$calls): bool {
                $calls[] = 'isUser';
                return false;
            },
            static function () use (&$calls): bool {
                $calls[] = 'csrf';
                return true;
            },
            static function () use (&$calls): string {
                $calls[] = 'username';
                return 'gm';
            },
            [],
        );

        self::assertNull($result);
        self::assertSame(['isUser'], $calls);
    }

    public function testHandleSubmissionInvalidCsrfReturnsExactErrorUrlAndRunsNoLaterGate(): void
    {
        $controller = $this->buildSubmissionController();
        $controller->expects(self::never())->method('processRookieOption');
        /** @var list<string> $calls */
        $calls = [];

        $result = $controller->handleSubmission(
            static function () use (&$calls): bool {
                $calls[] = 'isUser';
                return true;
            },
            static function () use (&$calls): bool {
                $calls[] = 'csrf';
                return false;
            },
            static function () use (&$calls): string {
                $calls[] = 'username';
                return 'gm';
            },
            [],
        );

        self::assertSame(
            'modules.php?name=Player&error=Invalid%20or%20expired%20form%20submission.%20Please%20reload%20and%20try%20again.',
            $result,
        );
        self::assertSame(['isUser', 'csrf'], $calls);
    }

    public function testHandleSubmissionPassesSessionTeamNotPostedTeamAsFourthArgument(): void
    {
        $controller = $this->buildSubmissionController($this->teamsMapping('gm', 'Session Team'));
        $controller->expects(self::once())
            ->method('processRookieOption')
            ->with('Posted Team', 5, 100, 'Session Team')
            ->willReturn(['success' => false, 'type' => 'ownership_error', 'message' => 'x', 'playerID' => 5]);

        $controller->handleSubmission(
            static fn (): bool => true,
            static fn (): bool => true,
            static fn (): string => 'gm',
            ['teamname' => 'Posted Team', 'playerID' => '5', 'rookieOptionValue' => '100'],
        );
    }

    public function testHandleSubmissionNullSessionTeamPassesNull(): void
    {
        $controller = $this->buildSubmissionController($this->teamsMapping('gm', null));
        $controller->expects(self::once())
            ->method('processRookieOption')
            ->with('Posted Team', 5, 100, null)
            ->willReturn(['success' => false, 'type' => 'ownership_error', 'message' => 'x', 'playerID' => 5]);

        $controller->handleSubmission(
            static fn (): bool => true,
            static fn (): bool => true,
            static fn (): string => 'gm',
            ['teamname' => 'Posted Team', 'playerID' => '5', 'rookieOptionValue' => '100'],
        );
    }

    public function testHandleSubmissionSuccessFromFaRedirectsToFreeAgency(): void
    {
        $controller = $this->buildSubmissionController($this->teamsMapping('gm', 'Heat'));
        $controller->expects(self::once())
            ->method('processRookieOption')
            ->willReturn(['success' => true, 'type' => 'success', 'message' => 'ok', 'playerID' => 5]);

        $result = $controller->handleSubmission(
            static fn (): bool => true,
            static fn (): bool => true,
            static fn (): string => 'gm',
            ['teamname' => 'Heat', 'playerID' => '5', 'rookieOptionValue' => '100', 'from' => 'fa'],
        );

        self::assertSame('modules.php?name=FreeAgency&result=rookie_option_success', $result);
    }

    public function testHandleSubmissionSuccessDefaultRedirectsToPlayerPage(): void
    {
        $controller = $this->buildSubmissionController($this->teamsMapping('gm', 'Heat'));
        $controller->expects(self::once())
            ->method('processRookieOption')
            ->willReturn(['success' => true, 'type' => 'success', 'message' => 'ok', 'playerID' => 5]);

        $result = $controller->handleSubmission(
            static fn (): bool => true,
            static fn (): bool => true,
            static fn (): string => 'gm',
            ['teamname' => 'Heat', 'playerID' => '5', 'rookieOptionValue' => '100', 'from' => ''],
        );

        self::assertSame('modules.php?name=Player&pa=showpage&pid=5&result=rookie_option_success', $result);
    }

    public function testHandleSubmissionSuccessEmailFailedUsesEmailFailedParam(): void
    {
        $controller = $this->buildSubmissionController($this->teamsMapping('gm', 'Heat'));
        $controller->expects(self::once())
            ->method('processRookieOption')
            ->willReturn(['success' => true, 'type' => 'success', 'message' => 'ok', 'playerID' => 5, 'emailSuccess' => false]);

        $result = $controller->handleSubmission(
            static fn (): bool => true,
            static fn (): bool => true,
            static fn (): string => 'gm',
            ['teamname' => 'Heat', 'playerID' => '5', 'rookieOptionValue' => '100'],
        );

        self::assertNotNull($result);
        self::assertStringEndsWith('&result=email_failed', $result);
    }

    public function testHandleSubmissionErrorRedirectsToFormWithEncodedFromAndMessage(): void
    {
        $controller = $this->buildSubmissionController($this->teamsMapping('gm', 'Heat'));
        $controller->expects(self::once())
            ->method('processRookieOption')
            ->willReturn(['success' => false, 'type' => 'validation_error', 'message' => 'Bad & wrong', 'playerID' => 5]);

        $result = $controller->handleSubmission(
            static fn (): bool => true,
            static fn (): bool => true,
            static fn (): string => 'gm',
            ['teamname' => 'Heat', 'playerID' => '5', 'rookieOptionValue' => '100', 'from' => 'a b'],
        );

        self::assertSame(
            'modules.php?name=Player&pa=rookieoption&pid=5&from=a%20b&error=Bad%20%26%20wrong',
            $result,
        );
    }

    public function testHandleSubmissionNonStringFromTreatedAsEmpty(): void
    {
        $controller = $this->buildSubmissionController($this->teamsMapping('gm', 'Heat'));
        $controller->expects(self::once())
            ->method('processRookieOption')
            ->willReturn(['success' => false, 'type' => 'validation_error', 'message' => 'Bad', 'playerID' => 5]);

        $result = $controller->handleSubmission(
            static fn (): bool => true,
            static fn (): bool => true,
            static fn (): string => 'gm',
            ['teamname' => 'Heat', 'playerID' => '5', 'rookieOptionValue' => '100', 'from' => ['x']],
        );

        self::assertNotNull($result);
        self::assertStringContainsString('&from=&error=', $result);
    }

}
