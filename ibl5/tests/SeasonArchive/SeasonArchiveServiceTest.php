<?php

declare(strict_types=1);

namespace Tests\SeasonArchive;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use SeasonArchive\SeasonArchiveService;
use SeasonArchive\Contracts\SeasonArchiveRepositoryInterface;
use SeasonArchive\Contracts\SeasonArchiveServiceInterface;

/**
 * SeasonArchiveServiceTest - Tests for SeasonArchiveService business logic
 *
 * @covers \SeasonArchive\SeasonArchiveService
 */
#[AllowMockObjectsWithoutExpectations]
class SeasonArchiveServiceTest extends TestCase
{
    /** @var SeasonArchiveRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject */
    private SeasonArchiveRepositoryInterface $mockRepository;
    private SeasonArchiveService $service;

    protected function setUp(): void
    {
        $this->mockRepository = $this->createMock(SeasonArchiveRepositoryInterface::class);
        $this->mockRepository->method('getTeamConferences')->willReturn([]);
        $this->service = new SeasonArchiveService($this->mockRepository);
    }

    public function testImplementsSeasonArchiveServiceInterface(): void
    {
        $this->assertInstanceOf(SeasonArchiveServiceInterface::class, $this->service);
    }

    public function testBuildSeasonLabelForSeasonOne(): void
    {
        $label = $this->service->buildSeasonLabel(1989);
        $this->assertSame('Season I (1988-89)', $label);
    }

    public function testBuildSeasonLabelForSeasonEighteen(): void
    {
        $label = $this->service->buildSeasonLabel(2006);
        $this->assertSame('Season XVIII (2005-06)', $label);
    }

    public function testBuildSeasonLabelForSeasonTen(): void
    {
        $label = $this->service->buildSeasonLabel(1998);
        $this->assertSame('Season X (1997-98)', $label);
    }

    public function testGetSeasonDetailReturnsNullForInvalidYear(): void
    {
        $result = $this->service->getSeasonDetail(1980);
        $this->assertNull($result);
    }

    public function testGetSeasonDetailReturnsNullForYearBeyondRange(): void
    {
        $result = $this->service->getSeasonDetail(2010);
        $this->assertNull($result);
    }

    public function testGetSeasonDetailReturnsNullWhenNoAwards(): void
    {
        $this->mockRepository->method('getAwardsByYear')->willReturn([]);

        $result = $this->service->getSeasonDetail(1989);
        $this->assertNull($result);
    }

