<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Feedback;

use Auth\Contracts\AuthServiceInterface;
use League\League;
use PHPUnit\Framework\TestCase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Security\CsrfGuard;
use Tests\Clock\FixedClock;
use TradeSuggestions\Feedback\Contracts\FeedbackRepositoryInterface;
use TradeSuggestions\Feedback\DealKey;
use TradeSuggestions\Feedback\PanelSessionState;
use TradeSuggestions\Feedback\SuggestionFeedbackController;

final class SuggestionFeedbackControllerTest extends TestCase
{
    private const FORM = 'trade_suggestion_action';

    private string $dealKey;
    private string $coreKey;

    /** @var array<mixed> */
    private array $store = [];

    protected function setUp(): void
    {
        $_SESSION = [];
        $_POST = [];
        $this->dealKey = str_repeat('a', 40);
        $this->coreKey = str_repeat('b', 40);
        $this->store = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        CsrfGuard::setTestClock(null);
    }

    // ---- gate order -------------------------------------------------

    public function testGetIsRejected(): void
    {
        $repo = $this->recordingRepo();
        $post = $this->hidePost();

        $url = $this->controller($repo)->handle('GET', $post);

        self::assertSame(SuggestionFeedbackController::HOME . '?trade_suggestion_error=method', $url);
        self::assertSame([], $repo->records);
    }

    public function testMissingTokenRejected(): void
    {
        $repo = $this->recordingRepo();
        $post = ['action' => 'hide', 'deal_key' => $this->dealKey, 'reasons' => ['TOO_OLD']];
        $_POST = $post;

        $url = $this->controller($repo)->handle('POST', $post);

        self::assertSame(SuggestionFeedbackController::HOME . '?trade_suggestion_error=csrf', $url);
        self::assertSame([], $repo->records);
    }

    public function testBadTokenRejected(): void
    {
        $repo = $this->recordingRepo();
        CsrfGuard::clearTokens(self::FORM);
        $post = $this->hidePost();
        $post['_csrf_token'] = str_repeat('0', 64);
        $_POST = $post;

        $url = $this->controller($repo)->handle('POST', $post);

        self::assertSame(SuggestionFeedbackController::HOME . '?trade_suggestion_error=csrf', $url);
        self::assertSame([], $repo->records);
    }

    public function testModeTokenRejected(): void
    {
        $repo = $this->recordingRepo();
        CsrfGuard::clearTokens('trade_mode');
        $post = $this->hidePost();
        $post['_csrf_token'] = CsrfGuard::generateRawToken('trade_mode');
        $_POST = $post;

        $url = $this->controller($repo)->handle('POST', $post);

        self::assertSame(SuggestionFeedbackController::HOME . '?trade_suggestion_error=csrf', $url);
        self::assertSame([], $repo->records);
    }

    public function testRejectsExpiredCsrfToken(): void
    {
        $repo = $this->recordingRepo();
        $clock = new FixedClock(1_000_000);
        CsrfGuard::setTestClock($clock);
        $post = $this->withValidToken($this->hidePost());
        $clock->setNow(1_000_000 + 14400 + 1);

        $url = $this->controller($repo)->handle('POST', $post);

        self::assertSame(SuggestionFeedbackController::HOME . '?trade_suggestion_error=csrf', $url);
        self::assertSame([], $repo->records);
    }

    public function testNoTeamRejected(): void
    {
        $repo = $this->recordingRepo();
        $post = $this->withValidToken($this->hidePost());

        $url = $this->controller($repo, '', 'Team One', 1)->handle('POST', $post);

        self::assertSame(SuggestionFeedbackController::HOME . '?trade_suggestion_error=auth', $url);
        self::assertSame([], $repo->records);
    }

    public function testRejectsFreeAgentsUser(): void
    {
        $repo = $this->recordingRepo();
        $post = $this->withValidToken($this->hidePost());

        $url = $this->controller($repo, 'fagm', League::FREE_AGENTS_TEAM_NAME, 0)->handle('POST', $post);

        self::assertSame(SuggestionFeedbackController::HOME . '?trade_suggestion_error=auth', $url);
        self::assertSame([], $repo->records);
    }

    public function testRejectsNonFranchiseTid(): void
    {
        $repo = $this->recordingRepo();
        $post = $this->withValidToken($this->hidePost());

        $url = $this->controller($repo, 'asgm', 'Rookies', 40)->handle('POST', $post);

        self::assertSame(SuggestionFeedbackController::HOME . '?trade_suggestion_error=auth', $url);
        self::assertSame([], $repo->records);
    }

    public function testPostedTeamIdIgnored(): void
    {
        $repo = $this->recordingRepo();
        $post = $this->withValidToken($this->hidePost(['tid' => '9', 'teamid' => '9']));

        $this->controller($repo)->handle('POST', $post);

        self::assertCount(1, $repo->records);
        self::assertSame(1, $repo->records[0]['tid']);
    }

