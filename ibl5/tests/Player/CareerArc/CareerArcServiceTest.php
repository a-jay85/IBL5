<?php

declare(strict_types=1);

namespace Tests\Player\CareerArc;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Player\CareerArc\ArcEvent;
use Player\CareerArc\CareerArc;
use Player\CareerArc\CareerArcLabeler;
use Player\CareerArc\CareerArcService;
use Player\CareerArc\Contracts\CareerArcRepositoryInterface;

#[CoversClass(CareerArcService::class)]
final class CareerArcServiceTest extends TestCase
{
    /**
     * @return array{year:int, teamid:int, team:string, games:int, pts:int, age:int,
     *   r_oo:int, r_drive_off:int, r_po:int, r_trans_off:int, r_od:int, r_dd:int, r_pd:int, r_td:int}
     */
    private static function row(int $year, int $teamid = 1, int $games = 10, int $pts = 100, int $age = 25, int $rating = 1): array
    {
        return [
            'year' => $year,
            'teamid' => $teamid,
            'team' => 'Team ' . $teamid,
            'games' => $games,
            'pts' => $pts,
            'age' => $age,
            'r_oo' => $rating,
            'r_drive_off' => $rating,
            'r_po' => $rating,
            'r_trans_off' => $rating,
            'r_od' => $rating,
            'r_dd' => $rating,
            'r_pd' => $rating,
            'r_td' => $rating,
        ];
    }

    /**
     * @return array{season_year:int, transaction_month:int, transaction_day:int,
     *   transaction_type:int, from_teamid:int, to_teamid:int,
     *   from_team_name:?string, to_team_name:?string}
     */
    private static function move(int $seasonYear, int $type, ?string $fromName = 'Team 1', ?string $toName = 'Team 2'): array
    {
        return [
            'season_year' => $seasonYear,
            'transaction_month' => 3,
            'transaction_day' => 14,
            'transaction_type' => $type,
            'from_teamid' => 1,
            'to_teamid' => 2,
            'from_team_name' => $fromName,
            'to_team_name' => $toName,
        ];
    }

    /**
     * @return array{name:string, draftyear:int, draftround:int, draftpickno:int, draftedby:string}
     */
    private static function facts(int $draftYear = 0, int $draftRound = 0, int $pickNo = 0, string $draftedBy = ''): array
    {
        return [
            'name' => 'Test Player',
            'draftyear' => $draftYear,
            'draftround' => $draftRound,
            'draftpickno' => $pickNo,
            'draftedby' => $draftedBy,
        ];
    }

    /**
     * @param list<array{year:int, teamid:int, team:string, games:int, pts:int, age:int,
     *   r_oo:int, r_drive_off:int, r_po:int, r_trans_off:int, r_od:int, r_dd:int, r_pd:int, r_td:int}> $rows
     * @param list<array{season_year:int, transaction_month:int, transaction_day:int,
     *   transaction_type:int, from_teamid:int, to_teamid:int,
     *   from_team_name:?string, to_team_name:?string}> $moves
     * @param array{name:string, draftyear:int, draftround:int, draftpickno:int, draftedby:string}|null $facts
     * @param list<array{year:int, award:string}>|null $awards
     */
    private static function service(array $rows, array $moves = [], ?array $facts = null, ?array $awards = []): CareerArcService
    {
        $repository = self::createStub(CareerArcRepositoryInterface::class);
        $repository->method('getSeasonRows')->willReturn($rows);
        $repository->method('getMoves')->willReturn($moves);
        $repository->method('getPlayerFacts')->willReturn($facts);
        $repository->method('getAwardsForUniqueName')->willReturn($awards);

        return new CareerArcService($repository, new CareerArcLabeler());
    }

    /**
     * @param list<array{year:int, teamid:int, team:string, games:int, pts:int, age:int,
     *   r_oo:int, r_drive_off:int, r_po:int, r_trans_off:int, r_od:int, r_dd:int, r_pd:int, r_td:int}> $rows
     */
    private static function buildArc(array $rows, bool $isRetired = false): CareerArc
    {
        $arc = self::service($rows)->build(1, $isRetired);
        self::assertNotNull($arc);

        return $arc;
    }

