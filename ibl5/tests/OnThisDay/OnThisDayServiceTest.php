<?php

declare(strict_types=1);

namespace Tests\OnThisDay;

use OnThisDay\Contracts\OnThisDayRepositoryInterface;
use OnThisDay\Contracts\ShownMomentHistoryInterface;
use OnThisDay\Moment;
use OnThisDay\MomentKind;
use OnThisDay\OnThisDayCalendar;
use OnThisDay\OnThisDayService;
use OnThisDay\WowScorer;
use PHPUnit\Framework\TestCase;
use Season\Contracts\SeasonQueryRepositoryInterface;

final class OnThisDayServiceTest extends TestCase
{
    /**
     * @param array<string, int> $facts
     */
    private function makeMoment(
        MomentKind $kind,
        string $key,
        array $facts,
        int $pid = 1,
        string $date = '2000-02-14',
    ): Moment {
        return new Moment(
            kind: $kind,
            key: $key,
            date: $date,
            seasonYear: 2000,
            pid: $pid,
            playerName: 'Test Player',
            teamId: 1,
            teamName: 'Team A',
            opponentTeamId: 2,
            opponentName: 'Team B',
            facts: $facts,
        );
    }

    private function fiftyPointGame(string $key, int $points, int $pid = 1): Moment
    {
        return $this->makeMoment(
            MomentKind::FiftyPointGame,
            $key,
            ['points' => $points, 'rebounds' => 5, 'assists' => 2, 'steals' => 0, 'blocks' => 0],
            $pid,
        );
    }

    /**
     * A null start date means a single-day sim (start equals end).
     */
    private function seasonQuery(int $sim, string $endDate, ?string $startDate = null): SeasonQueryRepositoryInterface
    {
        $seasonQuery = self::createStub(SeasonQueryRepositoryInterface::class);
        $seasonQuery->method('getLastSimDatesArray')->willReturn([
            'sim' => $sim,
            'start_date' => $startDate ?? $endDate,
            'end_date' => $endDate,
        ]);

        return $seasonQuery;
    }

    /**
     * @param array<string, int> $shown moment_key => last shown sim
     */
    private function history(array $shown): ShownMomentHistoryInterface
    {
        $history = self::createStub(ShownMomentHistoryInterface::class);
        $history->method('findShownBefore')->willReturn($shown);

        return $history;
    }

    private function service(
        OnThisDayRepositoryInterface $repository,
        SeasonQueryRepositoryInterface $seasonQuery,
        ?ShownMomentHistoryInterface $history = null,
    ): OnThisDayService {
        return new OnThisDayService(
            $repository,
            $history ?? $this->history([]),
            $seasonQuery,
            new WowScorer(),
            new OnThisDayCalendar(),
        );
    }

    /**
     * Run the service against an empty store and return every findMoments window.
     *
     * @return list<list<string>>
     */
    private function recordWindows(SeasonQueryRepositoryInterface $seasonQuery): array
    {
        /** @var list<list<string>> $windows */
        $windows = [];

        $repository = self::createStub(OnThisDayRepositoryInterface::class);
        $repository->method('findMoments')->willReturnCallback(
            static function (array $monthDays, int $beforeSeasonYear) use (&$windows): array {
                $windows[] = array_values(array_map('strval', $monthDays));

                return [];
            }
        );

        $this->service($repository, $seasonQuery)->getMomentsForSim();

        return $windows;
    }

    public function testEmptySimDatesReturnsEmptySelection(): void
    {
        $repository = $this->createMock(OnThisDayRepositoryInterface::class);
        $repository->expects($this->never())->method('findMoments');

        $seasonQuery = self::createStub(SeasonQueryRepositoryInterface::class);
        $seasonQuery->method('getLastSimDatesArray')->willReturn(['sim' => 0, 'start_date' => '', 'end_date' => '']);

        $selection = $this->service($repository, $seasonQuery)->getMomentsForSim();

        $this->assertTrue($selection->isEmpty());
        $this->assertSame(0, $selection->sim);
    }

    public function testWidensRadiusUntilAMomentExists(): void
    {
        $moment = $this->fiftyPointGame('fifty_point:2000-02-12:1', 60);

        $repository = self::createStub(OnThisDayRepositoryInterface::class);
        // Radius 0 and 1 give windows of 1 and 3 days; radius 3 gives 7 days.
        $repository->method('findMoments')->willReturnCallback(
            static fn (array $monthDays, int $beforeSeasonYear): array => count($monthDays) >= 7 ? [$moment] : []
        );

        $selection = $this->service($repository, $this->seasonQuery(1, '2005-02-14'))->getMomentsForSim();

        $this->assertSame(3, $selection->radiusUsed);
        $this->assertEquals([$moment], $selection->moments);
        $this->assertSame(2005, $selection->currentSeasonYear);
        $this->assertSame('2005-02-14', $selection->centerDate);
        $this->assertSame(1, $selection->sim);
    }

    public function testReturnsEmptyAfterRadiusCap(): void
    {
        $repository = self::createStub(OnThisDayRepositoryInterface::class);

        $selection = $this->service($repository, $this->seasonQuery(1, '2005-02-14'))->getMomentsForSim();

        $this->assertTrue($selection->isEmpty());
        $this->assertSame(7, $selection->radiusUsed);
    }

    public function testKeyShownInEarlierSimIsExcluded(): void
    {
        $a = $this->fiftyPointGame('fifty_point:2000-02-14:1', 70, 1);
        $b = $this->fiftyPointGame('fifty_point:2000-02-14:2', 55, 2);

        $repository = self::createStub(OnThisDayRepositoryInterface::class);
        $repository->method('findMoments')->willReturn([$a, $b]);

        $selection = $this->service(
            $repository,
            $this->seasonQuery(20, '2005-02-14'),
            $this->history(['fifty_point:2000-02-14:1' => 12]),
        )->getMomentsForSim(1);

        $this->assertEquals([$b], $selection->moments);
        $this->assertSame(20, $selection->sim);
    }