    public function testGetSeasonDetailReturnsDataForValidYear(): void
    {
        $this->mockRepository->method('getAwardsByYear')->willReturn([
            ['year' => 1989, 'Award' => 'Most Valuable Player (1st)', 'name' => 'Arvydas Sabonis', 'table_ID' => 76],
            ['year' => 1989, 'Award' => 'Defensive Player of the Year (1st)', 'name' => 'Hakeem Olajuwon', 'table_ID' => 77],
            ['year' => 1989, 'Award' => 'Rookie of the Year (1st)', 'name' => 'Test Rookie', 'table_ID' => 78],
            ['year' => 1989, 'Award' => '6th Man Award (1st)', 'name' => 'Test SixthMan', 'table_ID' => 79],
            ['year' => 1989, 'Award' => 'IBL Finals MVP', 'name' => 'Test FinalsMVP', 'table_ID' => 80],
            ['year' => 1989, 'Award' => 'Scoring Leader (1st)', 'name' => 'Test Scorer', 'table_ID' => 81],
            ['year' => 1989, 'Award' => 'Rebounding Leader (1st)', 'name' => 'Test Rebounder', 'table_ID' => 82],
            ['year' => 1989, 'Award' => 'Assists Leader (1st)', 'name' => 'Test Assister', 'table_ID' => 83],
            ['year' => 1989, 'Award' => 'Steals Leader (1st)', 'name' => 'Test Stealer', 'table_ID' => 84],
            ['year' => 1989, 'Award' => 'Blocks Leader (1st)', 'name' => 'Test Blocker', 'table_ID' => 85],
        ]);
        $this->mockRepository->method('getPlayoffResultsByYear')->willReturn([
            ['year' => 1989, 'round' => 4, 'winner' => 'Clippers', 'loser' => 'Raptors', 'loser_games' => 3, 'id' => 15],
        ]);
        $this->mockRepository->method('getTeamAwardsByYear')->willReturn([
            ['year' => '<B>1989</B>', 'name' => 'Rockets', 'Award' => '<B>IBL HEAT Champions</b>', 'ID' => 11],
        ]);
        $this->mockRepository->method('getAllGmAwardsWithTeams')->willReturn([]);
        $this->mockRepository->method('getAllGmTenuresWithTeams')->willReturn([]);
        $this->mockRepository->method('getHeatWinLossByYear')->willReturn([
            ['year' => 1988, 'currentname' => 'Clippers', 'namethatyear' => 'Clippers', 'wins' => 10, 'losses' => 2, 'table_ID' => 13],
        ]);
        $this->mockRepository->method('getTeamColors')->willReturn([
            'Clippers' => ['color1' => 'C8102E', 'color2' => 'FFFFFF', 'teamid' => 5],
        ]);
        $this->mockRepository->method('getPlayerIdsByNames')->willReturn([
            'Arvydas Sabonis' => 100,
        ]);

        $result = $this->service->getSeasonDetail(1989);
        $this->assertNotNull($result);
        $this->assertSame(1989, $result['year']);
        $this->assertSame('Season I (1988-89)', $result['label']);
        $this->assertSame('Arvydas Sabonis', $result['majorAwards']['mvp']);
        $this->assertSame('Hakeem Olajuwon', $result['majorAwards']['dpoy']);
        $this->assertSame('Test Scorer', $result['statisticalLeaders']['scoring']);
        $this->assertSame('Clippers', $result['tournaments']['iblFinalsWinner']);
        $this->assertSame('Raptors', $result['tournaments']['iblFinalsLoser']);
        $this->assertSame(3, $result['tournaments']['iblFinalsLoserGames']);

        // Verify new fields exist
        $this->assertArrayHasKey('playerIds', $result);
        $this->assertArrayHasKey('teamIds', $result);
        $this->assertArrayHasKey('allStarCoaches', $result);
        $this->assertSame(100, $result['playerIds']['Arvydas Sabonis']);
        $this->assertSame(5, $result['teamIds']['Clippers']);
    }

    public function testGetSeasonDetailUsesHeatYearMinusOne(): void
    {
        $this->mockRepository->method('getAwardsByYear')->willReturn([
            ['year' => 1989, 'Award' => 'Most Valuable Player (1st)', 'name' => 'Test MVP', 'table_ID' => 1],
        ]);
        $this->mockRepository->method('getPlayoffResultsByYear')->willReturn([]);
        $this->mockRepository->method('getTeamAwardsByYear')->willReturn([]);
        $this->mockRepository->method('getAllGmAwardsWithTeams')->willReturn([]);
        $this->mockRepository->method('getAllGmTenuresWithTeams')->willReturn([]);
        $this->mockRepository->method('getTeamColors')->willReturn([]);
        $this->mockRepository->method('getPlayerIdsByNames')->willReturn([]);

        // Verify that getHeatWinLossByYear is called with year - 1
        $this->mockRepository->expects($this->once())
            ->method('getHeatWinLossByYear')
            ->with(1988)
            ->willReturn([]);

        $this->service->getSeasonDetail(1989);
    }