    /**
     * @return list<ArcEvent>
     */
    private static function eventsOfType(CareerArc $arc, string $type): array
    {
        return array_values(array_filter(
            $arc->events,
            static fn (ArcEvent $event): bool => $event->type === $type,
        ));
    }

    public function testRatingIsOffPlusDefSum(): void
    {
        $rating = CareerArcService::ratingFor([
            'r_oo' => 1,
            'r_drive_off' => 2,
            'r_po' => 3,
            'r_trans_off' => 4,
            'r_od' => 5,
            'r_dd' => 6,
            'r_pd' => 7,
            'r_td' => 8,
        ]);

        self::assertSame(36, $rating);
    }

    public function testAllZeroRatingsYieldRatingGap(): void
    {
        $arc = self::buildArc([self::row(2024, 1, 10, 100, 25, 0)]);

        self::assertNull($arc->seasonsByYear[2024]->rating);
        self::assertSame(10.0, $arc->seasonsByYear[2024]->ppg);
    }

    public function testZeroGamesYieldsPpgGap(): void
    {
        $arc = self::buildArc([self::row(2024, 1, 0, 0)]);

        self::assertNull($arc->seasonsByYear[2024]->ppg);
        self::assertNull(CareerArcService::ppgFor(50, 0));
    }

    public function testNoSeasonsReturnsNull(): void
    {
        self::assertNull(self::service([])->build(1, false));
    }

    public function testRookieWithOneSeason(): void
    {
        $arc = self::buildArc([self::row(2024)]);

        self::assertCount(1, $arc->seasonsByYear);
        self::assertCount(1, $arc->stints);
        self::assertSame(2024, $arc->axisStart);
        self::assertSame($arc->axisStart, $arc->axisEnd);
    }

    public function testMultiTeamCareerBuildsStintsInOrder(): void
    {
        $arc = self::buildArc([
            self::row(2021, 1),
            self::row(2022, 1),
            self::row(2023, 2),
            self::row(2024, 1),
        ]);

        self::assertCount(3, $arc->stints);
        self::assertSame(
            [1, 2, 1],
            [$arc->stints[0]->teamid, $arc->stints[1]->teamid, $arc->stints[2]->teamid],
        );
        self::assertSame([2021, 2022], [$arc->stints[0]->fromYear, $arc->stints[0]->toYear]);
        self::assertSame([2023, 2023], [$arc->stints[1]->fromYear, $arc->stints[1]->toYear]);
        self::assertSame([2024, 2024], [$arc->stints[2]->fromYear, $arc->stints[2]->toYear]);
    }

    public function testGapYearOnSameTeamKeepsOneStint(): void
    {
        $arc = self::buildArc([self::row(2022, 1), self::row(2024, 1)]);

        self::assertCount(1, $arc->stints);
        self::assertSame(2022, $arc->stints[0]->fromYear);
        self::assertSame(2024, $arc->stints[0]->toYear);
    }

    public function testMissingSeasonLeavesGapInBothSeries(): void
    {
        $arc = self::buildArc([self::row(2022), self::row(2024)]);

        self::assertSame(2022, $arc->axisStart);
        self::assertSame(2024, $arc->axisEnd);
        self::assertArrayNotHasKey(2023, $arc->seasonsByYear);
        self::assertArrayHasKey(2022, $arc->seasonsByYear);
        self::assertArrayHasKey(2024, $arc->seasonsByYear);
    }

    public function testMidSeasonTradeAddsMarkerWithoutSplittingSeason(): void
    {
        $arc = self::service(
            [self::row(2025, 2)],
            [self::move(2025, 2, 'Team 1', 'Team 2')],
        )->build(1, false);

        self::assertNotNull($arc);
        self::assertCount(1, $arc->seasonsByYear);
        self::assertSame(2, $arc->seasonsByYear[2025]->teamid);
        $trades = self::eventsOfType($arc, ArcEvent::TRADE);
        self::assertCount(1, $trades);
        self::assertSame(2025, $trades[0]->year);
        self::assertStringContainsString('Traded', $trades[0]->text);
        self::assertStringContainsString('Team 1', $trades[0]->text);
        self::assertStringContainsString('Team 2', $trades[0]->text);
        self::assertSame('Traded 3/14/2025: Team 1 to Team 2', $trades[0]->text);
    }