    public function testUnknownActionRejected(): void
    {
        $repo = $this->recordingRepo();
        $post = $this->withValidToken(['action' => 'delete']);

        $url = $this->controller($repo)->handle('POST', $post);

        self::assertSame(SuggestionFeedbackController::HOME . '?trade_suggestion_error=action', $url);
        self::assertSame([], $repo->records);
    }

    // ---- hide validation ---------------------------------------------

    public function testMalformedDealKeyRejected(): void
    {
        $repo = $this->recordingRepo();
        $post = $this->withValidToken($this->hidePost(['deal_key' => 'abc']));

        $url = $this->controller($repo)->handle('POST', $post);

        self::assertSame(SuggestionFeedbackController::HOME . '?trade_suggestion_error=stale', $url);
        self::assertSame([], $repo->records);
    }

    public function testStaleDealKeyRejected(): void
    {
        $repo = $this->recordingRepo();
        $post = $this->withValidToken($this->hidePost(['deal_key' => str_repeat('c', 40)]));

        $url = $this->controller($repo)->handle('POST', $post);

        self::assertSame(SuggestionFeedbackController::HOME . '?trade_suggestion_error=stale', $url);
        self::assertSame([], $repo->records);
    }

    public function testUnknownReasonRejected(): void
    {
        $repo = $this->recordingRepo();
        $post = $this->withValidToken($this->hidePost(['reasons' => ['TOO_OLD', 'SMELLS']]));

        $url = $this->controller($repo)->handle('POST', $post);

        self::assertSame(SuggestionFeedbackController::HOME . '?trade_suggestion_error=reason', $url);
        self::assertSame([], $repo->records);
    }

    public function testEmptyReasonsRejected(): void
    {
        $repo = $this->recordingRepo();
        $post = $this->withValidToken($this->hidePost(['reasons' => []]));

        $url = $this->controller($repo)->handle('POST', $post);

        self::assertSame(SuggestionFeedbackController::HOME . '?trade_suggestion_error=reason', $url);
        self::assertSame([], $repo->records);
    }

    public function testFreeTextLengthBoundary(): void
    {
        $repo = $this->recordingRepo();
        $post = $this->withValidToken($this->hidePost(['free_text' => str_repeat('é', 501)]));

        $url = $this->controller($repo)->handle('POST', $post);

        self::assertSame(SuggestionFeedbackController::HOME . '?trade_suggestion_error=length', $url);
        self::assertSame([], $repo->records);

        $post = $this->withValidToken($this->hidePost(['free_text' => str_repeat('é', 500)]));
        $url = $this->controller($repo)->handle('POST', $post);

        self::assertSame(SuggestionFeedbackController::HOME . '?trade_suggestion_hidden=1#trade-suggestions', $url);
        self::assertCount(1, $repo->records);
        self::assertSame(str_repeat('é', 500), $repo->records[0]['text']);
    }

    // ---- hide success ------------------------------------------------

    public function testHideRecordsFromSessionEntry(): void
    {
        $repo = $this->recordingRepo();
        $post = $this->withValidToken($this->hidePost([
            'reasons' => ['OTHER', 'TOO_OLD'],
            'free_text' => '<script>alert(1)</script>',
        ]));

        $this->controller($repo)->handle('POST', $post);

        self::assertCount(1, $repo->records);
        $record = $repo->records[0];
        self::assertSame(1, $record['tid']);
        self::assertSame(2, $record['partnerTid']);
        self::assertEquals(new DealKey($this->dealKey, $this->coreKey), $record['key']);
        self::assertSame('{}', $record['dealJson']);
        self::assertSame('TOO_OLD,OTHER', $record['reasons']);
        self::assertSame('<script>alert(1)</script>', $record['text']);
    }

    public function testHideSuccessRedirectsHiddenFlag(): void
    {
        $repo = $this->recordingRepo();
        $post = $this->withValidToken($this->hidePost());

        $url = $this->controller($repo)->handle('POST', $post);

        self::assertSame('/ibl5/index.php?trade_suggestion_hidden=1#trade-suggestions', $url);
    }

    public function testHideTrimsSurroundingWhitespace(): void
    {
        $repo = $this->recordingRepo();
        $post = $this->withValidToken($this->hidePost(['free_text' => "  too old \n"]));

        $this->controller($repo)->handle('POST', $post);

        self::assertSame('too old', $repo->records[0]['text']);
    }

    public function testSecondHideOfSameIdeaIsStale(): void
    {
        $repo = $this->recordingRepo();
        $controller = $this->controller($repo);

        $first = $controller->handle('POST', $this->withValidToken($this->hidePost()));
        $second = $controller->handle('POST', $this->withValidToken($this->hidePost()));

        self::assertSame(SuggestionFeedbackController::HOME . '?trade_suggestion_hidden=1#trade-suggestions', $first);
        self::assertSame(SuggestionFeedbackController::HOME . '?trade_suggestion_error=stale', $second);
        self::assertCount(1, $repo->records);
    }

