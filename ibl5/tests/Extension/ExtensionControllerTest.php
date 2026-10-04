<?php

declare(strict_types=1);

namespace Tests\Extension;

use Extension\Contracts\ExtensionProcessorInterface;
use Extension\ExtensionController;
use League\League;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;

/**
 * Behavioral tests for the contract-extension POST gates (modules/Player/extension.php shim).
 */
final class ExtensionControllerTest extends TestCase
{
    private const HOME_URL = '/ibl5/index.php';
    private const FORBIDDEN_URL = '/ibl5/index.php?result=extension_forbidden';
    private const TEAM_BASE_URL = '/ibl5/modules.php?name=Team&op=team&teamid=3&display=contracts';

    private function teams(?string $sessionTeam, ?int $tid = 3): TeamIdentityRepositoryInterface
    {
        $teams = self::createStub(TeamIdentityRepositoryInterface::class);
        $teams->method('getTeamnameFromUsername')->willReturn($sessionTeam);
        $teams->method('getTidFromTeamname')->willReturn($tid);

        return $teams;
    }

    /**
     * Run handleSubmission() with all gates passing and a fixed username.
     *
     * @param array<array-key, mixed> $post
     */
    private function submit(ExtensionController $controller, array $post): string
    {
        return $controller->handleSubmission(
            static fn (): bool => true,
            static fn (): bool => true,
            static fn (): string => 'gm',
            $post,
        );
    }

    public function testInvalidCsrfRedirectsToIndexAndRunsNoLaterGate(): void
    {
        $processor = $this->createMock(ExtensionProcessorInterface::class);
        $processor->expects(self::never())->method('processExtension');
        $controller = new ExtensionController($this->teams('Boston'), $processor);
        /** @var list<string> $calls */
        $calls = [];

        $result = $controller->handleSubmission(
            static function () use (&$calls): bool {
                $calls[] = 'csrf';
                return false;
            },
            static function () use (&$calls): bool {
                $calls[] = 'isUser';
                return true;
            },
            static function () use (&$calls): string {
                $calls[] = 'username';
                return 'gm';
            },
            ['teamName' => 'Boston'],
        );

        self::assertSame(self::HOME_URL, $result);
        self::assertSame(['csrf'], $calls);
    }

    public function testUnauthenticatedRedirectsToIndexAndSkipsCookieDecode(): void
    {
        $processor = $this->createMock(ExtensionProcessorInterface::class);
        $processor->expects(self::never())->method('processExtension');
        $controller = new ExtensionController($this->teams('Boston'), $processor);
        /** @var list<string> $calls */
        $calls = [];

        $result = $controller->handleSubmission(
            static function () use (&$calls): bool {
                $calls[] = 'csrf';
                return true;
            },
            static function () use (&$calls): bool {
                $calls[] = 'isUser';
                return false;
            },
            static function () use (&$calls): string {
                $calls[] = 'username';
                return 'gm';
            },
            ['teamName' => 'Boston'],
        );

        self::assertSame(self::HOME_URL, $result);
        self::assertSame(['csrf', 'isUser'], $calls);
    }

    /**
     * @return array<string, array{0: ?string, 1: string}>
     */
    public static function teamlessSessionTeams(): array
    {
        return [
            'null session team' => [null, ''],
            'empty session team' => ['', ''],
            'free agents session team' => [League::FREE_AGENTS_TEAM_NAME, League::FREE_AGENTS_TEAM_NAME],
        ];
    }

    #[DataProvider('teamlessSessionTeams')]
    public function testTeamlessSessionRedirectsToIndexWithoutProcessing(?string $sessionTeam, string $postedTeam): void
    {
        $processor = $this->createMock(ExtensionProcessorInterface::class);
        $processor->expects(self::never())->method('processExtension');
        $controller = new ExtensionController($this->teams($sessionTeam), $processor);

        $result = $this->submit($controller, ['teamName' => $postedTeam]);

        self::assertSame(self::HOME_URL, $result);
    }

    public function testPostedTeamMismatchRedirectsForbiddenWithoutProcessing(): void
    {
        $processor = $this->createMock(ExtensionProcessorInterface::class);
        $processor->expects(self::never())->method('processExtension');
        $controller = new ExtensionController($this->teams('Boston'), $processor);

        $result = $this->submit($controller, ['teamName' => 'Chicago']);

        self::assertSame(self::FORBIDDEN_URL, $result);
    }

