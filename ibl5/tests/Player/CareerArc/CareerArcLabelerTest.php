<?php

declare(strict_types=1);

namespace Tests\Player\CareerArc;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Player\CareerArc\ArcSeason;
use Player\CareerArc\CareerArcLabeler;

#[CoversClass(CareerArcLabeler::class)]
final class CareerArcLabelerTest extends TestCase
{
    /**
     * One season per team id, years counting up from 2020.
     *
     * @param list<int> $teamIds
     * @return array<int, ArcSeason>
     */
    private static function seasonsOnTeams(array $teamIds, ?float $ppg = 10.0, int $age = 25): array
    {
        $seasons = [];
        foreach ($teamIds as $index => $teamId) {
            $year = 2020 + $index;
            $seasons[$year] = new ArcSeason($year, $teamId, 'Team ' . $teamId, $ppg, 10, $age);
        }

        return $seasons;
    }

    /**
     * Four seasons over teams 1 and 2, with the PPG peak in 2021 at the given age.
     * Only the first $ppgSeasons seasons carry a PPG point.
     *
     * @return array<int, ArcSeason>
     */
    private static function peakSeasons(int $peakAge, int $ppgSeasons = 4): array
    {
        $seasons = [];
        for ($index = 0; $index < 4; ++$index) {
            $year = 2020 + $index;
            $isPeak = $year === 2021;
            $hasPpg = $index < $ppgSeasons;
            $ppg = $hasPpg ? ($isPeak ? 25.0 : 10.0) : null;
            $seasons[$year] = new ArcSeason($year, 1 + ($index % 2), 'Team', $ppg, 10, $isPeak ? $peakAge : 24);
        }

        return $seasons;
    }

    public function testJourneymanAbsentAtThreeTeams(): void
    {
        $labels = (new CareerArcLabeler())->labelsFor(self::seasonsOnTeams([1, 2, 3]), null);

        self::assertSame([], $labels);
    }

    public function testJourneymanPresentAtFourTeams(): void
    {
        $labels = (new CareerArcLabeler())->labelsFor(self::seasonsOnTeams([1, 2, 3, 4]), null);

        self::assertSame(['Journeyman (4 teams)'], $labels);
    }

    public function testJourneymanIgnoresTeamIdZero(): void
    {
        $labels = (new CareerArcLabeler())->labelsFor(self::seasonsOnTeams([1, 2, 3, 0]), null);

        self::assertSame([], $labels);
    }

    public function testOneTeamAbsentAtFourSeasons(): void
    {
        $labels = (new CareerArcLabeler())->labelsFor(self::seasonsOnTeams([1, 1, 1, 1]), null);

        self::assertSame([], $labels);
    }

    public function testOneTeamPresentAtFiveSeasons(): void
    {
        $labels = (new CareerArcLabeler())->labelsFor(self::seasonsOnTeams([1, 1, 1, 1, 1]), null);

        self::assertSame(['One-team career (5 seasons)'], $labels);
    }

    public function testOneTeamAbsentWhenTwoTeams(): void
    {
        $labels = (new CareerArcLabeler())->labelsFor(self::seasonsOnTeams([1, 1, 1, 2, 2]), null);

        self::assertSame([], $labels);
    }

    public function testLateBloomerAbsentAtPeakAge28(): void
    {
        $labels = (new CareerArcLabeler())->labelsFor(self::peakSeasons(28), 2021);

        self::assertSame([], $labels);
    }

    public function testLateBloomerPresentAtPeakAge29(): void
    {
        $labels = (new CareerArcLabeler())->labelsFor(self::peakSeasons(29), 2021);

        self::assertSame(['Late bloomer'], $labels);
    }

    public function testLateBloomerAbsentWithThreePpgSeasons(): void
    {
        $labels = (new CareerArcLabeler())->labelsFor(self::peakSeasons(31, 3), 2021);

        self::assertSame([], $labels);
    }

    public function testLateBloomerAbsentWhenAgeUnknown(): void
    {
        $labels = (new CareerArcLabeler())->labelsFor(self::peakSeasons(0), 2021);

        self::assertSame([], $labels);
    }

    public function testLateBloomerAbsentWithoutBestPpgYear(): void
    {
        $labels = (new CareerArcLabeler())->labelsFor(self::peakSeasons(31), null);

        self::assertSame([], $labels);
    }

    public function testSingleSeasonRookieHasNoLabels(): void
    {
        $labels = (new CareerArcLabeler())->labelsFor(self::seasonsOnTeams([1]), 2020);

        self::assertSame([], $labels);
    }

    public function testLabelOrderIsJourneymanThenLateBloomer(): void
    {
        $seasons = [];
        foreach ([1, 2, 3, 4] as $index => $teamId) {
            $year = 2020 + $index;
            $seasons[$year] = new ArcSeason($year, $teamId, 'Team ' . $teamId, $year === 2022 ? 22.0 : 9.0, 10, $year === 2022 ? 30 : 27);
        }

        $labels = (new CareerArcLabeler())->labelsFor($seasons, 2022);

        self::assertSame(['Journeyman (4 teams)', 'Late bloomer'], $labels);
    }
}
