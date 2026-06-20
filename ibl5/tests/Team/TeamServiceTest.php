<?php

declare(strict_types=1);

namespace Tests\Team;

use League\League;
use PHPUnit\Framework\TestCase;
use Team\Team;
use Team\TeamService;
use Team\TeamPageDataPreparer;
use Team\Contracts\TeamServiceInterface;
use Team\Contracts\TeamRepositoryInterface;
use Team\Contracts\TeamQueryRepositoryInterface;
use Tests\WideUnit\Mocks\MockDatabase;
use Tests\WideUnit\Mocks\TestDataFactory;

/**
 * Tests for TeamService and TeamPageDataPreparer
 *
 * Validates data orchestration logic and typed-data return shapes.
 */
class TeamServiceTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $interfaces = class_implements(TeamService::class);
        self::assertContains(
            TeamServiceInterface::class,
            $interfaces ? $interfaces : [],
        );
    }

    public function testGetTeamPageDataConsumesInjectedTeamQueryRepository(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->onQuery('ibl_team_info', [
            TestDataFactory::createTeam([
                'teamid' => 1,
                'team_name' => 'Bulls',
                'team_city' => 'Chicago',
            ]),
        ]);

        $repository = self::createStub(TeamRepositoryInterface::class);
        // No current-season power data → skip the current-season card branch.
        $repository->method('getTeamPowerData')->willReturn(null);

        $leagueContext = self::createStub(\League\LeagueContext::class);
        $leagueContext->method('getConfig')->willReturn(['images_path' => '/images']);

        $teamQueryRepository = $this->createMock(TeamQueryRepositoryInterface::class);
        $teamQueryRepository->expects($this->once())
            ->method('getDraftPicks')
            ->with(1)
            ->willReturn([]);

        $league = self::createStub(League::class);
        $league->method('getAllTeamsResult')->willReturn([]);

        $service = new TeamService($mockDb, $repository, $leagueContext, $teamQueryRepository, $league);

        $result = $service->getTeamPageData(1, null, 'ratings');

        // getDraftPicks() expectation above proves the injected repository was
        // consumed; the empty pick list renders the bare draft-picks container.
        $this->assertTrue($result['isActualTeam']);
        $this->assertStringContainsString('draft-picks-list', $result['draftPicksTable']);
    }

    // -------------------------------------------------------------------------
    // TeamPageDataPreparer unit assertions
    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private static function bullsRow(): array
    {
        return TestDataFactory::createTeam([
            'teamid' => 1,
            'team_name' => 'Bulls',
            'team_city' => 'Chicago',
            'color1' => 'CC0000',
            'color2' => '000000',
            'arena' => 'United Center',
            'capacity' => 20917,
            'owner_name' => 'JohnGM',
        ]);
    }

    private static function bullsTeam(): Team
    {
        return Team::initialize(new MockDatabase(), self::bullsRow());
    }

    private function buildPreparer(
        TeamRepositoryInterface $repo,
        TeamQueryRepositoryInterface $queryRepo,
        League $league,
    ): TeamPageDataPreparer {
        return new TeamPageDataPreparer(new MockDatabase(), $repo, $queryRepo, $league);
    }

    public function testPrepareBannerDataClassifiesIntoBannerGroups(): void
    {
        $repo = self::createStub(TeamRepositoryInterface::class);
        $repo->method('getChampionshipBanners')->willReturn([
            ['year' => 2020, 'currentname' => 'Bulls', 'bannername' => 'Bulls', 'bannertype' => 1],
            ['year' => 2019, 'currentname' => 'Bulls', 'bannername' => 'Bulls', 'bannertype' => 2],
            ['year' => 2018, 'currentname' => 'Bulls', 'bannername' => 'Old Bulls', 'bannertype' => 4],
        ]);

        $preparer = $this->buildPreparer($repo, self::createStub(TeamQueryRepositoryInterface::class), self::createStub(League::class));
        $result = $preparer->prepareBannerData(self::bullsTeam());

        $this->assertCount(1, $result['championships']['banners']);
        $this->assertCount(1, $result['conferenceTitles']['banners']);
        $this->assertCount(1, $result['divisionTitles']['banners']);
        $this->assertSame('2020', $result['championships']['textSummary']);
        $this->assertSame('2019', $result['conferenceTitles']['textSummary']);
        $this->assertSame('2018 (as Old Bulls)', $result['divisionTitles']['textSummary']);
        $this->assertSame('Bulls', $result['teamName']);
        $this->assertSame('CC0000', $result['color1']);
    }

    public function testPrepareBannerDataWithNoBannersReturnsEmptyGroups(): void
    {
        $repo = self::createStub(TeamRepositoryInterface::class);
        $repo->method('getChampionshipBanners')->willReturn([]);

        $preparer = $this->buildPreparer($repo, self::createStub(TeamQueryRepositoryInterface::class), self::createStub(League::class));
        $result = $preparer->prepareBannerData(self::bullsTeam());

        $this->assertSame([], $result['championships']['banners']);
        $this->assertSame([], $result['conferenceTitles']['banners']);
        $this->assertSame([], $result['divisionTitles']['banners']);
        $this->assertSame('', $result['championships']['textSummary']);
        $this->assertSame('', $result['conferenceTitles']['textSummary']);
        $this->assertSame('', $result['divisionTitles']['textSummary']);
    }

    public function testPrepareBannerDataTextSummaryWithMultipleBanners(): void
    {
        $repo = self::createStub(TeamRepositoryInterface::class);
        $repo->method('getChampionshipBanners')->willReturn([
            ['year' => 2018, 'currentname' => 'Bulls', 'bannername' => 'Bulls', 'bannertype' => 1],
            ['year' => 2020, 'currentname' => 'Bulls', 'bannername' => 'Bulls', 'bannertype' => 1],
        ]);

        $preparer = $this->buildPreparer($repo, self::createStub(TeamQueryRepositoryInterface::class), self::createStub(League::class));
        $result = $preparer->prepareBannerData(self::bullsTeam());

        $this->assertSame('2018, 2020', $result['championships']['textSummary']);
    }

    public function testPreparePlayoffDataRoundAggregationTotals(): void
    {
        $repo = self::createStub(TeamRepositoryInterface::class);
        $repo->method('getPlayoffResults')->willReturn([
            ['year' => 2020, 'round' => 4, 'winner' => 'Bulls', 'loser' => 'Lakers', 'winner_games' => 4, 'loser_games' => 2, 'winner_name_that_year' => 'Bulls', 'loser_name_that_year' => 'Lakers'],
            ['year' => 2019, 'round' => 3, 'winner' => 'Celtics', 'loser' => 'Bulls', 'winner_games' => 4, 'loser_games' => 1, 'winner_name_that_year' => 'Celtics', 'loser_name_that_year' => 'Bulls'],
        ]);

        $preparer = $this->buildPreparer($repo, self::createStub(TeamQueryRepositoryInterface::class), self::createStub(League::class));
        $result = $preparer->preparePlayoffData(self::bullsTeam());

        // Finals win: Bulls won 4-2 → +4 gameWins, +2 gameLosses
        // Conf Finals loss: Bulls lost 4-1 → +1 gameWins, +4 gameLosses
        $this->assertSame(5, $result['totalGameWins']);
        $this->assertSame(6, $result['totalGameLosses']);
        $this->assertSame(1, $result['totalSeriesWins']);
        $this->assertSame(1, $result['totalSeriesLosses']);

        // Round 4 (IBL Finals): Bulls won 4-2
        $finals = $result['rounds'][3];
        $this->assertSame('IBL Finals', $finals['name']);
        $this->assertSame(4, $finals['gameWins']);
        $this->assertSame(2, $finals['gameLosses']);
        $this->assertSame(1, $finals['seriesWins']);
        $this->assertSame(0, $finals['seriesLosses']);
    }

    public function testPrepareWinLossHistoryDataBestRecordAndTotals(): void
    {
        $repo = self::createStub(TeamRepositoryInterface::class);
        $repo->method('getRegularSeasonHistory')->willReturn([
            ['year' => 2024, 'currentname' => 'Bulls', 'namethatyear' => 'Bulls', 'wins' => 50, 'losses' => 32],
            ['year' => 2023, 'currentname' => 'Bulls', 'namethatyear' => 'Bulls', 'wins' => 45, 'losses' => 37],
        ]);

        $preparer = $this->buildPreparer($repo, self::createStub(TeamQueryRepositoryInterface::class), self::createStub(League::class));
        $result = $preparer->prepareWinLossHistoryData(self::bullsTeam(), 'regular');

        $this->assertSame(95, $result['totalWins']);
        $this->assertSame(69, $result['totalLosses']);
        // 50/82 > 45/82 → 2024 is best
        $this->assertTrue($result['records'][0]['isBest']);
        $this->assertFalse($result['records'][1]['isBest']);
        $this->assertSame(1, $result['teamid']);
    }

    public function testPrepareCurrentSeasonDataStandingsPositions(): void
    {
        $repo = self::createStub(TeamRepositoryInterface::class);
        $repo->method('getTeamPowerData')->willReturn([
            'teamid' => 1, 'team_name' => 'Bulls', 'league_record' => '30-20',
            'wins' => 30, 'losses' => 20, 'pct' => 0.6,
            'conference' => 'Eastern', 'division' => 'Atlantic',
            'conf_record' => '18-12', 'div_record' => '8-4', 'div_gb' => 2.5,
            'home_record' => '18-8', 'away_record' => '12-12',
            'games_unplayed' => 12, 'ranking' => 1.0,
            'last_win' => 5, 'last_loss' => 3,
            'streak_type' => 'W', 'streak' => 2,
            'sos' => 0.5, 'remaining_sos' => 0.5,
        ]);
        $repo->method('getDivisionStandings')->willReturn([
            ['team_name' => 'Bulls', 'wins' => 30, 'losses' => 20, 'div_gb' => 0.0, 'conference' => 'Eastern', 'division' => 'Atlantic', 'teamid' => 1, 'league_record' => '30-20', 'pct' => 0.6, 'conf_record' => '18-12', 'div_record' => '8-4', 'home_record' => '18-8', 'away_record' => '12-12', 'games_unplayed' => 12, 'ranking' => 1.0, 'last_win' => 5, 'last_loss' => 3, 'streak_type' => 'W', 'streak' => 2, 'sos' => 0.5, 'remaining_sos' => 0.5],
            ['team_name' => 'Nets', 'wins' => 25, 'losses' => 25, 'div_gb' => 5.0, 'conference' => 'Eastern', 'division' => 'Atlantic', 'teamid' => 2, 'league_record' => '25-25', 'pct' => 0.5, 'conf_record' => '15-15', 'div_record' => '6-6', 'home_record' => '15-11', 'away_record' => '10-14', 'games_unplayed' => 12, 'ranking' => 2.0, 'last_win' => 3, 'last_loss' => 5, 'streak_type' => 'L', 'streak' => 2, 'sos' => 0.5, 'remaining_sos' => 0.5],
        ]);
        $repo->method('getConferenceStandings')->willReturn([
            ['team_name' => 'Celtics', 'wins' => 35, 'losses' => 15, 'div_gb' => 0.0, 'conference' => 'Eastern', 'division' => 'Atlantic', 'teamid' => 3, 'league_record' => '35-15', 'pct' => 0.7, 'conf_record' => '22-8', 'div_record' => '10-2', 'home_record' => '20-6', 'away_record' => '15-9', 'games_unplayed' => 12, 'ranking' => 0.5, 'last_win' => 7, 'last_loss' => 2, 'streak_type' => 'W', 'streak' => 3, 'sos' => 0.5, 'remaining_sos' => 0.5],
            ['team_name' => 'Bulls', 'wins' => 30, 'losses' => 20, 'div_gb' => 5.0, 'conference' => 'Eastern', 'division' => 'Atlantic', 'teamid' => 1, 'league_record' => '30-20', 'pct' => 0.6, 'conf_record' => '18-12', 'div_record' => '8-4', 'home_record' => '18-8', 'away_record' => '12-12', 'games_unplayed' => 12, 'ranking' => 1.0, 'last_win' => 5, 'last_loss' => 3, 'streak_type' => 'W', 'streak' => 2, 'sos' => 0.5, 'remaining_sos' => 0.5],
        ]);
        $repo->method('getFranchiseSeasons')->willReturn([]);

        $preparer = $this->buildPreparer($repo, self::createStub(TeamQueryRepositoryInterface::class), self::createStub(League::class));
        $result = $preparer->prepareCurrentSeasonData(self::bullsTeam());

        $this->assertNotNull($result);
        $this->assertSame(1, $result['divisionPosition']);
        $this->assertSame(2, $result['conferencePosition']);
        $this->assertSame('Eastern', $result['conference']);
        $this->assertSame('Atlantic', $result['division']);
        $this->assertSame(30, $result['wins']);
        $this->assertSame(20, $result['losses']);
        $this->assertNull($result['fka']);
    }

    public function testPrepareCurrentSeasonDataFkaFromFranchiseSeasons(): void
    {
        $repo = self::createStub(TeamRepositoryInterface::class);
        $repo->method('getTeamPowerData')->willReturn([
            'teamid' => 1, 'team_name' => 'Bulls', 'league_record' => '30-20',
            'wins' => 30, 'losses' => 20, 'pct' => 0.6,
            'conference' => 'Eastern', 'division' => 'Atlantic',
            'conf_record' => '18-12', 'div_record' => '8-4', 'div_gb' => 0.0,
            'home_record' => '18-8', 'away_record' => '12-12',
            'games_unplayed' => 12, 'ranking' => 1.0,
            'last_win' => 5, 'last_loss' => 3,
            'streak_type' => 'W', 'streak' => 2,
            'sos' => 0.5, 'remaining_sos' => 0.5,
        ]);
        $repo->method('getDivisionStandings')->willReturn([
            ['team_name' => 'Bulls', 'wins' => 30, 'losses' => 20, 'div_gb' => 0.0, 'conference' => 'Eastern', 'division' => 'Atlantic', 'teamid' => 1, 'league_record' => '30-20', 'pct' => 0.6, 'conf_record' => '18-12', 'div_record' => '8-4', 'home_record' => '18-8', 'away_record' => '12-12', 'games_unplayed' => 12, 'ranking' => 1.0, 'last_win' => 5, 'last_loss' => 3, 'streak_type' => 'W', 'streak' => 2, 'sos' => 0.5, 'remaining_sos' => 0.5],
        ]);
        $repo->method('getConferenceStandings')->willReturn([
            ['team_name' => 'Bulls', 'wins' => 30, 'losses' => 20, 'div_gb' => 0.0, 'conference' => 'Eastern', 'division' => 'Atlantic', 'teamid' => 1, 'league_record' => '30-20', 'pct' => 0.6, 'conf_record' => '18-12', 'div_record' => '8-4', 'home_record' => '18-8', 'away_record' => '12-12', 'games_unplayed' => 12, 'ranking' => 1.0, 'last_win' => 5, 'last_loss' => 3, 'streak_type' => 'W', 'streak' => 2, 'sos' => 0.5, 'remaining_sos' => 0.5],
        ]);
        $repo->method('getFranchiseSeasons')->willReturn([
            ['id' => 1, 'franchise_id' => 1, 'season_year' => 2010, 'season_ending_year' => 2011, 'team_city' => 'Chicago', 'team_name' => 'Stags'],
            ['id' => 2, 'franchise_id' => 1, 'season_year' => 2011, 'season_ending_year' => 2012, 'team_city' => 'Chicago', 'team_name' => 'Stags'],
            ['id' => 3, 'franchise_id' => 1, 'season_year' => 2012, 'season_ending_year' => 2013, 'team_city' => 'Chicago', 'team_name' => 'Bulls'],
        ]);

        $preparer = $this->buildPreparer($repo, self::createStub(TeamQueryRepositoryInterface::class), self::createStub(League::class));
        $result = $preparer->prepareCurrentSeasonData(self::bullsTeam());

        $this->assertNotNull($result);
        // Two Stags seasons collapse into one era (2010-2012)
        $this->assertSame('Chicago Stags (2010-2012)', $result['fka']);
    }

    public function testPrepareCurrentSeasonDataReturnsNullWhenNoPowerData(): void
    {
        $repo = self::createStub(TeamRepositoryInterface::class);
        $repo->method('getTeamPowerData')->willReturn(null);

        $preparer = $this->buildPreparer($repo, self::createStub(TeamQueryRepositoryInterface::class), self::createStub(League::class));
        $result = $preparer->prepareCurrentSeasonData(self::bullsTeam());

        $this->assertNull($result);
    }
}
