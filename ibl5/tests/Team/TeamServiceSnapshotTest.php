<?php

declare(strict_types=1);

namespace Tests\Team;

use League\League;
use PHPUnit\Framework\TestCase;
use Team\TeamService;
use Team\TeamView;
use Team\Contracts\TeamRepositoryInterface;
use Team\Contracts\TeamQueryRepositoryInterface;
use Tests\WideUnit\Mocks\MockDatabase;
use Tests\WideUnit\Mocks\TestDataFactory;

/**
 * Golden-master snapshot tests for TeamService + TeamView.
 *
 * Each test captures the final rendered HTML output of TeamView::render($pageData)
 * BEFORE any production refactoring. The snapshots serve as the regression guard
 * that proves the refactor produces byte-identical output.
 *
 * Run once to write snapshots, then again to assert they match.
 */
class TeamServiceSnapshotTest extends TestCase
{
    use SnapshotTestTrait;

    /** @return array<string, mixed> */
    private static function bullsTeamRow(): array
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
            'owner_email' => 'gm@bulls.ibl',
        ]);
    }

    private static function buildMockDb(): MockDatabase
    {
        $mockDb = new MockDatabase();
        $mockDb->onQuery('ibl_team_info', [self::bullsTeamRow()]);
        return $mockDb;
    }

    /** @return \PHPUnit\Framework\MockObject\Stub&TeamRepositoryInterface */
    private function buildFullRepository(): TeamRepositoryInterface
    {
        $repo = self::createStub(TeamRepositoryInterface::class);

        $repo->method('getChampionshipBanners')->willReturn([
            ['year' => 2020, 'currentname' => 'Bulls', 'bannername' => 'Bulls', 'bannertype' => 1],
            ['year' => 2019, 'currentname' => 'Bulls', 'bannername' => 'Bulls', 'bannertype' => 2],
            ['year' => 2018, 'currentname' => 'Bulls', 'bannername' => 'Chicago Bulls', 'bannertype' => 4],
        ]);

        $repo->method('getPlayoffResults')->willReturn([
            [
                'year' => 2020, 'round' => 4,
                'winner' => 'Bulls', 'loser' => 'Lakers',
                'winner_games' => 4, 'loser_games' => 2,
                'winner_name_that_year' => 'Bulls',
                'loser_name_that_year' => 'Lakers',
            ],
            [
                'year' => 2019, 'round' => 3,
                'winner' => 'Bulls', 'loser' => 'Celtics',
                'winner_games' => 4, 'loser_games' => 1,
                'winner_name_that_year' => 'Bulls',
                'loser_name_that_year' => 'Celtics',
            ],
        ]);

        $repo->method('getRegularSeasonHistory')->willReturn([
            ['year' => 2024, 'currentname' => 'Bulls', 'namethatyear' => 'Bulls', 'wins' => 50, 'losses' => 32],
            ['year' => 2023, 'currentname' => 'Bulls', 'namethatyear' => 'Bulls', 'wins' => 45, 'losses' => 37],
        ]);

        $repo->method('getHEATHistory')->willReturn([
            ['year' => 2023, 'currentname' => 'Bulls', 'namethatyear' => 'Bulls', 'wins' => 5, 'losses' => 3],
        ]);

        $repo->method('getTeamPowerData')->willReturn([
            'teamid' => 1, 'team_name' => 'Bulls',
            'league_record' => '30-20', 'wins' => 30, 'losses' => 20,
            'pct' => 0.6, 'conference' => 'Eastern', 'division' => 'Atlantic',
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
        $repo->method('getGMTenures')->willReturn([
            ['id' => 1, 'franchise_id' => 1, 'gm_display_name' => 'JohnGM', 'start_season_year' => 2015, 'end_season_year' => null, 'is_mid_season_start' => 0, 'is_mid_season_end' => 0],
        ]);
        $repo->method('getGMAwards')->willReturn([
            ['year' => 2020, 'award' => 'IBL Championship', 'name' => 'JohnGM', 'table_id' => 1],
        ]);
        $repo->method('getTeamAccomplishments')->willReturn([]);

        return $repo;
    }

    public function testFullTeamPageMatchesSnapshot(): void
    {
        $mockDb = self::buildMockDb();
        $repo = $this->buildFullRepository();

        $queryRepo = self::createStub(TeamQueryRepositoryInterface::class);
        $queryRepo->method('getDraftPicks')->willReturn([
            ['pickid' => 1, 'ownerofpick' => 'Bulls', 'teampick' => 'Bulls', 'year' => 2025, 'round' => 1, 'notes' => null],
        ]);

        $league = self::createStub(League::class);
        $league->method('getAllTeamsResult')->willReturn([self::bullsTeamRow()]);

        $leagueContext = self::createStub(\League\LeagueContext::class);
        $leagueContext->method('getConfig')->willReturn(['images_path' => '/images']);

        $service = new TeamService($mockDb, $repo, $leagueContext, $queryRepo, $league);
        $pageData = $service->getTeamPageData(1, null, 'ratings');

        $html = (new TeamView())->render($pageData);
        $this->assertSnapshotMatches($html, 'TeamService_full.html');

        $sidebar = $pageData['rafters']
            . $pageData['currentSeasonCard']
            . $pageData['awardsCard']
            . $pageData['franchiseHistoryCard']
            . ($pageData['draftPicksTable'] ?? '');
        $this->assertSnapshotMatches($sidebar, 'TeamService_sidebar.html');
    }

    public function testFreeAgentPageMatchesSnapshot(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->onQuery('ibl_team_info', [
            TestDataFactory::createTeam(['teamid' => 0, 'team_name' => 'Free Agents', 'team_city' => '', 'owner_name' => '']),
        ]);

        $repo = self::createStub(TeamRepositoryInterface::class);
        $queryRepo = self::createStub(TeamQueryRepositoryInterface::class);
        $queryRepo->method('getDraftPicks')->willReturn([]);

        $league = self::createStub(League::class);
        $league->method('getAllTeamsResult')->willReturn([]);

        $leagueContext = self::createStub(\League\LeagueContext::class);
        $leagueContext->method('getConfig')->willReturn(['images_path' => '/images']);

        $service = new TeamService($mockDb, $repo, $leagueContext, $queryRepo, $league);
        $pageData = $service->getTeamPageData(0, null, 'ratings');

        $html = (new TeamView())->render($pageData);
        $this->assertSnapshotMatches($html, 'TeamService_free_agent.html');
    }

    public function testNoBannersMatchesSnapshot(): void
    {
        $mockDb = self::buildMockDb();

        $repo = self::createStub(TeamRepositoryInterface::class);
        $repo->method('getChampionshipBanners')->willReturn([]);
        $repo->method('getPlayoffResults')->willReturn([]);
        $repo->method('getRegularSeasonHistory')->willReturn([]);
        $repo->method('getHEATHistory')->willReturn([]);
        $repo->method('getTeamPowerData')->willReturn(null);
        $repo->method('getFranchiseSeasons')->willReturn([]);
        $repo->method('getGMTenures')->willReturn([]);
        $repo->method('getGMAwards')->willReturn([]);
        $repo->method('getTeamAccomplishments')->willReturn([]);

        $queryRepo = self::createStub(TeamQueryRepositoryInterface::class);
        $queryRepo->method('getDraftPicks')->willReturn([]);

        $league = self::createStub(League::class);
        $league->method('getAllTeamsResult')->willReturn([]);

        $leagueContext = self::createStub(\League\LeagueContext::class);
        $leagueContext->method('getConfig')->willReturn(['images_path' => '/images']);

        $service = new TeamService($mockDb, $repo, $leagueContext, $queryRepo, $league);
        $pageData = $service->getTeamPageData(1, null, 'ratings');

        $html = (new TeamView())->render($pageData);
        $this->assertSnapshotMatches($html, 'TeamService_no_banners.html');
    }

    public function testNullPowerDataMatchesSnapshot(): void
    {
        $mockDb = self::buildMockDb();

        $repo = self::createStub(TeamRepositoryInterface::class);
        $repo->method('getChampionshipBanners')->willReturn([
            ['year' => 2020, 'currentname' => 'Bulls', 'bannername' => 'Bulls', 'bannertype' => 1],
        ]);
        $repo->method('getPlayoffResults')->willReturn([]);
        $repo->method('getRegularSeasonHistory')->willReturn([
            ['year' => 2024, 'currentname' => 'Bulls', 'namethatyear' => 'Bulls', 'wins' => 50, 'losses' => 32],
        ]);
        $repo->method('getHEATHistory')->willReturn([]);
        $repo->method('getTeamPowerData')->willReturn(null);
        $repo->method('getFranchiseSeasons')->willReturn([]);
        $repo->method('getGMTenures')->willReturn([]);
        $repo->method('getGMAwards')->willReturn([]);
        $repo->method('getTeamAccomplishments')->willReturn([]);

        $queryRepo = self::createStub(TeamQueryRepositoryInterface::class);
        $queryRepo->method('getDraftPicks')->willReturn([]);

        $league = self::createStub(League::class);
        $league->method('getAllTeamsResult')->willReturn([]);

        $leagueContext = self::createStub(\League\LeagueContext::class);
        $leagueContext->method('getConfig')->willReturn(['images_path' => '/images']);

        $service = new TeamService($mockDb, $repo, $leagueContext, $queryRepo, $league);
        $pageData = $service->getTeamPageData(1, null, 'ratings');

        $html = (new TeamView())->render($pageData);
        $this->assertSnapshotMatches($html, 'TeamService_null_power.html');
    }
}
