<?php

declare(strict_types=1);

namespace Tests\OnThisDay;

use OnThisDay\Contracts\MomentPosterInterface;
use OnThisDay\Contracts\OnThisDayServiceInterface;
use OnThisDay\Contracts\SimPostClaimInterface;
use OnThisDay\Moment;
use OnThisDay\MomentFormatter;
use OnThisDay\MomentKind;
use OnThisDay\OnThisDaySelection;
use PHPUnit\Framework\TestCase;
use Season\SeasonQueryRepository;
use Updater\Steps\PostOnThisDayStep;

final class PostOnThisDayStepTest extends TestCase
{
    private function makeMoment(): Moment
    {
        return new Moment(
            kind: MomentKind::FiftyPointGame,
            key: 'fifty_point_game:2019-02-14:1',
            date: '2019-02-14',
            seasonYear: 2019,
            pid: 1,
            playerName: 'Test Player',
            teamId: 1,
            teamName: 'Hawks',
            opponentTeamId: 2,
            opponentName: 'Celtics',
            facts: ['points' => 52],
        );
    }

    private function makeSelection(bool $empty = false): OnThisDaySelection
    {
        return new OnThisDaySelection(
            moments: $empty ? [] : [$this->makeMoment()],
            currentSeasonYear: 2021,
            centerDate: '2021-02-14',
            radiusUsed: 0,
            sim: 12,
        );
    }

    private function makeSeasonQuery(string $phase, int $sim): SeasonQueryRepository
    {
        $seasonQuery = self::createStub(SeasonQueryRepository::class);
        $seasonQuery->method('getSeasonPhase')->willReturn($phase);
        $seasonQuery->method('getLastSimDatesArray')->willReturn([
            'sim' => $sim,
            'start_date' => '2021-02-10',
            'end_date' => '2021-02-14',
        ]);

        return $seasonQuery;
    }

    private function makeService(OnThisDaySelection $selection): OnThisDayServiceInterface
    {
        $service = self::createStub(OnThisDayServiceInterface::class);
        $service->method('getMomentsForSim')->willReturn($selection);

        return $service;
    }

    private function makeClaims(bool $result): RecordingClaims
    {
        return new RecordingClaims($result);
    }

    public function testSkipsOutsideSimPhases(): void
    {
        $claims = $this->makeClaims(true);
        $poster = new RecordingPoster();
        $step = new PostOnThisDayStep(
            $this->makeService($this->makeSelection()),
            $claims,
            $this->makeSeasonQuery('Draft', 12),
            $poster,
        );

        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertTrue($result->skipped);
        self::assertStringContainsString('Draft', $result->detail);
        self::assertSame([], $claims->claimed);
        self::assertSame([], $poster->messages);
    }

    public function testPostsDuringPlayoffs(): void
    {
        $poster = new RecordingPoster();
        $step = new PostOnThisDayStep(
            $this->makeService($this->makeSelection()),
            $this->makeClaims(true),
            $this->makeSeasonQuery('Playoffs', 12),
            $poster,
        );

        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertFalse($result->skipped);
        self::assertCount(1, $poster->messages);
    }

    public function testSkipsWhenNoSimRecorded(): void
    {
        $claims = $this->makeClaims(true);
        $poster = new RecordingPoster();
        $step = new PostOnThisDayStep(
            $this->makeService($this->makeSelection()),
            $claims,
            $this->makeSeasonQuery('Regular Season', 0),
            $poster,
        );

        $result = $step->execute();

        self::assertTrue($result->skipped);
        self::assertSame([], $claims->claimed);
        self::assertSame([], $poster->messages);
    }

    public function testSkipsWhenSelectionEmptyWithoutClaiming(): void
    {
        $claims = $this->makeClaims(true);
        $poster = new RecordingPoster();
        $step = new PostOnThisDayStep(
            $this->makeService($this->makeSelection(empty: true)),
            $claims,
            $this->makeSeasonQuery('Regular Season', 12),
            $poster,
        );

        $result = $step->execute();

        self::assertTrue($result->skipped);
        self::assertStringContainsString('No notable moment within 7 days of 2021-02-14.', $result->detail);
        self::assertSame([], $claims->claimed);
        self::assertSame([], $poster->messages);
    }

