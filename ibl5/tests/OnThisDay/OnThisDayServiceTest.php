<?php

declare(strict_types=1);

namespace Tests\OnThisDay;

use OnThisDay\Contracts\OnThisDayRepositoryInterface;
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

    private function seasonQuery(int $sim, string $endDate): SeasonQueryRepositoryInterface
    {
        $seasonQuery = self::createStub(SeasonQueryRepositoryInterface::class);
        $seasonQuery->method('getLastSimDatesArray')->willReturn([
            'sim' => $sim,
            'start_date' => $endDate,
            'end_date' => $endDate,
        ]);

        return $seasonQuery;
    }

    private function service(
        OnThisDayRepositoryInterface $repository,
        SeasonQueryRepositoryInterface $seasonQuery,
    ): OnThisDayService {
        return new OnThisDayService($repository, $seasonQuery, new WowScorer(), new OnThisDayCalendar());
    }

    public function testEmptySimDatesReturnsEmptySelection(): void
    {
        $repository = $this->createMock(OnThisDayRepositoryInterface::class);
        $repository->expects($this->never())->method('findFiftyPointGames');

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
        $repository->method('findFiftyPointGames')->willReturnCallback(
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

    public function testExcludesPreviousSimSelection(): void
    {
        $top = $this->fiftyPointGame('fifty_point:2000-02-14:1', 70, 1);
        $second = $this->fiftyPointGame('fifty_point:2000-02-14:2', 55, 2);

        $repository = self::createStub(OnThisDayRepositoryInterface::class);
        $repository->method('findFiftyPointGames')->willReturn([$second, $top]);
        $repository->method('findSimEndDate')->willReturn('2005-02-13');

        $selection = $this->service($repository, $this->seasonQuery(2, '2005-02-14'))->getMomentsForSim(1);

        $this->assertEquals([$second], $selection->moments);
        $this->assertSame(2, $selection->sim);
    }

    public function testFallsBackWhenExclusionEmptiesResult(): void
    {
        $only = $this->fiftyPointGame('fifty_point:2000-02-14:1', 70);

        $repository = self::createStub(OnThisDayRepositoryInterface::class);
        $repository->method('findFiftyPointGames')->willReturn([$only]);
        $repository->method('findSimEndDate')->willReturn('2005-02-13');

        $selection = $this->service($repository, $this->seasonQuery(2, '2005-02-14'))->getMomentsForSim(3);

        $this->assertEquals([$only], $selection->moments);
        $this->assertSame(0, $selection->radiusUsed);
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
        $repository->method('findFiftyPointGames')->willReturn([$fifty]);
        $repository->method('findTripleDoubles')->willReturn([$tripleDouble]);

        $selection = $this->service($repository, $this->seasonQuery(1, '2005-02-14'))->getMomentsForSim(3);

        $this->assertEquals([$tripleDouble], $selection->moments);
    }

    public function testUsesSimEndDateNotWallClock(): void
    {
        /** @var list<list<string>> $windows */
        $windows = [];

        $repository = self::createStub(OnThisDayRepositoryInterface::class);
        $repository->method('findFiftyPointGames')->willReturnCallback(
            static function (array $monthDays, int $beforeSeasonYear) use (&$windows): array {
                $windows[] = array_values(array_map('strval', $monthDays));

                return [];
            }
        );

        $this->service($repository, $this->seasonQuery(1, '2005-02-14'))->getMomentsForSim();

        $this->assertSame(['02-14'], $windows[0]);
    }
}
