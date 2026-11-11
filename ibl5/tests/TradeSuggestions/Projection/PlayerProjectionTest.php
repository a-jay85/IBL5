<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Projection;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Fixtures\ProjectionFactory;
use TradeSuggestions\Projection\PlayerProjection;
use TradeSuggestions\Projection\StatRatingMap;

final class PlayerProjectionTest extends TestCase
{
    public function testReturnsExactlyFourSeasonsKeyedZeroToThree(): void
    {
        $seasons = PlayerProjection::project(ProjectionFactory::player(), ProjectionFactory::scale());

        self::assertSame([0, 1, 2, 3], array_keys($seasons));
        foreach ($seasons as $offset => $season) {
            self::assertSame($offset, $season->offset);
            self::assertSame(26 + $offset, $season->age);
        }
    }

    public function testOffsetZeroRatingsEqualClampedBase(): void
    {
        $ratings = ProjectionFactory::ratingSet(['r_fga' => 120.0, 'r_stl' => 40.0]);
        $seasons = PlayerProjection::project(
            ProjectionFactory::player(age: 22, ratings: $ratings),
            ProjectionFactory::scale(99.0)
        );

        self::assertSame(99.0, $seasons[0]->ratings->get('r_fga'));
        self::assertSame(40.0, $seasons[0]->ratings->get('r_stl'));
        self::assertSame(50.0, $seasons[0]->ratings->get('r_blk'));
    }

    public function testSalaryFollowsEffectiveCyAndIsZeroPastContractEnd(): void
    {
        $player = ProjectionFactory::player(
            effectiveCy: 2,
            salaryByYear: [1 => 100, 2 => 200, 3 => 300, 4 => 400, 5 => 0, 6 => 0]
        );
        $seasons = PlayerProjection::project($player, ProjectionFactory::scale());

        self::assertSame(200, $seasons[0]->salary);
        self::assertSame(300, $seasons[1]->salary);
        self::assertSame(400, $seasons[2]->salary);
        self::assertSame(0, $seasons[3]->salary);
        self::assertTrue($seasons[2]->onRoster);
        self::assertFalse($seasons[3]->onRoster);
    }

    public function testSalaryBeyondYearSixIsZero(): void
    {
        $player = ProjectionFactory::player(
            effectiveCy: 5,
            salaryByYear: [1 => 100, 2 => 100, 3 => 100, 4 => 100, 5 => 500, 6 => 600]
        );
        $seasons = PlayerProjection::project($player, ProjectionFactory::scale());

        self::assertSame(500, $seasons[0]->salary);
        self::assertSame(600, $seasons[1]->salary);
        self::assertSame(0, $seasons[2]->salary);
        self::assertFalse($seasons[2]->onRoster);
    }

    public function testTwentyTwoYearOldAthleticRatingGrowsThroughOffsetThree(): void
    {
        $seasons = PlayerProjection::project(ProjectionFactory::player(age: 22), ProjectionFactory::scale());

        self::assertSame(
            [50.0, 52.0, 54.0, 56.0],
            array_map(static fn ($s): float => $s->ratings->get('r_stl'), array_values($seasons))
        );

        $seasons23 = PlayerProjection::project(ProjectionFactory::player(age: 23), ProjectionFactory::scale());
        self::assertSame(54.0, $seasons23[3]->ratings->get('r_stl'));
    }

    public function testThirtyOneYearOldSkillRatingDeclines(): void
    {
        $ratings = ProjectionFactory::ratingSet(['r_fgp' => 60.0]);
        $seasons = PlayerProjection::project(
            ProjectionFactory::player(age: 31, ratings: $ratings),
            ProjectionFactory::scale()
        );

        self::assertSame(
            [60.0, 59.5, 58.5, 57.0],
            array_map(static fn ($s): float => $s->ratings->get('r_fgp'), array_values($seasons))
        );
    }

    public function testProjectedRatingIsClampedToScaleMax(): void
    {
        $ratings = ProjectionFactory::ratingSet(['r_stl' => 94.0]);
        $seasons = PlayerProjection::project(
            ProjectionFactory::player(age: 22, ratings: $ratings),
            ProjectionFactory::scale(95.0)
        );

        self::assertSame(95.0, $seasons[2]->ratings->get('r_stl'));
    }

    public function testProjectedRatingNeverBelowZero(): void
    {
        $ratings = ProjectionFactory::ratingSet(['r_stl' => 1.0]);
        $seasons = PlayerProjection::project(
            ProjectionFactory::player(age: 38, ratings: $ratings),
            ProjectionFactory::scale()
        );

        self::assertSame(0.0, $seasons[1]->ratings->get('r_stl'));
        self::assertSame(0.0, $seasons[3]->ratings->get('r_stl'));
    }

    public function testPer36PointsScaleWithMappedRatingDelta(): void
    {
        $seasons = PlayerProjection::project(ProjectionFactory::player(age: 22), ProjectionFactory::scale());

        // Base line: 520 points in 1000 minutes = 18.72 per 36.
        self::assertEqualsWithDelta(18.72, $seasons[0]->per36['pts'], 1e-9);
        self::assertGreaterThan($seasons[0]->per36['pts'], $seasons[1]->per36['pts']);
        // 7 pts ratings: 1 athletic (+2.0), 6 skill (+1.5) => mean 1.5714 => factor 1.015714.
        self::assertEqualsWithDelta(18.72 * (1 + 0.01 * (2.0 + 6 * 1.5) / 7), $seasons[1]->per36['pts'], 1e-9);
        self::assertEqualsWithDelta(50 * 36 / 1000, $seasons[0]->per36['pf'], 1e-9);
        self::assertSame(['pts', 'reb', 'ast', 'stl', 'blk', 'tov', 'pf'], array_keys($seasons[0]->per36));
    }

    public function testPer36EmptyWhenNoProductionSample(): void
    {
        $seasons = PlayerProjection::project(ProjectionFactory::player(noProduction: true), ProjectionFactory::scale());

        foreach ($seasons as $season) {
            self::assertSame([], $season->per36);
        }
    }

    public function testUnmappedStatFactorIsOne(): void
    {
        $base = ProjectionFactory::ratingSet();
        $projected = ProjectionFactory::ratingSet([], 70.0);

        self::assertSame(1.0, StatRatingMap::factor('fg_pct', $base, $projected));
        self::assertEqualsWithDelta(1.2, StatRatingMap::factor('ast', $base, $projected), 1e-9);
    }

    public function testStatFactorFlooredAtZero(): void
    {
        $base = ProjectionFactory::ratingSet(['r_ast' => 300.0]);
        $projected = ProjectionFactory::ratingSet(['r_ast' => 0.0]);

        self::assertSame(0.0, StatRatingMap::factor('ast', $base, $projected));
    }
}