    public function testExtractAwardHandlesTrailingWhitespace(): void
    {
        $this->mockRepository->method('getAwardsByYear')->willReturn([
            ['year' => 1989, 'Award' => "One-on-One Tournament Champion\n", 'name' => 'Test Player', 'table_ID' => 1],
            ['year' => 1989, 'Award' => 'Most Valuable Player (1st)', 'name' => 'Test MVP', 'table_ID' => 2],
        ]);
        $this->mockRepository->method('getPlayoffResultsByYear')->willReturn([]);
        $this->mockRepository->method('getTeamAwardsByYear')->willReturn([]);
        $this->mockRepository->method('getAllGmAwardsWithTeams')->willReturn([]);
        $this->mockRepository->method('getAllGmTenuresWithTeams')->willReturn([]);
        $this->mockRepository->method('getHeatWinLossByYear')->willReturn([]);
        $this->mockRepository->method('getTeamColors')->willReturn([]);
        $this->mockRepository->method('getPlayerIdsByNames')->willReturn([]);

        $result = $this->service->getSeasonDetail(1989);
        $this->assertNotNull($result);
        $this->assertSame('Test Player', $result['tournaments']['oneOnOneChampion']);
    }

    public function testGmOfYearFromNormalizedData(): void
    {
        $this->mockRepository->method('getAwardsByYear')->willReturn([
            ['year' => 1990, 'Award' => 'Most Valuable Player (1st)', 'name' => 'Test MVP', 'table_ID' => 1],
        ]);
        $this->mockRepository->method('getPlayoffResultsByYear')->willReturn([]);
        $this->mockRepository->method('getTeamAwardsByYear')->willReturn([]);
        $this->mockRepository->method('getAllGmAwardsWithTeams')->willReturn([
            ['year' => 1990, 'Award' => 'GM of the Year', 'gm_username' => 'Ross Gates', 'team_name' => 'Bulls', 'table_ID' => 8],
            ['year' => 1993, 'Award' => 'GM of the Year', 'gm_username' => 'Ross Gates', 'team_name' => 'Bulls', 'table_ID' => 9],
        ]);
        $this->mockRepository->method('getAllGmTenuresWithTeams')->willReturn([]);
        $this->mockRepository->method('getHeatWinLossByYear')->willReturn([]);
        $this->mockRepository->method('getTeamColors')->willReturn([]);
        $this->mockRepository->method('getPlayerIdsByNames')->willReturn([]);

        $result = $this->service->getSeasonDetail(1990);
        $this->assertNotNull($result);
        $this->assertSame(['name' => 'Ross Gates', 'team' => 'Bulls'], $result['majorAwards']['gmOfYear']);
    }

    public function testGmOfYearReturnsEmptyWhenNotFound(): void
    {
        $this->mockRepository->method('getAwardsByYear')->willReturn([
            ['year' => 1989, 'Award' => 'Most Valuable Player (1st)', 'name' => 'Test MVP', 'table_ID' => 1],
        ]);
        $this->mockRepository->method('getPlayoffResultsByYear')->willReturn([]);
        $this->mockRepository->method('getTeamAwardsByYear')->willReturn([]);
        $this->mockRepository->method('getAllGmAwardsWithTeams')->willReturn([
            ['year' => 1990, 'Award' => 'GM of the Year', 'gm_username' => 'Ross Gates', 'team_name' => 'Bulls', 'table_ID' => 8],
        ]);
        $this->mockRepository->method('getAllGmTenuresWithTeams')->willReturn([]);
        $this->mockRepository->method('getHeatWinLossByYear')->willReturn([]);
        $this->mockRepository->method('getTeamColors')->willReturn([]);
        $this->mockRepository->method('getPlayerIdsByNames')->willReturn([]);

        $result = $this->service->getSeasonDetail(1989);
        $this->assertNotNull($result);
        $this->assertSame(['name' => '', 'team' => ''], $result['majorAwards']['gmOfYear']);
    }

