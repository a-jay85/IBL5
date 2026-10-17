<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use Auth\Contracts\AuthServiceInterface;
use League\League;
use PHPUnit\Framework\TestCase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Security\CsrfGuard;
use TradeSuggestions\Contracts\TeamModeRepositoryInterface;
use TradeSuggestions\TeamModeController;
use TradeSuggestions\TeamModeGuesser;
use TradeSuggestions\TeamModeService;
use TradeSuggestions\TeamModeSetting;

class TeamModeControllerTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        CsrfGuard::setTestClock(null);
    }

    public function testRejectsNonPostRequest(): void
    {
        $repo = $this->recordingRepo();
        $post = $this->withValidCsrfToken(['trade_mode' => 'rebuild']);

        $url = $this->controller($repo, 'gm1', 'Team One', 1)->handleSetMode('GET', $post);

        self::assertSame(TeamModeController::HOME . '?trade_mode_error=method', $url);
        self::assertSame([], $repo->saves);
    }

    public function testRejectsMissingCsrfToken(): void
    {
        $repo = $this->recordingRepo();
        $post = ['trade_mode' => 'rebuild'];
        $_POST = $post;

        $url = $this->controller($repo, 'gm1', 'Team One', 1)->handleSetMode('POST', $post);

        self::assertSame(TeamModeController::HOME . '?trade_mode_error=csrf', $url);
        self::assertSame([], $repo->saves);
    }

    public function testRejectsInvalidCsrfToken(): void
    {
        $repo = $this->recordingRepo();
        CsrfGuard::clearTokens('trade_offer');
        $post = ['trade_mode' => 'rebuild', '_csrf_token' => CsrfGuard::generateRawToken('trade_offer')];
        $_POST = $post;

        $url = $this->controller($repo, 'gm1', 'Team One', 1)->handleSetMode('POST', $post);

        self::assertSame(TeamModeController::HOME . '?trade_mode_error=csrf', $url);
        self::assertSame([], $repo->saves);
    }

    public function testRejectsUnauthenticatedUser(): void
    {
        $repo = $this->recordingRepo();
        $post = $this->withValidCsrfToken(['trade_mode' => 'rebuild']);

        $url = $this->controller($repo, '', 'Team One', 1)->handleSetMode('POST', $post);

        self::assertSame(TeamModeController::HOME . '?trade_mode_error=auth', $url);
        self::assertSame([], $repo->saves);
    }

    public function testRejectsFreeAgentsGm(): void
    {
        $repo = $this->recordingRepo();
        $post = $this->withValidCsrfToken(['trade_mode' => 'rebuild']);

        $url = $this->controller($repo, 'fagm', League::FREE_AGENTS_TEAM_NAME, 0)->handleSetMode('POST', $post);

        self::assertSame(TeamModeController::HOME . '?trade_mode_error=auth', $url);
        self::assertSame([], $repo->saves);
    }

    public function testRejectsNonFranchiseTeam(): void
    {
        $repo = $this->recordingRepo();
        $post = $this->withValidCsrfToken(['trade_mode' => 'rebuild']);

        $url = $this->controller($repo, 'asgm', 'Rookies', League::ROOKIES_TEAMID)->handleSetMode('POST', $post);

        self::assertSame(TeamModeController::HOME . '?trade_mode_error=auth', $url);
        self::assertSame([], $repo->saves);
    }

    public function testRejectsUnknownModeValue(): void
    {
        foreach (['tank', '', ['rebuild']] as $value) {
            $repo = $this->recordingRepo();
            $post = $this->withValidCsrfToken(['trade_mode' => $value]);

            $url = $this->controller($repo, 'gm1', 'Team One', 1)->handleSetMode('POST', $post);

            self::assertSame(TeamModeController::HOME . '?trade_mode_error=value', $url);
            self::assertSame([], $repo->saves);
        }
    }

    public function testWritesSessionTeamIgnoringPostedTeamId(): void
    {
        $repo = $this->recordingRepo();
        $post = $this->withValidCsrfToken(['trade_mode' => 'rebuild', 'teamid' => '2', 'tid' => '2', 'team' => 'Team Two']);

        $this->controller($repo, 'gm1', 'Team One', 1)->handleSetMode('POST', $post);

        // A controller that read $post['teamid'] would record tid 2 here.
        self::assertSame([['tid' => 1, 'setting' => TeamModeSetting::Rebuild]], $repo->saves);
    }

    public function testWritesValidModeAndRedirectsToPanel(): void
    {
        $repo = $this->recordingRepo();
        $post = $this->withValidCsrfToken(['trade_mode' => 'win_now']);

        $url = $this->controller($repo, 'gm1', 'Team One', 1)->handleSetMode('post', $post);

        self::assertSame(TeamModeController::HOME . '#trade-suggestions', $url);
        self::assertStringEndsWith('#trade-suggestions', $url);
        self::assertSame([['tid' => 1, 'setting' => TeamModeSetting::WinNow]], $repo->saves);
    }

    /**
     * Mint a valid trade_mode CSRF token and place it in both $_POST (read by
     * CsrfGuard) and the returned post array (passed to the controller).
     *
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private function withValidCsrfToken(array $post): array
    {
        CsrfGuard::clearTokens('trade_mode');
        $post['_csrf_token'] = CsrfGuard::generateRawToken('trade_mode');
        $_POST = $post;

        return $post;
    }

    /**
     * @return TeamModeRepositoryInterface&object{saves: list<array{tid: int, setting: TeamModeSetting}>}
     */
    private function recordingRepo(): TeamModeRepositoryInterface
    {
        return new class implements TeamModeRepositoryInterface {
            /** @var list<array{tid: int, setting: TeamModeSetting}> */
            public array $saves = [];

            public function getStoredSetting(int $tid): TeamModeSetting
            {
                return TeamModeSetting::Auto;
            }

            public function getAllStoredSettings(): array
            {
                return [];
            }

            public function saveSetting(int $tid, TeamModeSetting $setting): void
            {
                $this->saves[] = ['tid' => $tid, 'setting' => $setting];
            }

            public function getModeInputs(int $tid): array
            {
                return ['wins' => 0, 'losses' => 0, 'avgAge' => 0.0];
            }
        };
    }

    private function controller(
        TeamModeRepositoryInterface $repo,
        string $username,
        string $teamName,
        int $tid,
    ): TeamModeController {
        $auth = self::createStub(AuthServiceInterface::class);
        $auth->method('getUsername')->willReturn($username === '' ? null : $username);

        $identity = self::createStub(TeamIdentityRepositoryInterface::class);
        $identity->method('getTeamnameFromUsername')->willReturn($teamName);
        $identity->method('getTidFromTeamname')->willReturn($tid);

        return new TeamModeController(
            $auth,
            $identity,
            new TeamModeService($repo, new TeamModeGuesser())
        );
    }
}
