<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Profile;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Fixtures\ProjectionFactory;
use TradeSuggestions\Profile\LeagueProfiles;
use TradeSuggestions\Profile\ProfileDimension;
use TradeSuggestions\Profile\TeamProfile;
use TradeSuggestions\SlotMapper;

final class TeamProfileTest extends TestCase
{
    public function testValuesAreMinutesWeightedMeans(): void
    {
        // No reliable chart, so weights are minutes per game: 1200/40 = 30, 400/40 = 10.
        $lineup = ProjectionFactory::lineupFor(1, 0, [
            ProjectionFactory::player(pid: 1, ratings: ProjectionFactory::ratingSet(['r_fgp' => 80]), production: ProjectionFactory::productionLine(['stats_min' => 1200])),
            ProjectionFactory::player(pid: 2, ratings: ProjectionFactory::ratingSet(['r_fgp' => 40]), production: ProjectionFactory::productionLine(['stats_min' => 400])),
        ]);

        $values = TeamProfile::valuesFor($lineup, ProjectionFactory::ratingsOf([$lineup]), [
            1 => ['pts' => 30.0],
            2 => ['pts' => 10.0],
        ]);

        self::assertEqualsWithDelta(70.0, $values['r_fgp'], 1e-9);
        self::assertEqualsWithDelta(25.0, $values['pts'], 1e-9);
    }

    public function testPlayerWithoutProductionSkipsStatDimensionsOnly(): void
    {
        $players = [];
        foreach (SlotMapper::SLOTS as $i => $slot) {
            $players[] = ProjectionFactory::player(
                pid: $i + 1,
                pos: $slot,
                ratings: ProjectionFactory::ratingSet(['r_fgp' => $slot === 'C' ? 20 : 50]),
                noProduction: $slot === 'C',
                dcDepth: [$slot => 1],
                dcMinutes: 30,
            );
        }
        $lineup = ProjectionFactory::lineupFor(1, 0, $players);
        $per36 = [1 => ['pts' => 20.0], 2 => ['pts' => 20.0], 3 => ['pts' => 20.0], 4 => ['pts' => 20.0], 5 => []];

        $values = TeamProfile::valuesFor($lineup, ProjectionFactory::ratingsOf([$lineup]), $per36);

        self::assertTrue($lineup->chartReliable);
        self::assertEqualsWithDelta(44.0, $values['r_fgp'], 1e-9);
        self::assertEqualsWithDelta(20.0, $values['pts'], 1e-9);
    }

    public function testZeroWeightRosterYieldsZeroValues(): void
    {
        $lineup = ProjectionFactory::lineupFor(1, 0, [ProjectionFactory::player(noProduction: true)]);
        $empty = ProjectionFactory::lineupFor(2, 0, []);

        foreach ([$lineup, $empty] as $case) {
            $values = TeamProfile::valuesFor($case, ProjectionFactory::ratingsOf([$case]), []);
            self::assertSame([0.0], array_values(array_unique($values)));
        }
    }

    public function testPercentagesZeroWhenDenominatorZero(): void
    {
        $line = ProjectionFactory::productionLine([
            'stats_fgm' => 0, 'stats_fga' => 0, 'stats_3gm' => 0, 'stats_3ga' => 0, 'stats_ftm' => 0, 'stats_fta' => 0,
        ]);
        $lineup = ProjectionFactory::lineupFor(1, 0, [ProjectionFactory::player(production: $line)]);

        $values = TeamProfile::valuesFor($lineup, ProjectionFactory::ratingsOf([$lineup]), [1 => ['pts' => 0.0]]);

        foreach (ProfileDimension::RATES as $rate) {
            self::assertSame(0.0, $values[$rate], $rate);
        }

        $shooter = ProjectionFactory::lineupFor(2, 0, [ProjectionFactory::player(pid: 2)]);
        $shooterValues = TeamProfile::valuesFor($shooter, ProjectionFactory::ratingsOf([$shooter]), [2 => ['pts' => 1.0]]);
        self::assertEqualsWithDelta(200 / 420, $shooterValues['fg_pct'], 1e-9);
        self::assertEqualsWithDelta(110 / 420, $shooterValues['tpa_rate'], 1e-9);
    }