    public function testWaiverClaimAndUnknownTeamText(): void
    {
        $arc = self::service(
            [self::row(2025, 2)],
            [self::move(2025, 3, null, 'Team 2')],
        )->build(1, false);

        self::assertNotNull($arc);
        $waivers = self::eventsOfType($arc, ArcEvent::WAIVER);
        self::assertCount(1, $waivers);
        self::assertSame('Claimed off waivers 3/14/2025: unknown team to Team 2', $waivers[0]->text);
    }

    public function testTeamChangeWithoutTransactionAddsNoTradeMarker(): void
    {
        $arc = self::service([self::row(2023, 1), self::row(2024, 2)], [])->build(1, false);

        self::assertNotNull($arc);
        self::assertCount(2, $arc->stints);
        self::assertSame([], self::eventsOfType($arc, ArcEvent::TRADE));
    }

    public function testMoveOutsideAxisWindowDropped(): void
    {
        $arc = self::service(
            [self::row(2022), self::row(2023)],
            [self::move(2026, 2)],
        )->build(1, false);

        self::assertNotNull($arc);
        self::assertSame([], self::eventsOfType($arc, ArcEvent::TRADE));
        self::assertSame(2023, $arc->axisEnd);
    }

    public function testMoveInYearAfterLastSeasonExtendsAxis(): void
    {
        $arc = self::service(
            [self::row(2022), self::row(2023)],
            [self::move(2024, 2)],
        )->build(1, false);

        self::assertNotNull($arc);
        self::assertCount(1, self::eventsOfType($arc, ArcEvent::TRADE));
        self::assertSame(2024, $arc->axisEnd);
        self::assertArrayNotHasKey(2024, $arc->seasonsByYear);
    }

    public function testAwardsOutsideSeasonWindowDropped(): void
    {
        $arc = self::service(
            [self::row(2022), self::row(2023), self::row(2024)],
            [],
            self::facts(),
            [
                ['year' => 2020, 'award' => 'Rookie of the Year'],
                ['year' => 2023, 'award' => 'MVP'],
                ['year' => 2026, 'award' => 'Finals MVP'],
            ],
        )->build(1, false);

        self::assertNotNull($arc);
        $awards = self::eventsOfType($arc, ArcEvent::AWARD);
        self::assertCount(1, $awards);
        self::assertSame(2023, $awards[0]->year);
    }

    public function testAwardsHiddenWhenNameAmbiguous(): void
    {
        $arc = self::service([self::row(2023)], [], self::facts(), null)->build(1, false);

        self::assertNotNull($arc);
        self::assertSame([], self::eventsOfType($arc, ArcEvent::AWARD));
        self::assertTrue($arc->awardsHidden);
    }

    public function testAwardsVisibleWhenNameUnique(): void
    {
        $arc = self::service([self::row(2023)], [], self::facts(), [])->build(1, false);

        self::assertNotNull($arc);
        self::assertFalse($arc->awardsHidden);
    }

    public function testAwardsSameYearGroupedIntoOneEvent(): void
    {
        $arc = self::service(
            [self::row(2022), self::row(2023)],
            [],
            self::facts(),
            [
                ['year' => 2023, 'award' => 'MVP'],
                ['year' => 2023, 'award' => 'All-League First Team'],
            ],
        )->build(1, false);

        self::assertNotNull($arc);
        $awards = self::eventsOfType($arc, ArcEvent::AWARD);
        self::assertCount(1, $awards);
        self::assertSame('MVP; All-League First Team', $awards[0]->text);
    }