    // ---- refresh -----------------------------------------------------

    public function testRefreshAdvancesOffset(): void
    {
        $repo = $this->recordingRepo();
        $controller = $this->controller($repo, eligible: 12);
        $post = $this->withValidToken(['action' => 'refresh']);

        $url = $controller->handle('POST', $post);

        self::assertSame('/ibl5/index.php#trade-suggestions', $url);
        self::assertSame(3, $this->state()->offset());
    }

    public function testRefreshWrapsAtEndOfList(): void
    {
        $repo = $this->recordingRepo();
        $controller = $this->controller($repo, eligible: 12);
        for ($i = 0; $i < 3; $i++) {
            $controller->handle('POST', $this->withValidToken(['action' => 'refresh']));
        }
        self::assertSame(9, $this->state()->offset());

        $controller->handle('POST', $this->withValidToken(['action' => 'refresh']));

        self::assertSame(0, $this->state()->offset());
    }

    public function testRefreshWithFewerThanFourEligibleStaysAtZero(): void
    {
        $repo = $this->recordingRepo();
        $controller = $this->controller($repo, eligible: 3);

        $controller->handle('POST', $this->withValidToken(['action' => 'refresh']));

        self::assertSame(0, $this->state()->offset());
    }

    public function testRefreshDoesNotTouchRepository(): void
    {
        $repo = $this->recordingRepo();
        $controller = $this->controller($repo, eligible: 12);

        $controller->handle('POST', $this->withValidToken(['action' => 'refresh']));

        self::assertSame([], $repo->records);
    }

    // ---- helpers -----------------------------------------------------

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function hidePost(array $overrides = []): array
    {
        return array_replace(
            ['action' => 'hide', 'deal_key' => $this->dealKey, 'reasons' => ['TOO_OLD'], 'free_text' => ''],
            $overrides,
        );
    }

    /**
     * Mint a valid token and place it in both $_POST (read by CsrfGuard) and
     * the returned post array.
     *
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private function withValidToken(array $post): array
    {
        CsrfGuard::clearTokens(self::FORM);
        $post['_csrf_token'] = CsrfGuard::generateRawToken(self::FORM);
        $_POST = $post;

        return $post;
    }

    private function state(): PanelSessionState
    {
        return new PanelSessionState($this->store);
    }

    /**
     * @return FeedbackRepositoryInterface&object{records: list<array{tid: int, partnerTid: int, key: DealKey, dealJson: string, reasons: string, text: string}>}
     */
    private function recordingRepo(): FeedbackRepositoryInterface
    {
        return new class implements FeedbackRepositoryInterface {
            /** @var list<array{tid: int, partnerTid: int, key: DealKey, dealJson: string, reasons: string, text: string}> */
            public array $records = [];

            public function hiddenKeys(int $tid): array
            {
                return ['deal' => [], 'core' => []];
            }

            public function record(int $tid, int $partnerTid, DealKey $key, string $dealJson, string $reasonCodes, string $freeText): void
            {
                $this->records[] = [
                    'tid' => $tid,
                    'partnerTid' => $partnerTid,
                    'key' => $key,
                    'dealJson' => $dealJson,
                    'reasons' => $reasonCodes,
                    'text' => $freeText,
                ];
            }

            public function latestForTeam(int $tid): ?array
            {
                return null;
            }
        };
    }

    /**
     * @param int $eligible Number of offered ideas; the first is the hideable one
     */
    private function controller(
        FeedbackRepositoryInterface $repo,
        string $username = 'gm1',
        string $teamName = 'Team One',
        int $tid = 1,
        int $eligible = 1,
    ): SuggestionFeedbackController {
        $map = [$this->dealKey => ['core' => $this->coreKey, 'partnerTid' => 2, 'dealJson' => '{}']];
        for ($i = 1; $i < $eligible; $i++) {
            $map[sprintf('%040x', $i)] = ['core' => str_repeat('d', 40), 'partnerTid' => 3, 'dealJson' => '{}'];
        }
        $state = $this->state();
        $state->forSimKey('sim');
        $state->setEligible($map);

        $auth = self::createStub(AuthServiceInterface::class);
        $auth->method('getUsername')->willReturn($username === '' ? null : $username);

        $identity = self::createStub(TeamIdentityRepositoryInterface::class);
        $identity->method('getTeamnameFromUsername')->willReturn($teamName);
        $identity->method('getTidFromTeamname')->willReturn($tid);

        return new SuggestionFeedbackController($auth, $identity, $repo, $state);
    }
}
