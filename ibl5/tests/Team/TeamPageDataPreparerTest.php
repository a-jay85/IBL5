<?php

declare(strict_types=1);

namespace Tests\Team;

use League\League;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Team\Contracts\TeamQueryRepositoryInterface;
use Team\Contracts\TeamRepositoryInterface;
use Team\Team;
use Team\TeamPageDataPreparer;
use Tests\WideUnit\Mocks\MockDatabase;
use Tests\WideUnit\Mocks\TestDataFactory;

/**
 * Direct unit tests for TeamPageDataPreparer.
 *
 * Complements the 8 preparer assertions in TeamServiceTest (banner grouping, playoff
 * aggregation, best record, standings positions, Stags FKA collapse, null power data).
 * Private helpers buildFormerlyKnownAs / appendBannerYear are covered through
 * prepareCurrentSeasonData / prepareBannerData; no reflection.
 */
class TeamPageDataPreparerTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function bullsRow(): array
    {
        return TestDataFactory::createTeam([
            'teamid' => 1, 'team_name' => 'Bulls', 'team_city' => 'Chicago',
            'color1' => 'CC0000', 'color2' => '000000',
            'arena' => 'United Center', 'capacity' => 20917, 'owner_name' => 'JohnGM',
        ]);
    }

    private static function bullsTeam(): Team
    {
        return Team::initialize(new MockDatabase(), self::bullsRow());
    }

    /** Null collaborators default to inert stubs so each test wires only what it asserts on. */
    private function buildPreparer(
        TeamRepositoryInterface $repo,
        ?TeamQueryRepositoryInterface $queryRepo = null,
        ?League $league = null,
    ): TeamPageDataPreparer {
        return new TeamPageDataPreparer(
            new MockDatabase(),
            $repo,
            $queryRepo ?? self::createStub(TeamQueryRepositoryInterface::class),
            $league ?? self::createStub(League::class),
        );
    }

    /** @return array<string, mixed> one ibl_banners-shaped row (BannerRow) */
    private static function bannerRow(int $year, int $type, string $bannerName = 'Bulls'): array
    {
        return ['year' => $year, 'currentname' => 'Bulls', 'bannername' => $bannerName, 'bannertype' => $type];
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed> full getTeamPowerData() row
     */
    private static function powerRow(array $overrides = []): array
    {
        return array_merge([
            'teamid' => 1, 'team_name' => 'Bulls', 'league_record' => '30-20',
            'wins' => 30, 'losses' => 20, 'pct' => 0.6,
            'conference' => 'Eastern', 'division' => 'Atlantic',
            'conf_record' => '18-12', 'div_record' => '8-4', 'div_gb' => 2.5,
            'home_record' => '18-8', 'away_record' => '12-12',
            'games_unplayed' => 12, 'ranking' => 1.0,
            'last_win' => 5, 'last_loss' => 3,
            'streak_type' => 'W', 'streak' => 2,
            'sos' => 0.5, 'remaining_sos' => 0.5,
        ], $overrides);
    }

    /** @return array<string, mixed> one standings row; the preparer reads only team_name */
    private static function standingRow(string $teamName, int $teamid): array
    {
        return array_merge(self::powerRow(['teamid' => $teamid, 'team_name' => $teamName]), ['div_gb' => 0.0]);
    }

    /** @return array<string, mixed> one FranchiseSeasonRow; ending year is always start + 1 */
    private static function franchiseSeason(int $startYear, string $city, string $name): array
    {
        return [
            'id' => $startYear, 'franchise_id' => 1,
            'season_year' => $startYear, 'season_ending_year' => $startYear + 1,
            'team_city' => $city, 'team_name' => $name,
        ];
    }

    /** @return array<string, mixed> one PlayoffResult row */
    private static function playoffRow(
        int $year,
        int $round,
        string $winner,
        string $loser,
        int $winnerGames,
        int $loserGames,
        ?string $winnerNameThatYear = null,
        ?string $loserNameThatYear = null,
    ): array {
        return [
            'year' => $year, 'round' => $round, 'winner' => $winner, 'loser' => $loser,
            'winner_games' => $winnerGames, 'loser_games' => $loserGames,
            'winner_name_that_year' => $winnerNameThatYear ?? $winner,
            'loser_name_that_year' => $loserNameThatYear ?? $loser,
        ];
    }

    /** @return array<string, mixed> one win/loss history row */
    private static function historyRow(int $year, int $wins, int $losses, string $nameThatYear = 'Bulls'): array
    {
        return ['year' => $year, 'currentname' => 'Bulls', 'namethatyear' => $nameThatYear, 'wins' => $wins, 'losses' => $losses];
    }

    /**
     * @param list<array<string, mixed>> $franchiseSeasons
     * @param array<string, mixed> $powerOverrides
     * @param list<array<string, mixed>> $divisionStandings
     * @param list<array<string, mixed>> $conferenceStandings
     */
    private function currentSeasonRepo(
        array $franchiseSeasons = [],
        array $powerOverrides = [],
        array $divisionStandings = [],
        array $conferenceStandings = [],
    ): TeamRepositoryInterface {
        $repo = self::createStub(TeamRepositoryInterface::class);
        $repo->method('getTeamPowerData')->willReturn(self::powerRow($powerOverrides));
        $repo->method('getDivisionStandings')->willReturn($divisionStandings);
        $repo->method('getConferenceStandings')->willReturn($conferenceStandings);
        $repo->method('getFranchiseSeasons')->willReturn($franchiseSeasons);
        return $repo;
    }

    // -------------------------------------------------------------------------
    // prepareBannerData
    // -------------------------------------------------------------------------

    /** @return array<string, array{int, string, string, ?string}> */
    public static function bannerTypeProvider(): array
    {
        return [
            'type 1 championship' => [1, 'championships', 'IBL Champions', './images/banners/banner1.gif'],
            'type 2 eastern conference' => [2, 'conferenceTitles', 'Eastern Conf. Champions', './images/banners/banner2.gif'],
            'type 3 western conference' => [3, 'conferenceTitles', 'Western Conf. Champions', './images/banners/banner2.gif'],
            'type 4 atlantic division' => [4, 'divisionTitles', 'Atlantic Div. Champions', null],
            'type 5 central division' => [5, 'divisionTitles', 'Central Div. Champions', null],
            'type 6 midwest division' => [6, 'divisionTitles', 'Midwest Div. Champions', null],
            'type 7 pacific division' => [7, 'divisionTitles', 'Pacific Div. Champions', null],
        ];
    }

    #[DataProvider('bannerTypeProvider')]
    public function testPrepareBannerDataMapsEachBannerTypeToLabelAndBackground(
        int $type,
        string $group,
        string $label,
        ?string $bgImage,
    ): void {
        $repo = self::createStub(TeamRepositoryInterface::class);
        $repo->method('getChampionshipBanners')->willReturn([self::bannerRow(2020, $type)]);

        $result = $this->buildPreparer($repo)->prepareBannerData(self::bullsTeam());

        $this->assertSame(
            [['year' => 2020, 'name' => 'Bulls', 'label' => $label, 'bgImage' => $bgImage]],
            $result[$group]['banners'],
        );
        foreach (['championships', 'conferenceTitles', 'divisionTitles'] as $otherGroup) {
            if ($otherGroup !== $group) {
                $this->assertSame([], $result[$otherGroup]['banners']);
            }
        }
    }

    public function testPrepareBannerDataIgnoresBannerTypesOutsideOneThroughSeven(): void
    {
        $repo = self::createStub(TeamRepositoryInterface::class);
        $repo->method('getChampionshipBanners')->willReturn([
            self::bannerRow(2001, 0),
            self::bannerRow(2002, 8),
            self::bannerRow(2003, 1),
        ]);

        $result = $this->buildPreparer($repo)->prepareBannerData(self::bullsTeam());

        $this->assertCount(1, $result['championships']['banners']);
        $this->assertSame('2003', $result['championships']['textSummary']);
        $this->assertSame([], $result['conferenceTitles']['banners']);
        $this->assertSame('', $result['conferenceTitles']['textSummary']);
        $this->assertSame([], $result['divisionTitles']['banners']);
        $this->assertSame('', $result['divisionTitles']['textSummary']);
    }

    public function testPrepareBannerDataTextSummarySuffixesOnlyRenamedBanners(): void
    {
        $repo = self::createStub(TeamRepositoryInterface::class);
        $repo->method('getChampionshipBanners')->willReturn([
            self::bannerRow(2017, 1, 'Stags'),
            self::bannerRow(2018, 1, 'Bulls'),
            self::bannerRow(2019, 1, 'Stags'),
        ]);

        $result = $this->buildPreparer($repo)->prepareBannerData(self::bullsTeam());

        $this->assertSame('2017 (as Stags), 2018, 2019 (as Stags)', $result['championships']['textSummary']);
    }

    public function testPrepareBannerDataKeepsTextSummariesIndependentPerGroup(): void
    {
        $repo = self::createStub(TeamRepositoryInterface::class);
        $repo->method('getChampionshipBanners')->willReturn([
            self::bannerRow(2020, 1, 'Stags'),
            self::bannerRow(2019, 2, 'Bulls'),
            self::bannerRow(2021, 3, 'Stags'),
            self::bannerRow(2018, 4, 'Bulls'),
            self::bannerRow(2022, 5, 'Bulls'),
        ]);

        $result = $this->buildPreparer($repo)->prepareBannerData(self::bullsTeam());

        $this->assertSame('2020 (as Stags)', $result['championships']['textSummary']);
        $this->assertSame('2019, 2021 (as Stags)', $result['conferenceTitles']['textSummary']);
        $this->assertSame('2018, 2022', $result['divisionTitles']['textSummary']);
    }

    public function testPrepareBannerDataPreservesInputOrderAndTeamIdentity(): void
    {
        $repo = self::createStub(TeamRepositoryInterface::class);
        $repo->method('getChampionshipBanners')->willReturn([
            self::bannerRow(2022, 1),
            self::bannerRow(2018, 1),
        ]);

        $result = $this->buildPreparer($repo)->prepareBannerData(self::bullsTeam());

        $this->assertSame(
            [
                ['year' => 2022, 'name' => 'Bulls', 'label' => 'IBL Champions', 'bgImage' => './images/banners/banner1.gif'],
                ['year' => 2018, 'name' => 'Bulls', 'label' => 'IBL Champions', 'bgImage' => './images/banners/banner1.gif'],
            ],
            $result['championships']['banners'],
        );
        $this->assertSame('2022, 2018', $result['championships']['textSummary']);
        $this->assertSame('Bulls', $result['teamName']);
        $this->assertSame('CC0000', $result['color1']);
        $this->assertSame('000000', $result['color2']);
    }

    // -------------------------------------------------------------------------
    // prepareCurrentSeasonData: formerly known as
    // -------------------------------------------------------------------------

    public function testPrepareCurrentSeasonDataFkaIsNullWhenEveryEraMatchesCurrentName(): void
    {
        $repo = $this->currentSeasonRepo([
            self::franchiseSeason(2010, 'Chicago', 'Bulls'),
            self::franchiseSeason(2011, 'Chicago', 'Bulls'),
        ]);

        $result = $this->buildPreparer($repo)->prepareCurrentSeasonData(self::bullsTeam());

        $this->assertNotNull($result);
        $this->assertNull($result['fka']);
    }

    public function testPrepareCurrentSeasonDataFkaListsMultipleNameChangesInOrder(): void
    {
        $repo = $this->currentSeasonRepo([
            self::franchiseSeason(2005, 'Chicago', 'Stags'),
            self::franchiseSeason(2006, 'Chicago', 'Stags'),
            self::franchiseSeason(2007, 'Chicago', 'Zephyrs'),
            self::franchiseSeason(2008, 'Chicago', 'Bulls'),
        ]);

        $result = $this->buildPreparer($repo)->prepareCurrentSeasonData(self::bullsTeam());

        $this->assertNotNull($result);
        $this->assertSame('Chicago Stags (2005-2007), Chicago Zephyrs (2007-2008)', $result['fka']);
    }

    public function testPrepareCurrentSeasonDataFkaTreatsCityChangeAsNewEra(): void
    {
        $repo = $this->currentSeasonRepo([
            self::franchiseSeason(2005, 'Seattle', 'Bulls'),
            self::franchiseSeason(2006, 'Chicago', 'Bulls'),
        ]);

        $result = $this->buildPreparer($repo)->prepareCurrentSeasonData(self::bullsTeam());

        $this->assertNotNull($result);
        $this->assertSame('Seattle Bulls (2005-2006)', $result['fka']);
    }

    public function testPrepareCurrentSeasonDataFkaKeepsNonAdjacentRepeatedNamesAsSeparateEras(): void
    {
        $repo = $this->currentSeasonRepo([
            self::franchiseSeason(2005, 'Chicago', 'Stags'),
            self::franchiseSeason(2006, 'Chicago', 'Bulls'),
            self::franchiseSeason(2007, 'Chicago', 'Stags'),
            self::franchiseSeason(2008, 'Chicago', 'Bulls'),
        ]);

        $result = $this->buildPreparer($repo)->prepareCurrentSeasonData(self::bullsTeam());

        $this->assertNotNull($result);
        $this->assertSame('Chicago Stags (2005-2006), Chicago Stags (2007-2008)', $result['fka']);
    }

    public function testPrepareCurrentSeasonDataFkaListsSingleFormerSeason(): void
    {
        $repo = $this->currentSeasonRepo([self::franchiseSeason(2009, 'Chicago', 'Stags')]);

        $result = $this->buildPreparer($repo)->prepareCurrentSeasonData(self::bullsTeam());

        $this->assertNotNull($result);
        $this->assertSame('Chicago Stags (2009-2010)', $result['fka']);
    }

    // -------------------------------------------------------------------------
    // prepareCurrentSeasonData: standings and power data
    // -------------------------------------------------------------------------

    public function testPrepareCurrentSeasonDataDefaultsPositionsToOneWhenTeamAbsentFromStandings(): void
    {
        $standings = [self::standingRow('Nets', 2), self::standingRow('Celtics', 3)];
        $repo = $this->currentSeasonRepo([], [], $standings, $standings);

        $result = $this->buildPreparer($repo)->prepareCurrentSeasonData(self::bullsTeam());

        $this->assertNotNull($result);
        $this->assertSame(1, $result['divisionPosition']);
        $this->assertSame(1, $result['conferencePosition']);
    }

    public function testPrepareCurrentSeasonDataCastsStringPowerValues(): void
    {
        $repo = $this->currentSeasonRepo([], ['wins' => '30', 'losses' => '20', 'div_gb' => '2.5']);

        $result = $this->buildPreparer($repo)->prepareCurrentSeasonData(self::bullsTeam());

        $this->assertNotNull($result);
        $this->assertSame(30, $result['wins']);
        $this->assertSame(20, $result['losses']);
        $this->assertSame(2.5, $result['divisionGB']);
    }

    public function testPrepareCurrentSeasonDataTreatsNullDivGbAsZero(): void
    {
        $repo = $this->currentSeasonRepo([], ['div_gb' => null]);

        $result = $this->buildPreparer($repo)->prepareCurrentSeasonData(self::bullsTeam());

        $this->assertNotNull($result);
        $this->assertSame(0.0, $result['divisionGB']);
    }

    public function testPrepareCurrentSeasonDataMapsTeamAndPowerFieldsThrough(): void
    {
        $standings = [self::standingRow('Bulls', 1)];
        $repo = $this->currentSeasonRepo([], [], $standings, $standings);

        $result = $this->buildPreparer($repo)->prepareCurrentSeasonData(self::bullsTeam());

        $this->assertSame(
            [
                'teamName' => 'Bulls',
                'fka' => null,
                'wins' => 30,
                'losses' => 20,
                'arena' => 'United Center',
                'capacity' => 20917,
                'conference' => 'Eastern',
                'conferencePosition' => 1,
                'division' => 'Atlantic',
                'divisionPosition' => 1,
                'divisionGB' => 2.5,
                'home_record' => '18-8',
                'away_record' => '12-12',
                'lastWin' => 5,
                'lastLoss' => 3,
            ],
            $result,
        );
    }

    public function testPrepareCurrentSeasonDataQueriesStandingsByPowerDivisionAndConference(): void
    {
        $repo = self::createMock(TeamRepositoryInterface::class);
        $repo->method('getTeamPowerData')->willReturn(self::powerRow());
        $repo->expects($this->once())->method('getDivisionStandings')->with('Atlantic')->willReturn([]);
        $repo->expects($this->once())->method('getConferenceStandings')->with('Eastern')->willReturn([]);
        $repo->expects($this->once())->method('getFranchiseSeasons')->with(1)->willReturn([]);

        $result = $this->buildPreparer($repo)->prepareCurrentSeasonData(self::bullsTeam());

        $this->assertNotNull($result);
    }
}