    public function testLowerIsBetterDimensionRanksAscending(): void
    {
        $profiles = LeagueProfiles::build([
            1 => self::values(['tov' => 3.0, 'pts' => 3.0]),
            2 => self::values(['tov' => 1.0, 'pts' => 1.0]),
            3 => self::values(['tov' => 2.0, 'pts' => 2.0]),
        ]);

        self::assertSame(1, $profiles[2]->ranks['tov']);
        self::assertSame(3, $profiles[1]->ranks['tov']);
        self::assertSame(1, $profiles[1]->ranks['pts']);
    }

    public function testRanksAreOrdinalOneToTwentyEightPerDimension(): void
    {
        $valuesByTid = [];
        for ($tid = 1; $tid <= 28; $tid++) {
            $valuesByTid[$tid] = self::values();
        }

        $profiles = LeagueProfiles::build($valuesByTid);

        foreach (['pts', 'r_fga', 'tov', ProfileDimension::OFFENSE_MEAN] as $key) {
            $ranks = array_map(static fn (TeamProfile $p): int => $p->ranks[$key], $profiles);
            self::assertSame(range(1, 28), array_values($ranks), $key);
        }
    }

    public function testSurplusAndDeficitThresholds(): void
    {
        $valuesByTid = [];
        for ($tid = 1; $tid <= 28; $tid++) {
            $valuesByTid[$tid] = self::values(['pts' => 100.0 - $tid]);
        }

        $profiles = LeagueProfiles::build($valuesByTid);

        self::assertTrue($profiles[9]->isSurplus('pts'));
        self::assertFalse($profiles[10]->isSurplus('pts'));
        self::assertTrue($profiles[20]->isDeficit('pts'));
        self::assertFalse($profiles[19]->isDeficit('pts'));
    }

    public function testBalanceIsOffenseRankMinusDefenseRank(): void
    {
        $profiles = LeagueProfiles::build([
            1 => self::values(self::odpt(90.0, 10.0)),
            2 => self::values(self::odpt(50.0, 50.0)),
            3 => self::values(self::odpt(10.0, 90.0)),
        ]);

        self::assertSame(-2, $profiles[1]->balance());
        self::assertSame(0, $profiles[2]->balance());
        self::assertSame(2, $profiles[3]->balance());
    }

    public function testReRankMovesOnlyTheTwoChangedTeams(): void
    {
        $valuesByTid = [];
        for ($tid = 1; $tid <= 10; $tid++) {
            $valuesByTid[$tid] = self::values(['pts' => 10.0 * $tid]);
        }
        $profiles = LeagueProfiles::build($valuesByTid);

        $after = LeagueProfiles::reRank($profiles, 3, self::values(['pts' => 1000.0]), 7, self::values(['pts' => 0.0]));

        self::assertSame(1, $after[3]->ranks['pts']);
        self::assertSame(10, $after[7]->ranks['pts']);
        $others = array_filter($after, static fn (TeamProfile $p): bool => $p->tid !== 3 && $p->tid !== 7);
        uasort($others, static fn (TeamProfile $a, TeamProfile $b): int => $a->ranks['pts'] <=> $b->ranks['pts']);
        self::assertSame([10, 9, 8, 6, 5, 4, 2, 1], array_keys($others));
        self::assertSame(1000.0, $after[3]->values['pts']);
    }

    public function testDimensionKeysCountThirtyOne(): void
    {
        $lineup = ProjectionFactory::lineupFor(1, 0, [ProjectionFactory::player()]);

        $values = TeamProfile::valuesFor($lineup, ProjectionFactory::ratingsOf([$lineup]), [1 => ['pts' => 1.0]]);

        self::assertCount(31, $values);
        self::assertArrayHasKey('tpa_rate', $values);
        self::assertArrayHasKey('r_foul', $values);
        self::assertArrayHasKey('tov', $values);
    }

    /**
     * @param array<string, float> $overrides
     * @return array<string, float>
     */
    private static function values(array $overrides = []): array
    {
        $values = [];
        foreach (ProfileDimension::KEYS as $key) {
            $values[$key] = 50.0;
        }

        return array_merge($values, $overrides);
    }

    /**
     * @return array<string, float>
     */
    private static function odpt(float $offense, float $defense): array
    {
        $out = [];
        foreach (ProfileDimension::OFFENSE as $key) {
            $out[$key] = $offense;
        }
        foreach (ProfileDimension::DEFENSE as $key) {
            $out[$key] = $defense;
        }

        return $out;
    }
}
