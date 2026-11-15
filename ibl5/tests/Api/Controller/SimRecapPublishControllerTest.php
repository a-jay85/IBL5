<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\SimRecapPublishClaimController;
use Api\Controller\SimRecapPublishFinishController;
use Api\Controller\SimRecapPublishProgressController;
use Api\Repository\ApiKeyRepository;
use Api\Response\JsonResponder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Season\Contracts\SeasonQueryRepositoryInterface;
use SimRecap\MentionMapBuilder;
use SimRecap\PublishScriptBuilder;
use SimRecap\SimPublishProgressRepository;
use SimRecap\SimRecapContextRepository;
use SimRecap\SimSummaryRepository;
use Tests\WideUnit\Mocks\MockDatabase;

class SimRecapPublishControllerTest extends TestCase
{
    private const TOKEN = '0b6f3f0e-6f43-4f0c-9d1a-2c3b4d5e6f70';
    private const THREAD_ID = '123456789012345678';

    protected function setUp(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'test-bot-key';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_API_KEY']);
    }

    /**
     * Key lookup stub returning a key row with the given rate_limit_tier.
     */
    private static function keys(string $tier = 'unlimited'): ApiKeyRepository
    {
        $keys = self::createStub(ApiKeyRepository::class);
        $keys->method('findByHash')->willReturn([
            'key_hash'         => hash('sha256', 'test-bot-key'),
            'key_prefix'       => 'test-bot',
            'owner_name'       => 'x',
            'permission_level' => 'public',
            'rate_limit_tier'  => $tier,
        ]);
        return $keys;
    }

    /**
     * The builder is final, so it is built for real from stubbed collaborators.
     *
     * @param array<string, mixed>|null $summary
     * @param list<array<string, mixed>> $games
     */
    private static function scriptBuilder(?array $summary, array $games = []): PublishScriptBuilder
    {
        $summaries = self::createStub(SimSummaryRepository::class);
        $summaries->method('find')->willReturn($summary);
        $summaries->method('findDisplayableGameRecaps')->willReturn($games);

        $teams = self::createStub(TeamIdentityRepositoryInterface::class);
        $teams->method('getAllRealTeams')->willReturn([]);

        $seasons = self::createStub(SeasonQueryRepositoryInterface::class);
        $seasons->method('calculatePhaseSimNumber')->willReturn(10);

        return new PublishScriptBuilder(
            $summaries,
            new SimRecapContextRepository(new MockDatabase()),
            new MentionMapBuilder($teams),
            $seasons,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function summaryRow(): array
    {
        return [
            'sim' => 7, 'status' => 'done', 'recap_text' => null,
            'intro_text' => 'Intro paragraph.', 'outro_text' => 'Standings paragraph.',
            'themes_used' => null, 'attempts' => 1, 'claimed_at' => null, 'blocked_until' => null,
            'generated_at' => null, 'route' => 'any', 'routed_at' => null, 'claimed_by' => null,
            'last_cause' => null, 'flaky_failures' => 0, 'transport_failures' => 0, 'usage_waits' => 0,
            'auth_failed_hosts' => '', 'notified_state' => null,
        ];
    }

    private function claimController(SimPublishProgressRepository $repo, ?PublishScriptBuilder $builder = null, string $tier = 'unlimited'): SimRecapPublishClaimController
    {
        return new SimRecapPublishClaimController($repo, $builder ?? self::scriptBuilder(null), self::keys($tier));
    }

    // ── claim-next ─────────────────────────────────────────────────────────────

    public function testClaimNextReturnsScriptForClaimableSim(): void
    {
        $repo = $this->createMock(SimPublishProgressRepository::class);
        $repo->expects($this->once())->method('claimNext')->with(self::TOKEN)->willReturn([
            'sim' => 7, 'title_override' => null, 'thread_id' => self::THREAD_ID, 'last_step' => 3,
        ]);
        $builder = self::scriptBuilder(self::summaryRow(), [
            ['game_date' => '2008-12-01', 'sort_order' => 1, 'recap_text' => 'Game recap.'],
        ]);

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->never())->method('error');
        $responder->expects($this->once())->method('success')->with(
            self::callback(static function (mixed $data): bool {
                return is_array($data)
                    && $data['sim'] === 7
                    && $data['threadId'] === '123456789012345678'
                    && $data['lastStep'] === 3
                    && is_array($data['script'])
                    && $data['script']['sim'] === 7
                    && is_array($data['script']['steps'])
                    && $data['script']['steps'][0]['kind'] === 'thread_start';
            })
        );

        $this->claimController($repo, $builder)->handle([], [], $responder, ['token' => self::TOKEN]);
    }

    public function testClaimNextReturns204WhenNothingClaimable(): void
    {
        $repo = self::createStub(SimPublishProgressRepository::class);
        $repo->method('claimNext')->willReturn(null);

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())->method('raw')->with([], 204);
        $responder->expects($this->never())->method('success');
        $responder->expects($this->never())->method('error');

        $this->claimController($repo)->handle([], [], $responder, ['token' => self::TOKEN]);
    }

