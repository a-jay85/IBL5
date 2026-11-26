<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Profile;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Fixtures\ProjectionFactory;
use TradeSuggestions\Profile\PositionStrength;
use TradeSuggestions\Profile\SlotRankTable;
use TradeSuggestions\Profile\SlotStrength;

final class PositionStrengthTest extends TestCase
{
    public function testStrongAtRankEightAndNotAtNine(): void
    {
        self::assertTrue(self::strength(8)->isStrong());
        self::assertFalse(self::strength(9)->isStrong());
    }

    public function testWeakAtRankTwentyOneAndNotAtTwenty(): void
    {
        self::assertTrue(self::strength(21)->isWeak());
        self::assertFalse(self::strength(20)->isWeak());
    }

    public function testEmptySlotIsWeakWithZeroDepth(): void
    {
        $lineups = [1 => ProjectionFactory::lineupFor(1, 0, [ProjectionFactory::player(dcMinutes: 30)])];
        $table = SlotRankTable::build($lineups, ProjectionFactory::ratingsOf($lineups));

        $center = PositionStrength::build(1, $lineups[1], $table)['C'];

        self::assertNull($center->starterRank);
        self::assertTrue($center->isWeak());
        self::assertFalse($center->isStrong());
        self::assertSame(0, $center->playableDepth);
    }

    public function testPlayableDepthCountsFifteenMinutesOrRotationRole(): void
    {
        // No reliable chart, so the weight is minutes per game from the production line.
        $lineups = [1 => ProjectionFactory::lineupFor(1, 0, [
            ProjectionFactory::player(pid: 1, dcMinutes: 30),
            ProjectionFactory::player(pid: 2, dcMinutes: 20, production: ProjectionFactory::productionLine(['stats_min' => 560])),
            ProjectionFactory::player(pid: 3, dcMinutes: 5, production: ProjectionFactory::productionLine(['stats_min' => 560])),
            ProjectionFactory::player(pid: 4, dcMinutes: 5, production: ProjectionFactory::productionLine(['stats_min' => 640])),
        ])];
        $table = SlotRankTable::build($lineups, ProjectionFactory::ratingsOf($lineups));

        self::assertSame(14.0, $lineups[1]->minutesWeight[3]);
        self::assertSame(3, PositionStrength::build(1, $lineups[1], $table)['PG']->playableDepth);
    }

    public function testMultiPositionFlagRequiresTwoLegalSlots(): void
    {
        $lineups = [
            1 => ProjectionFactory::lineupFor(1, 0, [ProjectionFactory::player(pid: 1, pos: 'G', dcMinutes: 30)]),
            2 => ProjectionFactory::lineupFor(2, 0, [ProjectionFactory::player(pid: 2, tid: 2, pos: 'C', dcMinutes: 30)]),
        ];
        $table = SlotRankTable::build($lineups, ProjectionFactory::ratingsOf($lineups));
        $league = PositionStrength::buildLeague($lineups, $table);

        self::assertTrue($league[1]['PG']->multiPosition);
        self::assertFalse($league[2]['C']->multiPosition);
    }

    public function testBuriedTalentDetectedBehindBetterStarter(): void
    {
        $league = self::buriedLeague(55.0);

        self::assertSame([100], $league[3]['PG']->buriedPids);
        self::assertSame([], $league[1]['PG']->buriedPids);
    }

    public function testBuriedTalentEmptyWhenBenchWouldNotStartAnywhere(): void
    {
        $league = self::buriedLeague(30.0);

        self::assertSame([], $league[3]['PG']->buriedPids);
    }

    private static function strength(?int $starterRank): SlotStrength
    {
        return new SlotStrength('PG', $starterRank, null, 1, false, []);
    }

    /**
     * Starters rated 40, 50, 60 on tids 1-3; tid 3 also has a bench PG rated `$benchRating`.
     *
     * @return array<int, array<string, SlotStrength>>
     */
    private static function buriedLeague(float $benchRating): array
    {
        $lineups = ProjectionFactory::leagueOf(3, static function (int $tid) use ($benchRating): array {
            $players = [ProjectionFactory::player(pid: $tid, tid: $tid, dcMinutes: 30, ratings: ProjectionFactory::ratingSet([], 30.0 + 10 * $tid))];
            if ($tid === 3) {
                $players[] = ProjectionFactory::player(pid: 100, tid: 3, dcMinutes: 20, ratings: ProjectionFactory::ratingSet([], $benchRating));
            }

            return $players;
        });
        $table = SlotRankTable::build($lineups, ProjectionFactory::ratingsOf($lineups));

        return PositionStrength::buildLeague($lineups, $table);
    }
}