    public function testMissingPostedTeamRedirectsForbiddenWithoutProcessing(): void
    {
        $processor = $this->createMock(ExtensionProcessorInterface::class);
        $processor->expects(self::never())->method('processExtension');
        $controller = new ExtensionController($this->teams('Boston'), $processor);

        self::assertSame(self::FORBIDDEN_URL, $this->submit($controller, []));
        self::assertSame(self::FORBIDDEN_URL, $this->submit($controller, ['teamName' => ['Boston']]));
    }

    public function testProcessorReceivesSessionTeamAndCastOffer(): void
    {
        $processor = $this->createMock(ExtensionProcessorInterface::class);
        $processor->expects(self::once())
            ->method('processExtension')
            ->with([
                'teamName' => 'Boston',
                'playerID' => 7,
                'playerName' => 'Test Player',
                'offer' => ['year1' => 5, 'year2' => 6, 'year3' => 7, 'year4' => 0, 'year5' => 0],
                'demands' => ['total' => 1500, 'years' => 3],
            ])
            ->willReturn(['success' => false, 'error' => 'x']);
        $controller = new ExtensionController($this->teams('Boston'), $processor);

        $this->submit($controller, [
            'teamName' => 'Boston',
            'playerID' => '7',
            'playerName' => 'Test Player',
            'offerYear1' => '5',
            'offerYear2' => '6',
            'offerYear3' => '7',
            'demandsTotal' => '1500',
            'demandsYears' => '3',
        ]);
    }

    public function testUnknownTeamIdRedirectsToIndexAfterProcessing(): void
    {
        $processor = $this->createMock(ExtensionProcessorInterface::class);
        $processor->expects(self::once())
            ->method('processExtension')
            ->willReturn(['success' => false, 'error' => 'x']);
        $controller = new ExtensionController($this->teams('Boston', null), $processor);

        $result = $this->submit($controller, ['teamName' => 'Boston']);

        self::assertSame(self::HOME_URL, $result);
    }

    public function testErrorResultRedirectsWithEncodedError(): void
    {
        $processor = $this->createMock(ExtensionProcessorInterface::class);
        $processor->expects(self::once())
            ->method('processExtension')
            ->willReturn(['success' => false, 'error' => 'Bad & wrong']);
        $controller = new ExtensionController($this->teams('Boston'), $processor);

        $result = $this->submit($controller, ['teamName' => 'Boston']);

        self::assertSame(
            '/ibl5/modules.php?name=Team&op=team&teamid=3&display=contracts&result=extension_error&msg=Bad%20%26%20wrong',
            $result,
        );
    }

    public function testAcceptedResultRedirectsWithEncodedMessage(): void
    {
        $processor = $this->createMock(ExtensionProcessorInterface::class);
        $processor->expects(self::once())
            ->method('processExtension')
            ->willReturn($this->successResult(true, 'Deal done'));
        $controller = new ExtensionController($this->teams('Boston'), $processor);

        $result = $this->submit($controller, ['teamName' => 'Boston']);

        self::assertSame(self::TEAM_BASE_URL . '&result=extension_accepted&msg=Deal%20done', $result);
    }

    public function testRejectedResultRedirectsWithEncodedMessage(): void
    {
        $processor = $this->createMock(ExtensionProcessorInterface::class);
        $processor->expects(self::once())
            ->method('processExtension')
            ->willReturn($this->successResult(false, 'No deal'));
        $controller = new ExtensionController($this->teams('Boston'), $processor);

        $result = $this->submit($controller, ['teamName' => 'Boston']);

        self::assertSame(self::TEAM_BASE_URL . '&result=extension_rejected&msg=No%20deal', $result);
    }

    /**
     * @return array{success: true, accepted: bool, message: string, offerValue: float, demandValue: float, modifier: float, extensionYears: int, offerInMillions: float, offerDetails: string, discordNotificationSent: bool, discordChannel: string}
     */
    private function successResult(bool $accepted, string $message): array
    {
        return [
            'success' => true,
            'accepted' => $accepted,
            'message' => $message,
            'offerValue' => 1.0,
            'demandValue' => 1.0,
            'modifier' => 1.0,
            'extensionYears' => 3,
            'offerInMillions' => 1.0,
            'offerDetails' => '',
            'discordNotificationSent' => false,
            'discordChannel' => '',
        ];
    }
}