    public function testClaimNextReturns422WhenScriptUnavailable(): void
    {
        $repo = self::createStub(SimPublishProgressRepository::class);
        $repo->method('claimNext')->willReturn([
            'sim' => 7, 'title_override' => null, 'thread_id' => null, 'last_step' => 0,
        ]);

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())->method('error')->with(422, 'script_unavailable', self::anything());
        $responder->expects($this->never())->method('success');

        // A missing summary makes the real builder throw PublishScriptUnavailable.
        $this->claimController($repo, self::scriptBuilder(null))->handle([], [], $responder, ['token' => self::TOKEN]);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function malformedTokenProvider(): array
    {
        return [
            'missing'         => [null],
            'empty'           => [''],
            'not a uuid'      => ['not-a-token'],
            'uppercase uuid'  => ['0B6F3F0E-6F43-4F0C-9D1A-2C3B4D5E6F70'],
            'trailing junk'   => [self::TOKEN . 'x'],
            'integer'         => [12345],
        ];
    }

    #[DataProvider('malformedTokenProvider')]
    public function testClaimNextRejectsMalformedToken(mixed $token): void
    {
        $repo = $this->createMock(SimPublishProgressRepository::class);
        $repo->expects($this->never())->method('claimNext');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())->method('error')->with(400, 'bad_request', self::anything());