    public function testTeamAwardsHtmlStrippedAndCategorized(): void
    {
        $this->mockRepository->method('getAwardsByYear')->willReturn([
            ['year' => 1989, 'Award' => 'Most Valuable Player (1st)', 'name' => 'Test MVP', 'table_ID' => 1],
        ]);
        $this->mockRepository->method('getPlayoffResultsByYear')->willReturn([]);
        $this->mockRepository->method('getTeamAwardsByYear')->willReturn([
            ['year' => '<B>1989</B>', 'name' => 'Clippers', 'Award' => '<B>Pacific Division Champions</b>', 'ID' => 5],
            ['year' => '<B>1989</B>', 'name' => 'Clippers', 'Award' => '<B>IBL Champions</b>', 'ID' => 8],
        ]);
        $this->mockRepository->method('getAllGmAwardsWithTeams')->willReturn([]);
        $this->mockRepository->method('getAllGmTenuresWithTeams')->willReturn([]);
        $this->mockRepository->method('getHeatWinLossByYear')->willReturn([]);
        $this->mockRepository->method('getTeamColors')->willReturn([]);
        $this->mockRepository->method('getPlayerIdsByNames')->willReturn([]);

        $result = $this->service->getSeasonDetail(1989);
        $this->assertNotNull($result);
        $this->assertArrayHasKey('Pacific Division Champions', $result['teamAwards']);
        $this->assertSame('Clippers', $result['teamAwards']['Pacific Division Champions']);
        $this->assertArrayHasKey('IBL Champions', $result['teamAwards']);
        $this->assertSame('Clippers', $result['teamAwards']['IBL Champions']);
    }

    public function testChallongeHeatUrlGeneration(): void
    {
        $this->mockRepository->method('getAwardsByYear')->willReturn([
            ['year' => 1989, 'Award' => 'Most Valuable Player (1st)', 'name' => 'Test MVP', 'table_ID' => 1],
        ]);
        $this->mockRepository->method('getPlayoffResultsByYear')->willReturn([]);
        $this->mockRepository->method('getTeamAwardsByYear')->willReturn([]);
        $this->mockRepository->method('getAllGmAwardsWithTeams')->willReturn([]);
        $this->mockRepository->method('getAllGmTenuresWithTeams')->willReturn([]);
        $this->mockRepository->method('getHeatWinLossByYear')->willReturn([]);
        $this->mockRepository->method('getTeamColors')->willReturn([]);
        $this->mockRepository->method('getPlayerIdsByNames')->willReturn([]);

        // Season I (1989): HEAT year = 1988
        $result = $this->service->getSeasonDetail(1989);
        $this->assertNotNull($result);
        $this->assertSame('https://challonge.com/IBLheat88', $result['tournaments']['heatUrl']);
        $this->assertSame('https://challonge.com/iblplayoffs1989', $result['tournaments']['playoffsUrl']);
    }

    public function testChallongeHeatUrl1994Exception(): void
    {
        $this->mockRepository->method('getAwardsByYear')->willReturn([
            ['year' => 1995, 'Award' => 'Most Valuable Player (1st)', 'name' => 'Test MVP', 'table_ID' => 1],
        ]);
        $this->mockRepository->method('getPlayoffResultsByYear')->willReturn([]);
        $this->mockRepository->method('getTeamAwardsByYear')->willReturn([]);
        $this->mockRepository->method('getAllGmAwardsWithTeams')->willReturn([]);
        $this->mockRepository->method('getAllGmTenuresWithTeams')->willReturn([]);
        $this->mockRepository->method('getHeatWinLossByYear')->willReturn([]);
        $this->mockRepository->method('getTeamColors')->willReturn([]);
        $this->mockRepository->method('getPlayerIdsByNames')->willReturn([]);

        // Season VII (1995): HEAT year = 1994, uses lowercase
        $result = $this->service->getSeasonDetail(1995);
        $this->assertNotNull($result);
        $this->assertSame('https://challonge.com/iblheat94', $result['tournaments']['heatUrl']);
    }

    public function testGetAllSeasonsReturnsSeasonsSortedDescending(): void
    {
        $this->mockRepository->method('getAllSeasonYears')->willReturn([1989, 1990, 1991]);
        $this->mockRepository->method('getAwardsByYear')->willReturn([
            ['year' => 1989, 'Award' => 'Most Valuable Player (1st)', 'name' => 'Test MVP', 'table_ID' => 1],
        ]);
        $this->mockRepository->method('getPlayoffResultsByYear')->willReturn([]);
        $this->mockRepository->method('getTeamAwardsByYear')->willReturn([]);

        $seasons = $this->service->getAllSeasons();

        $this->assertCount(3, $seasons);
        $this->assertSame(1991, $seasons[0]['year']);
        $this->assertSame(1990, $seasons[1]['year']);
        $this->assertSame(1989, $seasons[2]['year']);
    }