    public function testUndraftedPlayerHasNoDraftMarker(): void
    {
        $arc = self::service([self::row(2023)], [], self::facts(2020, 0, 0, ''))->build(1, false);

        self::assertNotNull($arc);
        self::assertSame([], self::eventsOfType($arc, ArcEvent::DRAFT));
        self::assertSame(2023, $arc->axisStart);
    }

    public function testDraftMarkerTextIncludesRoundPickAndTeam(): void
    {
        $arc = self::service([self::row(2023)], [], self::facts(2021, 1, 5, 'Team 3'))->build(1, false);

        self::assertNotNull($arc);
        $drafts = self::eventsOfType($arc, ArcEvent::DRAFT);
        self::assertCount(1, $drafts);
        self::assertSame('Drafted 2021: round 1, pick 5 by Team 3', $drafts[0]->text);
        self::assertSame(2021, $drafts[0]->year);
        self::assertSame(2021, $arc->axisStart);
    }

    public function testDraftMarkerOmitsTeamWhenDraftedByEmpty(): void
    {
        $arc = self::service([self::row(2023)], [], self::facts(2021, 2, 40, ''))->build(1, false);

        self::assertNotNull($arc);
        $drafts = self::eventsOfType($arc, ArcEvent::DRAFT);
        self::assertSame('Drafted 2021: round 2, pick 40', $drafts[0]->text);
    }

    public function testDraftYearClampedToFiveSeasonsBeforeDebut(): void
    {
        $arc = self::service([self::row(2020)], [], self::facts(2010, 1, 3, 'Team 3'))->build(1, false);

        self::assertNotNull($arc);
        self::assertSame(2015, $arc->axisStart);
        $drafts = self::eventsOfType($arc, ArcEvent::DRAFT);
        self::assertCount(1, $drafts);
        self::assertSame(2015, $drafts[0]->year);
        self::assertStringContainsString('2010', $drafts[0]->text);
    }

    public function testRetiredPlayerGetsRetiredMarker(): void
    {
        $retired = self::buildArc([self::row(2022), self::row(2023)], true);
        $active = self::buildArc([self::row(2022), self::row(2023)], false);

        $markers = self::eventsOfType($retired, ArcEvent::RETIRED);
        self::assertCount(1, $markers);
        self::assertSame(2023, $markers[0]->year);
        self::assertSame('Retired after the 2023 season', $markers[0]->text);
        self::assertSame([], self::eventsOfType($active, ArcEvent::RETIRED));
    }

    public function testBestSeasonTiesGoToEarliest(): void
    {
        $arc = self::buildArc([
            self::row(2021, 1, 10, 200, 25, 2),
            self::row(2022, 1, 10, 100, 26, 1),
            self::row(2023, 1, 10, 200, 27, 2),
        ]);

        self::assertSame(2021, $arc->bestPpgYear);
        self::assertSame(2021, $arc->bestRatingYear);
    }

    public function testBestYearsNullWhenSeriesHasNoPoints(): void
    {
        $arc = self::buildArc([self::row(2024, 1, 0, 0, 25, 0)]);

        self::assertNull($arc->bestPpgYear);
        self::assertNull($arc->bestRatingYear);
    }

    public function testServiceAttachesLabelsFromLabeler(): void
    {
        $arc = self::buildArc([
            self::row(2020, 1),
            self::row(2021, 1),
            self::row(2022, 1),
            self::row(2023, 1),
            self::row(2024, 1),
        ]);

        self::assertSame(['One-team career (5 seasons)'], $arc->labels);
    }

    public function testEventsSortedByYearThenType(): void
    {
        $arc = self::service(
            [self::row(2022), self::row(2023)],
            [self::move(2023, 2)],
            self::facts(2022, 1, 1, 'Team 1'),
            [['year' => 2023, 'award' => 'MVP']],
        )->build(1, true);

        self::assertNotNull($arc);
        $types = array_map(static fn (ArcEvent $event): string => $event->type, $arc->events);
        self::assertSame(
            [ArcEvent::DRAFT, ArcEvent::TRADE, ArcEvent::AWARD, ArcEvent::RETIRED],
            $types,
        );
    }
}