    public function testPostsTopMomentOncePerSim(): void
    {
        $claims = $this->makeClaims(true);
        $poster = new RecordingPoster();
        $step = new PostOnThisDayStep(
            $this->makeService($this->makeSelection()),
            $claims,
            $this->makeSeasonQuery('Regular Season', 12),
            $poster,
        );

        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertSame([12], $claims->claimed);
        self::assertCount(1, $poster->messages);
        self::assertStringContainsString(
            (new MomentFormatter())->describe($this->makeMoment()),
            $poster->messages[0],
        );
        self::assertStringContainsString('2 seasons ago', $poster->messages[0]);
        self::assertStringContainsString('Posted sim 12', $result->detail);
    }

    public function testRerunSameSimDoesNotPostAgain(): void
    {
        $poster = new RecordingPoster();
        $step = new PostOnThisDayStep(
            $this->makeService($this->makeSelection()),
            $this->makeClaims(false),
            $this->makeSeasonQuery('Regular Season', 12),
            $poster,
        );

        $result = $step->execute();

        self::assertTrue($result->skipped);
        self::assertStringContainsString('Already posted for sim 12.', $result->detail);
        self::assertSame([], $poster->messages);
    }

    public function testDiscordFailureLeavesStepSuccessful(): void
    {
        $poster = new RecordingPoster(new \RuntimeException('webhook 500'));
        $step = new PostOnThisDayStep(
            $this->makeService($this->makeSelection()),
            $this->makeClaims(true),
            $this->makeSeasonQuery('Regular Season', 12),
            $poster,
        );

        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertStringContainsString('sim unaffected', $result->detail);
        self::assertStringContainsString('webhook 500', $result->detail);
    }

    public function testServiceFailureSkipsWithoutFailingPipeline(): void
    {
        $service = self::createStub(OnThisDayServiceInterface::class);
        $service->method('getMomentsForSim')->willThrowException(new \RuntimeException('db gone'));
        $poster = new RecordingPoster();
        $step = new PostOnThisDayStep(
            $service,
            $this->makeClaims(true),
            $this->makeSeasonQuery('Regular Season', 12),
            $poster,
        );

        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertTrue($result->skipped);
        self::assertStringContainsString('db gone', $result->detail);
        self::assertSame([], $poster->messages);
    }

    public function testFormattingFailureSkipsWithoutClaimingSim(): void
    {
        $badDate = new Moment(
            kind: MomentKind::FiftyPointGame,
            key: 'fifty_point_game:bad:1',
            date: 'not-a-date',
            seasonYear: 2019,
            pid: 1,
            playerName: 'Test Player',
            teamId: 1,
            teamName: 'Hawks',
            opponentTeamId: 2,
            opponentName: 'Celtics',
            facts: ['points' => 52],
        );
        $selection = new OnThisDaySelection(
            moments: [$badDate],
            currentSeasonYear: 2021,
            centerDate: '2021-02-14',
            radiusUsed: 0,
            sim: 12,
        );
        $claims = $this->makeClaims(true);
        $poster = new RecordingPoster();
        $step = new PostOnThisDayStep(
            $this->makeService($selection),
            $claims,
            $this->makeSeasonQuery('Regular Season', 12),
            $poster,
        );

        $result = $step->execute();

        self::assertTrue($result->skipped);
        self::assertSame([], $claims->claimed);
        self::assertSame([], $poster->messages);
    }

    public function testSeasonLookupFailureSkipsWithoutFailingPipeline(): void
    {
        $seasonQuery = self::createStub(SeasonQueryRepository::class);
        $seasonQuery->method('getSeasonPhase')->willThrowException(new \RuntimeException('settings gone'));
        $claims = $this->makeClaims(true);
        $poster = new RecordingPoster();
        $step = new PostOnThisDayStep(
            $this->makeService($this->makeSelection()),
            $claims,
            $seasonQuery,
            $poster,
        );

        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertTrue($result->skipped);
        self::assertStringContainsString('settings gone', $result->detail);
        self::assertSame([], $claims->claimed);
        self::assertSame([], $poster->messages);
    }
}

final class RecordingPoster implements MomentPosterInterface
{
    /** @var list<string> */
    public array $messages = [];

    public function __construct(private readonly ?\Throwable $failure = null)
    {
    }

    public function post(string $message): void
    {
        $this->messages[] = $message;
        if ($this->failure !== null) {
            throw $this->failure;
        }
    }
}

final class RecordingClaims implements SimPostClaimInterface
{
    /** @var list<int> */
    public array $claimed = [];

    public function __construct(private readonly bool $result)
    {
    }

    public function claimSimPost(int $sim): bool
    {
        $this->claimed[] = $sim;

        return $this->result;
    }
}