    public function testGetAllSeasonsSkipsYear1988(): void
    {
        $this->mockRepository->method('getAllSeasonYears')->willReturn([1988, 1989]);
        $this->mockRepository->method('getAwardsByYear')->willReturn([
            ['year' => 1989, 'Award' => 'Most Valuable Player (1st)', 'name' => 'Test MVP', 'table_ID' => 1],
        ]);
        $this->mockRepository->method('getPlayoffResultsByYear')->willReturn([]);
        $this->mockRepository->method('getTeamAwardsByYear')->willReturn([]);

        $seasons = $this->service->getAllSeasons();

        $this->assertCount(1, $seasons);
        $this->assertSame(1989, $seasons[0]['year']);
    }

    public function testPlayoffBracketGroupedByRound(): void
    {
        $this->mockRepository->method('getAwardsByYear')->willReturn([
            ['year' => 1989, 'Award' => 'Most Valuable Player (1st)', 'name' => 'Test MVP', 'table_ID' => 1],
        ]);
        $this->mockRepository->method('getPlayoffResultsByYear')->willReturn([
            ['year' => 1989, 'round' => 1, 'winner' => 'Raptors', 'loser' => 'Pelicans', 'loser_games' => 0, 'id' => 1],
            ['year' => 1989, 'round' => 1, 'winner' => 'Heat', 'loser' => 'Sting', 'loser_games' => 0, 'id' => 3],
            ['year' => 1989, 'round' => 2, 'winner' => 'Raptors', 'loser' => 'Nets', 'loser_games' => 3, 'id' => 9],
            ['year' => 1989, 'round' => 4, 'winner' => 'Clippers', 'loser' => 'Raptors', 'loser_games' => 3, 'id' => 15],
        ]);
        $this->mockRepository->method('getTeamAwardsByYear')->willReturn([]);
        $this->mockRepository->method('getAllGmAwardsWithTeams')->willReturn([]);
        $this->mockRepository->method('getAllGmTenuresWithTeams')->willReturn([]);
        $this->mockRepository->method('getHeatWinLossByYear')->willReturn([]);
        $this->mockRepository->method('getTeamColors')->willReturn([]);
        $this->mockRepository->method('getPlayerIdsByNames')->willReturn([]);

        $result = $this->service->getSeasonDetail(1989);
        $this->assertNotNull($result);

        $bracket = $result['playoffBracket'];
        $this->assertArrayHasKey(1, $bracket);
        $this->assertArrayHasKey(2, $bracket);
        $this->assertArrayHasKey(4, $bracket);
        $this->assertCount(2, $bracket[1]);
        $this->assertCount(1, $bracket[2]);
        $this->assertCount(1, $bracket[4]);
        $this->assertSame('Raptors', $bracket[1][0]['winner']);
        $this->assertSame('Clippers', $bracket[4][0]['winner']);
    }

    public function testAllStarRostersExtracted(): void
    {
        $this->mockRepository->method('getAwardsByYear')->willReturn([
            ['year' => 1989, 'Award' => 'Most Valuable Player (1st)', 'name' => 'Test MVP', 'table_ID' => 1],
            ['year' => 1989, 'Award' => 'Eastern Conference All-Star', 'name' => 'Player East 1', 'table_ID' => 2],
            ['year' => 1989, 'Award' => 'Eastern Conference All-Star', 'name' => 'Player East 2', 'table_ID' => 3],
            ['year' => 1989, 'Award' => 'Western Conference All-Star', 'name' => 'Player West 1', 'table_ID' => 4],
        ]);
        $this->mockRepository->method('getPlayoffResultsByYear')->willReturn([]);
        $this->mockRepository->method('getTeamAwardsByYear')->willReturn([]);
        $this->mockRepository->method('getAllGmAwardsWithTeams')->willReturn([]);
        $this->mockRepository->method('getAllGmTenuresWithTeams')->willReturn([]);
        $this->mockRepository->method('getHeatWinLossByYear')->willReturn([]);
        $this->mockRepository->method('getTeamColors')->willReturn([]);
        $this->mockRepository->method('getPlayerIdsByNames')->willReturn([]);

        $result = $this->service->getSeasonDetail(1989);
        $this->assertNotNull($result);
        $this->assertCount(2, $result['allStarRosters']['east']);
        $this->assertCount(1, $result['allStarRosters']['west']);
        $this->assertSame('Player East 1', $result['allStarRosters']['east'][0]);
    }