        $this->claimController($repo)->handle([], [], $responder, $token === null ? [] : ['token' => $token]);
    }

    public function testClaimNextRejectsMissingBody(): void
    {
        $repo = $this->createMock(SimPublishProgressRepository::class);
        $repo->expects($this->never())->method('claimNext');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())->method('error')->with(400, 'bad_request', self::anything());

        $this->claimController($repo)->handle([], [], $responder, null);
    }

    // ── progress ───────────────────────────────────────────────────────────────

    public function testProgressAdvancesClaimedRow(): void
    {
        $repo = $this->createMock(SimPublishProgressRepository::class);
        $repo->expects($this->once())->method('advance')->with(7, self::TOKEN, 2, self::THREAD_ID)->willReturn(true);

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->never())->method('error');
        $responder->expects($this->once())->method('success');

        (new SimRecapPublishProgressController($repo, self::keys()))->handle(
            [],
            [],
            $responder,
            ['sim' => 7, 'token' => self::TOKEN, 'step' => 2, 'threadId' => self::THREAD_ID]
        );
    }

    public function testProgressAcceptsNullThreadId(): void
    {
        $repo = $this->createMock(SimPublishProgressRepository::class);
        $repo->expects($this->once())->method('advance')->with(7, self::TOKEN, 1, null)->willReturn(true);

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())->method('success');

        (new SimRecapPublishProgressController($repo, self::keys()))->handle(
            [],
            [],
            $responder,
            ['sim' => 7, 'token' => self::TOKEN, 'step' => 1, 'threadId' => null]
        );
    }

    public function testProgressReturns409WhenAdvanceRefused(): void
    {
        $repo = $this->createMock(SimPublishProgressRepository::class);
        $repo->expects($this->once())->method('advance')->willReturn(false);

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())->method('error')->with(409, 'conflict', self::anything());
        $responder->expects($this->never())->method('success');

        (new SimRecapPublishProgressController($repo, self::keys()))->handle(
            [],
            [],
            $responder,
            ['sim' => 7, 'token' => self::TOKEN, 'step' => 5, 'threadId' => null]
        );
    }

    public function testProgressRejectsShortThreadId(): void
    {
        $repo = $this->createMock(SimPublishProgressRepository::class);
        $repo->expects($this->never())->method('advance');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())->method('error')->with(400, 'bad_request', self::anything());

        (new SimRecapPublishProgressController($repo, self::keys()))->handle(
            [],
            [],
            $responder,
            ['sim' => 7, 'token' => self::TOKEN, 'step' => 1, 'threadId' => '12345']
        );
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function badProgressBodyProvider(): array
    {
        return [
            'sim zero'            => [['sim' => 0, 'token' => self::TOKEN, 'step' => 1, 'threadId' => null]],
            'sim string'          => [['sim' => '7', 'token' => self::TOKEN, 'step' => 1, 'threadId' => null]],
            'negative step'       => [['sim' => 7, 'token' => self::TOKEN, 'step' => -1, 'threadId' => null]],
            'step over the cap'   => [['sim' => 7, 'token' => self::TOKEN, 'step' => 10001, 'threadId' => null]],
            'step string'         => [['sim' => 7, 'token' => self::TOKEN, 'step' => '1', 'threadId' => null]],
            'bad token'           => [['sim' => 7, 'token' => 'nope', 'step' => 1, 'threadId' => null]],
            'integer thread id'   => [['sim' => 7, 'token' => self::TOKEN, 'step' => 1, 'threadId' => 123456789012345678]],
            'long thread id'      => [['sim' => 7, 'token' => self::TOKEN, 'step' => 1, 'threadId' => '123456789012345678901']],
            'empty body'          => [[]],
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('badProgressBodyProvider')]
    public function testProgressRejectsBadInputBeforeTouchingTheRepository(array $body): void
    {
        $repo = $this->createMock(SimPublishProgressRepository::class);
        $repo->expects($this->never())->method('advance');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())->method('error')->with(400, 'bad_request', self::anything());

        (new SimRecapPublishProgressController($repo, self::keys()))->handle([], [], $responder, $body);
    }

    // ── finish ─────────────────────────────────────────────────────────────────

    public function testFinishMarksClaimedRowFinished(): void
    {
        $repo = $this->createMock(SimPublishProgressRepository::class);
        $repo->expects($this->once())->method('finish')->with(7, self::TOKEN)->willReturn(true);

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->never())->method('error');
        $responder->expects($this->once())->method('success');

        (new SimRecapPublishFinishController($repo, self::keys()))->handle([], [], $responder, ['sim' => 7, 'token' => self::TOKEN]);
    }

    public function testFinishReturns409ForForeignToken(): void
    {
        $repo = $this->createMock(SimPublishProgressRepository::class);
        $repo->expects($this->once())->method('finish')->willReturn(false);

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())->method('error')->with(409, 'conflict', self::anything());
        $responder->expects($this->never())->method('success');

        (new SimRecapPublishFinishController($repo, self::keys()))->handle([], [], $responder, ['sim' => 7, 'token' => self::TOKEN]);
    }

    public function testFinishRejectsBadInput(): void
    {
        $repo = $this->createMock(SimPublishProgressRepository::class);
        $repo->expects($this->never())->method('finish');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())->method('error')->with(400, 'bad_request', self::anything());

        (new SimRecapPublishFinishController($repo, self::keys()))->handle([], [], $responder, ['sim' => 0, 'token' => self::TOKEN]);
    }

    // ── service-key gate ───────────────────────────────────────────────────────

    public function testSimRecapPublishRoutesRequireServiceKey(): void
    {
        $repo = $this->createMock(SimPublishProgressRepository::class);
        $repo->expects($this->never())->method('claimNext');
        $repo->expects($this->never())->method('advance');
        $repo->expects($this->never())->method('finish');

        $standard = self::keys('standard');
        $controllers = [
            'claim-next' => [new SimRecapPublishClaimController($repo, self::scriptBuilder(null), $standard), ['token' => self::TOKEN]],
            'progress'   => [new SimRecapPublishProgressController($repo, $standard), ['sim' => 7, 'token' => self::TOKEN, 'step' => 1, 'threadId' => null]],
            'finish'     => [new SimRecapPublishFinishController($repo, $standard), ['sim' => 7, 'token' => self::TOKEN]],
        ];

        foreach ($controllers as [$controller, $body]) {
            $responder = $this->createMock(JsonResponder::class);
            $responder->expects($this->once())->method('error')->with(403, 'forbidden', self::anything());
            $responder->expects($this->never())->method('success');
            $responder->expects($this->never())->method('raw');

            $controller->handle([], [], $responder, $body);
        }
    }
}
