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

    /** @param list<array<string, mixed>> $rows */
    private function repoReturning(string $method, array $rows): TeamRepositoryInterface
    {
        $repo = self::createStub(TeamRepositoryInterface::class);
        $repo->method($method)->willReturn($rows);
        return $repo;
    }

    /** @param list<array<string, mixed>> $teamRows */
    private function leagueWithTeams(array $teamRows): League
    {
        $league = self::createStub(League::class);
        $league->method('getAllTeamsResult')->willReturn($teamRows);
        return $league;
    }

    /** @return list<array<string, mixed>> Bulls (teamid 1) and Lakers (teamid 2) */
    private static function bullsAndLakersRows(): array
    {
        return [
            self::bullsRow(),
            TestDataFactory::createTeam(['teamid' => 2, 'team_name' => 'Lakers', 'team_city' => 'Los Angeles']),
        ];
    }

    /** @return array<string, mixed> one DraftPickRow; extra columns omitted, the preparer does not read them */
    private static function draftPickRow(
        int $pickId,
        string $owner,
        string $originalTeam,
        int|string $year,
        int|string $round,
        ?string $notes = null,
    ): array {
        return [
            'pickid' => $pickId, 'ownerofpick' => $owner, 'teampick' => $originalTeam,
            'year' => $year, 'round' => $round, 'notes' => $notes,
        ];
    }

    /** @param list<array<string, mixed>> $picks */
    private function queryRepoWithPicks(array $picks): TeamQueryRepositoryInterface
    {
        $queryRepo = self::createStub(TeamQueryRepositoryInterface::class);
        $queryRepo->method('getDraftPicks')->willReturn($picks);
        return $queryRepo;
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
        // Catches a sort or reversal such as array_reverse($champBanners): 2022 stays ahead of 2018.
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

    // -------------------------------------------------------------------------
    // preparePlayoffData
    // -------------------------------------------------------------------------

    public function testPreparePlayoffDataWithNoResultsReturnsFourEmptyRounds(): void
    {
        // The last round must be named 'IBL Finals'; a rename to 'Finals' fails the name list below.
        $preparer = $this->buildPreparer($this->repoReturning('getPlayoffResults', []));

        $result = $preparer->preparePlayoffData(self::bullsTeam());

        $this->assertSame(
            ['First Round', 'Conference Semis', 'Conference Finals', 'IBL Finals'],
            array_column($result['rounds'], 'name'),
        );
        foreach ($result['rounds'] as $round) {
            $this->assertSame(0, $round['gameWins']);
            $this->assertSame(0, $round['gameLosses']);
            $this->assertSame(0, $round['seriesWins']);
            $this->assertSame(0, $round['seriesLosses']);
            $this->assertSame([], $round['results']);
        }
        $this->assertSame(0, $result['totalGameWins']);
        $this->assertSame(0, $result['totalGameLosses']);
        $this->assertSame(0, $result['totalSeriesWins']);
        $this->assertSame(0, $result['totalSeriesLosses']);
    }

    public function testPreparePlayoffDataSkipsRoundsOutsideOneThroughFour(): void
    {
        $preparer = $this->buildPreparer($this->repoReturning('getPlayoffResults', [
            self::playoffRow(2001, 0, 'Bulls', 'Nets', 4, 0),
            self::playoffRow(2002, 5, 'Bulls', 'Nets', 4, 0),
            self::playoffRow(2003, 1, 'Bulls', 'Nets', 4, 1),
        ]));

        $result = $preparer->preparePlayoffData(self::bullsTeam());

        $this->assertSame(4, $result['totalGameWins']);
        $this->assertSame(1, $result['totalGameLosses']);
        $this->assertSame(1, $result['totalSeriesWins']);
        $this->assertSame(0, $result['totalSeriesLosses']);
        $this->assertCount(1, $result['rounds'][0]['results']);
        $this->assertSame([], $result['rounds'][1]['results']);
        $this->assertSame([], $result['rounds'][2]['results']);
        $this->assertSame([], $result['rounds'][3]['results']);
    }

    public function testPreparePlayoffDataAggregatesSeveralSeriesWithinOneRound(): void
    {
        $preparer = $this->buildPreparer($this->repoReturning('getPlayoffResults', [
            self::playoffRow(2019, 2, 'Bulls', 'Celtics', 4, 3),
            self::playoffRow(2018, 2, 'Nets', 'Bulls', 4, 0),
            self::playoffRow(2017, 2, 'Bulls', 'Heat', 4, 2),
        ]));

        $result = $preparer->preparePlayoffData(self::bullsTeam());

        $round = $result['rounds'][1];
        $this->assertSame(8, $round['gameWins']);
        $this->assertSame(9, $round['gameLosses']);
        $this->assertSame(2, $round['seriesWins']);
        $this->assertSame(1, $round['seriesLosses']);
        $this->assertSame(['2019', '2018', '2017'], array_column($round['results'], 'year'));
        $this->assertSame(8, $result['totalGameWins']);
        $this->assertSame(9, $result['totalGameLosses']);
        $this->assertSame(2, $result['totalSeriesWins']);
        $this->assertSame(1, $result['totalSeriesLosses']);
    }

    public function testPreparePlayoffDataResultItemsCarryIsWinAndNamesThatYear(): void
    {
        $preparer = $this->buildPreparer($this->repoReturning('getPlayoffResults', [
            self::playoffRow(2020, 4, 'Bulls', 'Lakers', 4, 2, 'Stags', 'Lakers'),
            self::playoffRow(2019, 3, 'Celtics', 'Bulls', 4, 1, 'Celtics', 'Old Bulls'),
        ]));

        $result = $preparer->preparePlayoffData(self::bullsTeam());

        $this->assertSame(
            ['year' => '2020', 'winner' => 'Stags', 'loser' => 'Lakers', 'winnerGames' => 4, 'loserGames' => 2, 'isWin' => true],
            $result['rounds'][3]['results'][0],
        );
        $this->assertSame(
            ['year' => '2019', 'winner' => 'Celtics', 'loser' => 'Old Bulls', 'winnerGames' => 4, 'loserGames' => 1, 'isWin' => false],
            $result['rounds'][2]['results'][0],
        );
    }

    public function testPreparePlayoffDataEscapesHistoricalTeamNames(): void
    {
        $preparer = $this->buildPreparer($this->repoReturning('getPlayoffResults', [
            self::playoffRow(2020, 1, 'Bulls', 'Nets', 4, 0, '<b>Stags</b>', 'Nets & Co'),
        ]));

        $result = $preparer->preparePlayoffData(self::bullsTeam());

        $this->assertSame('&lt;b&gt;Stags&lt;/b&gt;', $result['rounds'][0]['results'][0]['winner']);
        $this->assertSame('Nets &amp; Co', $result['rounds'][0]['results'][0]['loser']);
    }

    public function testPreparePlayoffDataKeepsRoundTotalsIsolatedAcrossRounds(): void
    {
        // Two populated rounds, interleaved input order, asymmetric game counts.
        // Every paired column differs, so a leak into a neighbouring round or a
        // win/loss column swap in any one round fails this single assertSame.
        $preparer = $this->buildPreparer($this->repoReturning('getPlayoffResults', [
            self::playoffRow(2014, 3, 'Bulls', 'Spurs', 4, 1),
            self::playoffRow(2012, 1, 'Celtics', 'Bulls', 4, 0),
            self::playoffRow(2013, 3, 'Lakers', 'Bulls', 4, 3),
            self::playoffRow(2011, 1, 'Bulls', 'Heat', 4, 2),
            self::playoffRow(2010, 1, 'Bulls', 'Nets', 4, 1),
        ]));

        $result = $preparer->preparePlayoffData(self::bullsTeam());

        $this->assertSame([
            'rounds' => [
                [
                    'name' => 'First Round',
                    'gameWins' => 8,
                    'gameLosses' => 7,
                    'seriesWins' => 2,
                    'seriesLosses' => 1,
                    'results' => [
                        ['year' => '2012', 'winner' => 'Celtics', 'loser' => 'Bulls', 'winnerGames' => 4, 'loserGames' => 0, 'isWin' => false],
                        ['year' => '2011', 'winner' => 'Bulls', 'loser' => 'Heat', 'winnerGames' => 4, 'loserGames' => 2, 'isWin' => true],
                        ['year' => '2010', 'winner' => 'Bulls', 'loser' => 'Nets', 'winnerGames' => 4, 'loserGames' => 1, 'isWin' => true],
                    ],
                ],
                ['name' => 'Conference Semis', 'gameWins' => 0, 'gameLosses' => 0, 'seriesWins' => 0, 'seriesLosses' => 0, 'results' => []],
                [
                    'name' => 'Conference Finals',
                    'gameWins' => 7,
                    'gameLosses' => 5,
                    'seriesWins' => 1,
                    'seriesLosses' => 1,
                    'results' => [
                        ['year' => '2014', 'winner' => 'Bulls', 'loser' => 'Spurs', 'winnerGames' => 4, 'loserGames' => 1, 'isWin' => true],
                        ['year' => '2013', 'winner' => 'Lakers', 'loser' => 'Bulls', 'winnerGames' => 4, 'loserGames' => 3, 'isWin' => false],
                    ],
                ],
                ['name' => 'IBL Finals', 'gameWins' => 0, 'gameLosses' => 0, 'seriesWins' => 0, 'seriesLosses' => 0, 'results' => []],
            ],
            'totalGameWins' => 15,
            'totalGameLosses' => 12,
            'totalSeriesWins' => 3,
            'totalSeriesLosses' => 2,
        ], $result);
    }

    public function testPlayoffHelpersAreStateFreePrivateStatics(): void
    {
        // Security-surface discharge for the escaping move in preparePlayoffData:
        // buildResultItem owns the three HtmlSanitizer::safeHtmlOutput calls and
        // must stay a pure function. Static rules out $this (so no repository or
        // db handle); builtin-only parameters rule out an injected collaborator.
        $helpers = ['emptyRoundsMap', 'buildResultItem', 'addSeriesToRound', 'summarizeRounds'];

        foreach ($helpers as $name) {
            $method = new \ReflectionMethod(\Team\TeamPageDataPreparer::class, $name);

            $this->assertTrue($method->isPrivate(), "$name must be private");
            $this->assertTrue($method->isStatic(), "$name must be static so it cannot touch \$this");

            foreach ($method->getParameters() as $parameter) {
                $type = $parameter->getType();
                $this->assertInstanceOf(\ReflectionNamedType::class, $type, "$name(\${$parameter->getName()}) must declare a type");
                $this->assertTrue(
                    $type->isBuiltin(),
                    "$name(\${$parameter->getName()}) must be a builtin type, found {$type->getName()}",
                );
            }
        }

        $buildResultItem = new \ReflectionMethod(\Team\TeamPageDataPreparer::class, 'buildResultItem');
        $this->assertSame(
            ['playoff', 'teamName'],
            array_map(static fn (\ReflectionParameter $p): string => $p->getName(), $buildResultItem->getParameters()),
        );
    }

    // -------------------------------------------------------------------------
    // prepareWinLossHistoryData
    // -------------------------------------------------------------------------

    public function testPrepareWinLossHistoryDataEmptyHistoryReturnsZeroTotalsAndNoRecords(): void
    {
        $preparer = $this->buildPreparer($this->repoReturning('getRegularSeasonHistory', []));

        $result = $preparer->prepareWinLossHistoryData(self::bullsTeam(), 'regular');

        $this->assertSame([], $result['records']);
        $this->assertSame(0, $result['totalWins']);
        $this->assertSame(0, $result['totalLosses']);
        $this->assertSame(1, $result['teamid']);
    }

    public function testPrepareWinLossHistoryDataSingleSeasonIsBest(): void
    {
        $preparer = $this->buildPreparer($this->repoReturning('getRegularSeasonHistory', [
            self::historyRow(2024, 50, 32),
        ]));

        $result = $preparer->prepareWinLossHistoryData(self::bullsTeam(), 'regular');

        $this->assertCount(1, $result['records']);
        $this->assertTrue($result['records'][0]['isBest']);
        $this->assertSame(50, $result['totalWins']);
        $this->assertSame(32, $result['totalLosses']);
    }

    public function testPrepareWinLossHistoryDataRegularSeasonLabelAndUrlYear(): void
    {
        $preparer = $this->buildPreparer($this->repoReturning('getRegularSeasonHistory', [
            self::historyRow(2024, 50, 32, 'Stags'),
        ]));

        $result = $preparer->prepareWinLossHistoryData(self::bullsTeam(), 'regular');

        $this->assertSame(
            ['year' => 2024, 'label' => '2023-2024 Stags', 'wins' => 50, 'losses' => 32, 'urlYear' => 2024, 'isBest' => true],
            $result['records'][0],
        );
    }

    public function testPrepareWinLossHistoryDataHeatUsesHeatHistoryWithOffsetYear(): void
    {
        $repo = self::createMock(TeamRepositoryInterface::class);
        $repo->expects($this->once())->method('getHEATHistory')->with('Bulls')
            ->willReturn([self::historyRow(2024, 10, 5, 'Stags')]);
        $repo->expects($this->never())->method('getRegularSeasonHistory');

        $result = $this->buildPreparer($repo)->prepareWinLossHistoryData(self::bullsTeam(), 'heat');

        $this->assertSame('2024 Stags', $result['records'][0]['label']);
        $this->assertSame(2024, $result['records'][0]['year']);
        $this->assertSame(2025, $result['records'][0]['urlYear']);
    }

    public function testPrepareWinLossHistoryDataTreatsUnknownTypeAsRegularSeason(): void
    {
        $repo = self::createMock(TeamRepositoryInterface::class);
        $repo->expects($this->once())->method('getRegularSeasonHistory')->with('Bulls')
            ->willReturn([self::historyRow(2024, 50, 32)]);
        $repo->expects($this->never())->method('getHEATHistory');

        $result = $this->buildPreparer($repo)->prepareWinLossHistoryData(self::bullsTeam(), 'playoff');

        $this->assertSame('2023-2024 Bulls', $result['records'][0]['label']);
    }

    /** @return array<string, array{list<array<string, mixed>>, list<bool>}> */
    public static function tiedPercentageProvider(): array
    {
        return [
            'fewer wins first' => [[self::historyRow(2022, 20, 20), self::historyRow(2023, 41, 41)], [false, true]],
            'more wins first' => [[self::historyRow(2023, 41, 41), self::historyRow(2022, 20, 20)], [true, false]],
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<bool> $expectedIsBest
     */
    #[DataProvider('tiedPercentageProvider')]
    public function testPrepareWinLossHistoryDataTieOnPercentageFavorsMoreWins(array $rows, array $expectedIsBest): void
    {
        $preparer = $this->buildPreparer($this->repoReturning('getRegularSeasonHistory', $rows));

        $result = $preparer->prepareWinLossHistoryData(self::bullsTeam(), 'regular');

        $this->assertSame($expectedIsBest, array_column($result['records'], 'isBest'));
    }

    public function testPrepareWinLossHistoryDataExactTieKeepsFirstRecord(): void
    {
        $preparer = $this->buildPreparer($this->repoReturning('getRegularSeasonHistory', [
            self::historyRow(2024, 50, 32),
            self::historyRow(2023, 50, 32),
        ]));

        $result = $preparer->prepareWinLossHistoryData(self::bullsTeam(), 'regular');

        $this->assertSame([true, false], array_column($result['records'], 'isBest'));
    }

    public function testPrepareWinLossHistoryDataSkipsZeroGameSeasonsWhenPickingBest(): void
    {
        $preparer = $this->buildPreparer($this->repoReturning('getRegularSeasonHistory', [
            self::historyRow(2022, 0, 0),
            self::historyRow(2023, 30, 20),
        ]));

        $result = $preparer->prepareWinLossHistoryData(self::bullsTeam(), 'regular');

        $this->assertSame([false, true], array_column($result['records'], 'isBest'));
        $this->assertSame(30, $result['totalWins']);
        $this->assertSame(20, $result['totalLosses']);
    }

    public function testPrepareWinLossHistoryDataMarksNoBestWhenEverySeasonHasZeroGames(): void
    {
        $preparer = $this->buildPreparer($this->repoReturning('getRegularSeasonHistory', [
            self::historyRow(2022, 0, 0),
            self::historyRow(2023, 0, 0),
        ]));

        $result = $preparer->prepareWinLossHistoryData(self::bullsTeam(), 'regular');

        $this->assertSame([false, false], array_column($result['records'], 'isBest'));
        $this->assertSame(0, $result['totalWins']);
        $this->assertSame(0, $result['totalLosses']);
    }

    public function testPrepareWinLossHistoryDataCastsStringWinsAndLosses(): void
    {
        $row = array_merge(self::historyRow(2024, 0, 0), ['wins' => '50', 'losses' => '32']);
        $preparer = $this->buildPreparer($this->repoReturning('getRegularSeasonHistory', [$row]));

        $result = $preparer->prepareWinLossHistoryData(self::bullsTeam(), 'regular');

        $this->assertSame(50, $result['records'][0]['wins']);
        $this->assertSame(32, $result['records'][0]['losses']);
        $this->assertSame(50, $result['totalWins']);
    }

    public function testPrepareWinLossHistoryDataEscapesNameThatYearInLabel(): void
    {
        $preparer = $this->buildPreparer($this->repoReturning('getRegularSeasonHistory', [
            self::historyRow(2024, 50, 32, '<i>X</i>'),
        ]));

        $result = $preparer->prepareWinLossHistoryData(self::bullsTeam(), 'regular');

        $this->assertSame('2023-2024 &lt;i&gt;X&lt;/i&gt;', $result['records'][0]['label']);
    }

    // -------------------------------------------------------------------------
    // prepareDraftPicksData
    // -------------------------------------------------------------------------

    public function testPrepareDraftPicksDataReturnsEmptyListWhenNoPicks(): void
    {
        $preparer = $this->buildPreparer(
            self::createStub(TeamRepositoryInterface::class),
            $this->queryRepoWithPicks([]),
            $this->leagueWithTeams(self::bullsAndLakersRows()),
        );

        $this->assertSame([], $preparer->prepareDraftPicksData(self::bullsTeam()));
    }

    public function testPrepareDraftPicksDataMapsOriginalTeamFromLeagueTeams(): void
    {
        $preparer = $this->buildPreparer(
            self::createStub(TeamRepositoryInterface::class),
            $this->queryRepoWithPicks([self::draftPickRow(11, 'Bulls', 'Lakers', '2026', '1', 'Top 5 protected')]),
            $this->leagueWithTeams(self::bullsAndLakersRows()),
        );

        $result = $preparer->prepareDraftPicksData(self::bullsTeam());

        $this->assertSame(
            [[
                'originalTeamID' => 2,
                'originalTeamCity' => 'Los Angeles',
                'originalTeamName' => 'Lakers',
                'year' => '2026',
                'round' => '1',
                'notes' => 'Top 5 protected',
            ]],
            $result,
        );
    }

    public function testPrepareDraftPicksDataPreservesPickOrderAndNullNotes(): void
    {
        // Catches iteration over array_reverse($resultPicks): the Bulls pick must stay first.
        $preparer = $this->buildPreparer(
            self::createStub(TeamRepositoryInterface::class),
            $this->queryRepoWithPicks([
                self::draftPickRow(12, 'Bulls', 'Bulls', '2026', '2'),
                self::draftPickRow(13, 'Bulls', 'Lakers', '2027', '1', 'Swap'),
            ]),
            $this->leagueWithTeams(self::bullsAndLakersRows()),
        );

        $result = $preparer->prepareDraftPicksData(self::bullsTeam());

        $this->assertCount(2, $result);
        $this->assertSame(1, $result[0]['originalTeamID']);
        $this->assertSame('Chicago', $result[0]['originalTeamCity']);
        $this->assertSame('Bulls', $result[0]['originalTeamName']);
        $this->assertNull($result[0]['notes']);
        $this->assertSame(2, $result[1]['originalTeamID']);
        $this->assertSame('Swap', $result[1]['notes']);
    }

    public function testPrepareDraftPicksDataCastsIntegerYearToString(): void
    {
        $preparer = $this->buildPreparer(
            self::createStub(TeamRepositoryInterface::class),
            $this->queryRepoWithPicks([self::draftPickRow(14, 'Bulls', 'Bulls', 2027, 2)]),
            $this->leagueWithTeams(self::bullsAndLakersRows()),
        );

        $result = $preparer->prepareDraftPicksData(self::bullsTeam());

        $this->assertSame('2027', $result[0]['year']);
        $this->assertSame(2, $result[0]['round']);
    }

    public function testPrepareDraftPicksDataFallsBackWhenOriginalTeamMissingFromLeagueTeams(): void
    {
        $preparer = $this->buildPreparer(
            self::createStub(TeamRepositoryInterface::class),
            $this->queryRepoWithPicks([self::draftPickRow(15, 'Bulls', 'Ghosts', '2028', '1', 'Defunct')]),
            $this->leagueWithTeams(self::bullsAndLakersRows()),
        );

        $result = $preparer->prepareDraftPicksData(self::bullsTeam());

        $this->assertSame(
            [[
                'originalTeamID' => 0,
                'originalTeamCity' => '',
                'originalTeamName' => 'Ghosts',
                'year' => '2028',
                'round' => '1',
                'notes' => 'Defunct',
            ]],
            $result,
        );
    }

    public function testPrepareDraftPicksDataRequestsPicksForTheTeamId(): void
    {
        $queryRepo = self::createMock(TeamQueryRepositoryInterface::class);
        $queryRepo->expects($this->once())->method('getDraftPicks')->with(1)->willReturn([]);

        $preparer = $this->buildPreparer(
            self::createStub(TeamRepositoryInterface::class),
            $queryRepo,
            $this->leagueWithTeams(self::bullsAndLakersRows()),
        );

        $this->assertSame([], $preparer->prepareDraftPicksData(self::bullsTeam()));
    }

    // -------------------------------------------------------------------------
    // prepareAwardsData
    // -------------------------------------------------------------------------

    public function testPrepareAwardsDataDelegatesWithTeamIdOwnerNameAndTeamName(): void
    {
        $repo = self::createMock(TeamRepositoryInterface::class);
        $repo->expects($this->once())->method('getGMTenures')->with(1)->willReturn([]);
        $repo->expects($this->once())->method('getGMAwards')->with('JohnGM')->willReturn([]);
        $repo->expects($this->once())->method('getTeamAccomplishments')->with('Bulls')->willReturn([]);

        $result = $this->buildPreparer($repo)->prepareAwardsData(self::bullsTeam());

        $this->assertSame(['tenures' => [], 'gmAwards' => [], 'teamAccomplishments' => []], $result);
    }

    public function testPrepareAwardsDataReturnsRepositoryListsUnchangedUnderTheirKeys(): void
    {
        $tenures = [[
            'id' => 1, 'franchise_id' => 1, 'gm_display_name' => 'John GM',
            'start_season_year' => 2005, 'end_season_year' => null,
            'is_mid_season_start' => 0, 'is_mid_season_end' => 0,
        ]];
        $gmAwards = [['year' => 2010, 'award' => 'GM of the Year', 'name' => 'JohnGM', 'table_id' => 7]];
        $accomplishments = [['year' => 2011, 'name' => 'Bulls', 'award' => 'Best Record', 'id' => 3]];

        $repo = self::createStub(TeamRepositoryInterface::class);
        $repo->method('getGMTenures')->willReturn($tenures);
        $repo->method('getGMAwards')->willReturn($gmAwards);
        $repo->method('getTeamAccomplishments')->willReturn($accomplishments);

        $result = $this->buildPreparer($repo)->prepareAwardsData(self::bullsTeam());

        $this->assertSame(
            ['tenures' => $tenures, 'gmAwards' => $gmAwards, 'teamAccomplishments' => $accomplishments],
            $result,
        );
    }

    public function testPrepareAwardsDataWithEmptyRepositoryResultsReturnsThreeEmptyLists(): void
    {
        $repo = self::createStub(TeamRepositoryInterface::class);
        $repo->method('getGMTenures')->willReturn([]);
        $repo->method('getGMAwards')->willReturn([]);
        $repo->method('getTeamAccomplishments')->willReturn([]);

        $result = $this->buildPreparer($repo)->prepareAwardsData(self::bullsTeam());

        $this->assertSame(['tenures' => [], 'gmAwards' => [], 'teamAccomplishments' => []], $result);
    }
}