    public function testPlayerIdsCollectedFromAllSources(): void
    {
        $this->mockRepository->method('getAwardsByYear')->willReturn([
            ['year' => 1989, 'Award' => 'Most Valuable Player (1st)', 'name' => 'MVP Player', 'table_ID' => 1],
            ['year' => 1989, 'Award' => 'All-League First Team', 'name' => 'Team Player', 'table_ID' => 2],
            ['year' => 1989, 'Award' => 'IBL Champion', 'name' => 'Roster Player', 'table_ID' => 3],
        ]);
        $this->mockRepository->method('getPlayoffResultsByYear')->willReturn([]);
        $this->mockRepository->method('getTeamAwardsByYear')->willReturn([]);
        $this->mockRepository->method('getAllGmAwardsWithTeams')->willReturn([]);
        $this->mockRepository->method('getAllGmTenuresWithTeams')->willReturn([]);
        $this->mockRepository->method('getHeatWinLossByYear')->willReturn([]);
        $this->mockRepository->method('getTeamColors')->willReturn([]);

        // Verify that getPlayerIdsByNames is called with all collected names
        $this->mockRepository->expects($this->once())
            ->method('getPlayerIdsByNames')
            ->with($this->callback(static function (array $names): bool {
                return in_array('MVP Player', $names, true)
                    && in_array('Team Player', $names, true)
                    && in_array('Roster Player', $names, true);
            }))
            ->willReturn(['MVP Player' => 1, 'Team Player' => 2, 'Roster Player' => 3]);

        $result = $this->service->getSeasonDetail(1989);
        $this->assertNotNull($result);
        $this->assertSame(1, $result['playerIds']['MVP Player']);
    }

    public function testAllStarCoachesIncludedInSeasonDetail(): void
    {
        $mockRepo = $this->createMock(SeasonArchiveRepositoryInterface::class);
        $mockRepo->method('getTeamConferences')->willReturn([
            'Bulls' => 'Eastern',
            'Clippers' => 'Western',
        ]);
        $mockRepo->method('getAwardsByYear')->willReturn([
            ['year' => 1990, 'Award' => 'Most Valuable Player (1st)', 'name' => 'Test MVP', 'table_ID' => 1],
        ]);
        $mockRepo->method('getPlayoffResultsByYear')->willReturn([]);
        $mockRepo->method('getTeamAwardsByYear')->willReturn([]);
        $mockRepo->method('getAllGmAwardsWithTeams')->willReturn([
            ['year' => 1990, 'Award' => 'ASG Head Coach', 'gm_username' => 'Ross Gates', 'team_name' => 'Bulls', 'table_ID' => 1],
            ['year' => 1990, 'Award' => 'ASG Head Coach', 'gm_username' => 'Brandon Tomyoy', 'team_name' => 'Clippers', 'table_ID' => 2],
        ]);
        $mockRepo->method('getAllGmTenuresWithTeams')->willReturn([]);
        $mockRepo->method('getHeatWinLossByYear')->willReturn([]);
        $mockRepo->method('getTeamColors')->willReturn([]);
        $mockRepo->method('getPlayerIdsByNames')->willReturn([]);

        $service = new SeasonArchiveService($mockRepo);
        $result = $service->getSeasonDetail(1990);
        $this->assertNotNull($result);
        $this->assertArrayHasKey('allStarCoaches', $result);
        $this->assertSame(['Ross Gates'], $result['allStarCoaches']['east']);
        $this->assertSame(['Brandon Tomyoy'], $result['allStarCoaches']['west']);
    }