    public function testHistoryIsReadForTheCurrentSim(): void
    {
        $a = $this->fiftyPointGame('fifty_point:2000-02-14:1', 70, 1);

        $repository = self::createStub(OnThisDayRepositoryInterface::class);
        $repository->method('findMoments')->willReturn([$a]);

        $history = $this->createMock(ShownMomentHistoryInterface::class);
        $history->expects($this->once())->method('findShownBefore')->with(20)->willReturn([]);

        $selection = $this->service($repository, $this->seasonQuery(20, '2005-02-14'), $history)->getMomentsForSim(1);

        $this->assertEquals([$a], $selection->moments);
    }

    public function testAllShownFallsBackToOldestShown(): void
    {
        $a = $this->fiftyPointGame('fifty_point:2000-02-14:1', 70, 1);
        $b = $this->fiftyPointGame('fifty_point:2000-02-14:2', 55, 2);

        $repository = self::createStub(OnThisDayRepositoryInterface::class);
        $repository->method('findMoments')->willReturn([$a, $b]);

        $selection = $this->service(
            $repository,
            $this->seasonQuery(20, '2005-02-14'),
            $this->history(['fifty_point:2000-02-14:1' => 15, 'fifty_point:2000-02-14:2' => 9]),
        )->getMomentsForSim(1);

        $this->assertEquals([$b], $selection->moments);
        $this->assertSame(7, $selection->radiusUsed);
    }

    public function testEmptyHistoryKeepsTopPick(): void
    {
        $a = $this->fiftyPointGame('fifty_point:2000-02-14:1', 70, 1);
        $b = $this->fiftyPointGame('fifty_point:2000-02-14:2', 55, 2);

        $repository = self::createStub(OnThisDayRepositoryInterface::class);
        $repository->method('findMoments')->willReturn([$b, $a]);

        $selection = $this->service($repository, $this->seasonQuery(20, '2005-02-14'))->getMomentsForSim(1);

        $this->assertEquals([$a], $selection->moments);
    }

    public function testBaseWindowCoversWholeSimSpan(): void
    {
        $windows = $this->recordWindows($this->seasonQuery(20, '2005-02-13', '2005-02-07'));

        $this->assertSame(['02-07', '02-08', '02-09', '02-10', '02-11', '02-12', '02-13'], $windows[0]);
    }

    public function testWideningStartsFromSpanEdges(): void
    {
        $windows = $this->recordWindows($this->seasonQuery(20, '2005-02-13', '2005-02-07'));

        $this->assertSame(
            ['02-06', '02-07', '02-08', '02-09', '02-10', '02-11', '02-12', '02-13', '02-14'],
            $windows[1]
        );
    }

    public function testMissingStartDateSearchesEndDateOnly(): void
    {
        $windows = $this->recordWindows($this->seasonQuery(20, '2005-02-13', ''));

        $this->assertSame(['02-13'], $windows[0]);
    }

    public function testSameGameAcrossKindsCollapsesToHigherScore(): void
    {
        // Same (date, pid): 50 points scores 100; the 50/15/15 triple-double scores 110.
        $fifty = $this->fiftyPointGame('fifty_point:2000-02-14:1', 50);
        $tripleDouble = $this->makeMoment(
            MomentKind::TripleDouble,
            'triple_double:2000-02-14:1',
            ['points' => 50, 'rebounds' => 15, 'assists' => 15, 'steals' => 0, 'blocks' => 0],
        );

        $repository = self::createStub(OnThisDayRepositoryInterface::class);
        $repository->method('findMoments')->willReturn([$fifty, $tripleDouble]);

        $selection = $this->service($repository, $this->seasonQuery(1, '2005-02-14'))->getMomentsForSim(3);

        $this->assertEquals([$tripleDouble], $selection->moments);
    }

    public function testUsesSimEndDateNotWallClock(): void
    {
        /** @var list<list<string>> $windows */
        $windows = [];

        $repository = self::createStub(OnThisDayRepositoryInterface::class);
        $repository->method('findMoments')->willReturnCallback(
            static function (array $monthDays, int $beforeSeasonYear) use (&$windows): array {
                $windows[] = array_values(array_map('strval', $monthDays));

                return [];
            }
        );

        $this->service($repository, $this->seasonQuery(1, '2005-02-14'))->getMomentsForSim();

        $this->assertSame(['02-14'], $windows[0]);
    }

    public function testOneRepositoryQueryPerRadiusWalked(): void
    {
        /** @var list<int> $widths */
        $widths = [];

        $repository = $this->createMock(OnThisDayRepositoryInterface::class);
        $repository->expects($this->exactly(4))->method('findMoments')->willReturnCallback(
            static function (array $monthDays, int $beforeSeasonYear) use (&$widths): array {
                $widths[] = count($monthDays);

                return [];
            }
        );

        $this->service($repository, $this->seasonQuery(1, '2005-02-14'))->getMomentsForSim();

        $this->assertSame([1, 3, 7, 15], $widths);
    }

    public function testEmptyStoreYieldsEmptySelection(): void
    {
        $repository = self::createStub(OnThisDayRepositoryInterface::class);
        $repository->method('findMoments')->willReturn([]);

        $selection = $this->service($repository, $this->seasonQuery(2, '2005-02-14'))->getMomentsForSim();

        $this->assertTrue($selection->isEmpty());
        $this->assertSame([], $selection->moments);
        $this->assertSame(7, $selection->radiusUsed);
    }
}