    public function testAllStarCoachesHandlesCoHeadCoach(): void
    {
        $mockRepo = $this->createMock(SeasonArchiveRepositoryInterface::class);
        $mockRepo->method('getTeamConferences')->willReturn([
            'Grizzlies' => 'Western',
            'Sting' => 'Eastern',
        ]);
        $mockRepo->method('getAwardsByYear')->willReturn([
            ['year' => 2003, 'Award' => 'Most Valuable Player (1st)', 'name' => 'Test MVP', 'table_ID' => 1],
        ]);
        $mockRepo->method('getPlayoffResultsByYear')->willReturn([]);
        $mockRepo->method('getTeamAwardsByYear')->willReturn([]);
        $mockRepo->method('getAllGmAwardsWithTeams')->willReturn([
            ['year' => 2003, 'Award' => 'ASG Co-Head Coach', 'gm_username' => 'RJ Lilley', 'team_name' => 'Grizzlies', 'table_ID' => 1],
            ['year' => 2003, 'Award' => 'ASG Head Coach', 'gm_username' => 'Mel Baltazar', 'team_name' => 'Sting', 'table_ID' => 2],
        ]);
        $mockRepo->method('getAllGmTenuresWithTeams')->willReturn([]);
        $mockRepo->method('getHeatWinLossByYear')->willReturn([]);
        $mockRepo->method('getTeamColors')->willReturn([]);
        $mockRepo->method('getPlayerIdsByNames')->willReturn([]);

        $service = new SeasonArchiveService($mockRepo);
        $result = $service->getSeasonDetail(2003);
        $this->assertNotNull($result);
        // Mel Baltazar is head coach of Sting (Eastern), RJ Lilley is co-head coach of Grizzlies (Western)
        $this->assertSame(['Mel Baltazar'], $result['allStarCoaches']['east']);
        $this->assertSame(['RJ Lilley'], $result['allStarCoaches']['west']);
    }

    public function testAllStarCoachesEmptyWhenNoCoachesForYear(): void
    {
        $mockRepo = $this->createMock(SeasonArchiveRepositoryInterface::class);
        $mockRepo->method('getTeamConferences')->willReturn([]);
        $mockRepo->method('getAwardsByYear')->willReturn([
            ['year' => 1989, 'Award' => 'Most Valuable Player (1st)', 'name' => 'Test MVP', 'table_ID' => 1],
        ]);
        $mockRepo->method('getPlayoffResultsByYear')->willReturn([]);
        $mockRepo->method('getTeamAwardsByYear')->willReturn([]);
        $mockRepo->method('getAllGmAwardsWithTeams')->willReturn([]);
        $mockRepo->method('getAllGmTenuresWithTeams')->willReturn([]);
        $mockRepo->method('getHeatWinLossByYear')->willReturn([]);
        $mockRepo->method('getTeamColors')->willReturn([]);
        $mockRepo->method('getPlayerIdsByNames')->willReturn([]);

        $service = new SeasonArchiveService($mockRepo);
        $result = $service->getSeasonDetail(1989);
        $this->assertNotNull($result);
        $this->assertSame([], $result['allStarCoaches']['east']);
        $this->assertSame([], $result['allStarCoaches']['west']);
    }

    public function testIblChampionCoachFoundByTeamAndTenure(): void
    {
        $mockRepo = $this->createMock(SeasonArchiveRepositoryInterface::class);
        $mockRepo->method('getTeamConferences')->willReturn([]);
        $mockRepo->method('getAwardsByYear')->willReturn([
            ['year' => 1989, 'Award' => 'Most Valuable Player (1st)', 'name' => 'Test MVP', 'table_ID' => 1],
        ]);
        $mockRepo->method('getPlayoffResultsByYear')->willReturn([
            ['year' => 1989, 'round' => 4, 'winner' => 'Clippers', 'loser' => 'Raptors', 'loser_games' => 3, 'id' => 15],
        ]);
        $mockRepo->method('getTeamAwardsByYear')->willReturn([]);
        $mockRepo->method('getAllGmAwardsWithTeams')->willReturn([]);
        $mockRepo->method('getAllGmTenuresWithTeams')->willReturn([
            ['gm_username' => 'Brandon Tomyoy', 'start_season_year' => 1988, 'end_season_year' => null, 'team_name' => 'Clippers'],
            ['gm_username' => 'Ross Gates', 'start_season_year' => 1988, 'end_season_year' => null, 'team_name' => 'Bulls'],
        ]);
        $mockRepo->method('getHeatWinLossByYear')->willReturn([]);
        $mockRepo->method('getTeamColors')->willReturn([]);
        $mockRepo->method('getPlayerIdsByNames')->willReturn([]);

        $service = new SeasonArchiveService($mockRepo);
        $result = $service->getSeasonDetail(1989);
        $this->assertNotNull($result);
        $this->assertSame('Brandon Tomyoy', $result['iblChampionCoach']);
    }

    public function testIblChampionCoachRespectsYearTenure(): void
    {
        $mockRepo = $this->createMock(SeasonArchiveRepositoryInterface::class);
        $mockRepo->method('getTeamConferences')->willReturn([]);
        $mockRepo->method('getAwardsByYear')->willReturn([
            ['year' => 2000, 'Award' => 'Most Valuable Player (1st)', 'name' => 'Test MVP', 'table_ID' => 1],
        ]);
        $mockRepo->method('getPlayoffResultsByYear')->willReturn([
            ['year' => 2000, 'round' => 4, 'winner' => 'Lakers', 'loser' => 'Raptors', 'loser_games' => 2, 'id' => 15],
        ]);
        $mockRepo->method('getTeamAwardsByYear')->willReturn([]);
        $mockRepo->method('getAllGmAwardsWithTeams')->willReturn([]);
        $mockRepo->method('getAllGmTenuresWithTeams')->willReturn([
            ['gm_username' => 'Tony (Tek)', 'start_season_year' => 1988, 'end_season_year' => 1999, 'team_name' => 'Lakers'],
            ['gm_username' => 'Andre Ivarsson', 'start_season_year' => 1999, 'end_season_year' => null, 'team_name' => 'Lakers'],
        ]);
        $mockRepo->method('getHeatWinLossByYear')->willReturn([]);
        $mockRepo->method('getTeamColors')->willReturn([]);
        $mockRepo->method('getPlayerIdsByNames')->willReturn([]);

        $service = new SeasonArchiveService($mockRepo);
        $result = $service->getSeasonDetail(2000);
        $this->assertNotNull($result);
        // Year 2000 falls in "1999-Present" tenure, so Andre Ivarsson is the coach
        $this->assertSame('Andre Ivarsson', $result['iblChampionCoach']);
    }

    public function testIblChampionCoachEmptyWhenNoChampion(): void
    {
        $mockRepo = $this->createMock(SeasonArchiveRepositoryInterface::class);
        $mockRepo->method('getTeamConferences')->willReturn([]);
        $mockRepo->method('getAwardsByYear')->willReturn([
            ['year' => 1989, 'Award' => 'Most Valuable Player (1st)', 'name' => 'Test MVP', 'table_ID' => 1],
        ]);
        $mockRepo->method('getPlayoffResultsByYear')->willReturn([]);
        $mockRepo->method('getTeamAwardsByYear')->willReturn([]);
        $mockRepo->method('getAllGmAwardsWithTeams')->willReturn([]);
        $mockRepo->method('getAllGmTenuresWithTeams')->willReturn([]);
        $mockRepo->method('getHeatWinLossByYear')->willReturn([]);
        $mockRepo->method('getTeamColors')->willReturn([]);
        $mockRepo->method('getPlayerIdsByNames')->willReturn([]);

        $service = new SeasonArchiveService($mockRepo);
        $result = $service->getSeasonDetail(1989);
        $this->assertNotNull($result);
        $this->assertSame('', $result['iblChampionCoach']);
    }
}
