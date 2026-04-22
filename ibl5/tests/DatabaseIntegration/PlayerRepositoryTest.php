<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Player\PlayerRepository;

/**
 * Tests PlayerRepository against real MariaDB — JOINs, PlayerData hydration, native types.
 */
class PlayerRepositoryTest extends DatabaseTestCase
{
    private PlayerRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new PlayerRepository($this->db);
    }

    public function testLoadByIdReturnsPlayerData(): void
    {
        $this->insertTestPlayer(200010002, 'PLR LoadTest', [
            'age' => 25,
            'pos' => 'SF',
            'teamid' => 1,
        ]);

        $player = $this->repo->loadByID(200010002);

        self::assertSame(200010002, $player->playerID);
        self::assertSame('PLR LoadTest', $player->name);
        self::assertSame(1, $player->teamid);
        self::assertSame('Metros', $player->teamName);
        self::assertSame('SF', $player->position);
        self::assertSame(25, $player->age);
    }

    public function testLoadByIdThrowsForUnknownPlayer(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Player with ID 99999 not found');

        $this->repo->loadByID(99999);
    }

    public function testGetPlayerStatsReturnsRowWithNativeTypes(): void
    {
        $this->insertTestPlayer(200010003, 'PLR StatsTest', [
            'teamid' => 2,
            'pos' => 'SG',
        ]);

        $row = $this->repo->getPlayerStats(200010003);

        self::assertNotNull($row);
        self::assertSame(200010003, $row['pid']);
        self::assertSame(2, $row['teamid']);
        self::assertSame('SG', $row['pos']);
    }

    public function testGetPlayerStatsReturnsNullForUnknownPlayer(): void
    {
        $row = $this->repo->getPlayerStats(99999);

        self::assertNull($row);
    }

    public function testGetFreeAgencyDemandsReturnsZeroesWhenNoDemandRow(): void
    {
        $this->insertTestPlayer(200010004, 'PLR DemandTst');

        $demands = $this->repo->getFreeAgencyDemands(200010004);

        self::assertSame(0, $demands['dem1']);
        self::assertSame(0, $demands['dem2']);
        self::assertSame(0, $demands['dem3']);
        self::assertSame(0, $demands['dem4']);
        self::assertSame(0, $demands['dem5']);
        self::assertSame(0, $demands['dem6']);
    }

    public function testGetAwardsReturnsEmptyWhenNoAwards(): void
    {
        $this->insertTestPlayer(200010005, 'PLR NoAwards');

        $awards = $this->repo->getAwards('PLR NoAwards');

        self::assertSame([], $awards);
    }

    public function testGetAwardsReturnsRowAfterInsert(): void
    {
        $this->insertRow('ibl_awards', [
            'year' => 2025,
            'Award' => 'MVP',
            'name' => 'PLR AwardTest',
        ]);

        $awards = $this->repo->getAwards('PLR AwardTest');

        self::assertCount(1, $awards);
        self::assertSame('MVP', $awards[0]['Award']);
        self::assertSame('PLR AwardTest', $awards[0]['name']);
    }

    // ── All-Star Weekend counts ─────────────────────────────────

    public function testGetAllStarGameCountReturnsZeroForNoAwards(): void
    {
        self::assertSame(0, $this->repo->getAllStarGameCount('PLR NoAllStar'));
    }

    public function testGetAllStarGameCountMatchesConferenceAllStar(): void
    {
        $this->insertAwardRow('PLR AllStarCt', 'Eastern Conference All-Star', 2024);
        $this->insertAwardRow('PLR AllStarCt', 'Western Conference All-Star', 2025);
        $this->insertAwardRow('PLR AllStarCt', 'MVP', 2024);

        self::assertSame(2, $this->repo->getAllStarGameCount('PLR AllStarCt'));
    }

    public function testGetThreePointContestCountMatches(): void
    {
        $this->insertAwardRow('PLR 3ptContest', 'Three-Point Contest Winner', 2024);
        $this->insertAwardRow('PLR 3ptContest', 'Three-Point Contest Runner-Up', 2025);

        self::assertSame(2, $this->repo->getThreePointContestCount('PLR 3ptContest'));
    }

    public function testGetDunkContestCountMatches(): void
    {
        $this->insertAwardRow('PLR DunkContest', 'Slam Dunk Competition Winner', 2024);

        self::assertSame(1, $this->repo->getDunkContestCount('PLR DunkContest'));
    }

    public function testGetRookieSophChallengeCountMatches(): void
    {
        $this->insertAwardRow('PLR RookSoph', 'Rookie-Sophomore Challenge', 2024);
        $this->insertAwardRow('PLR RookSoph', 'Rookie-Sophomore Challenge', 2025);

        self::assertSame(2, $this->repo->getRookieSophChallengeCount('PLR RookSoph'));
    }

    public function testAllStarWeekendCountsCachedAcrossCalls(): void
    {
        $this->insertAwardRow('PLR CacheTest', 'Eastern Conference All-Star', 2024);
        $this->insertAwardRow('PLR CacheTest', 'Three-Point Contest Winner', 2024);

        self::assertSame(1, $this->repo->getAllStarGameCount('PLR CacheTest'));
        self::assertSame(1, $this->repo->getThreePointContestCount('PLR CacheTest'));
    }

    // ── Historical stats ────────────────────────────────────────

    public function testGetHistoricalStatsReturnsOrderedByYear(): void
    {
        $this->insertTestPlayer(200010010, 'PLR HistStats');
        $this->insertHistRow(200010010, 'PLR HistStats', 2025, ['teamid' => 1]);
        $this->insertHistRow(200010010, 'PLR HistStats', 2023, ['teamid' => 1]);
        $this->insertHistRow(200010010, 'PLR HistStats', 2024, ['teamid' => 1]);

        $stats = $this->repo->getHistoricalStats(200010010);

        self::assertCount(3, $stats);
        self::assertSame(2023, $stats[0]['year']);
        self::assertSame(2024, $stats[1]['year']);
        self::assertSame(2025, $stats[2]['year']);
    }

    public function testGetHistoricalStatsReturnsEmptyForNoHistory(): void
    {
        self::assertSame([], $this->repo->getHistoricalStats(999999999));
    }

    // ── Playoff / Heat / Olympics stats ─────────────────────────

    public function testGetPlayoffStatsReturnsEmptyForNoData(): void
    {
        self::assertSame([], $this->repo->getPlayoffStats('PLR NoPlayoffs'));
    }

    public function testGetHeatStatsReturnsEmptyForNoData(): void
    {
        self::assertSame([], $this->repo->getHeatStats('PLR NoHeat'));
    }

    public function testGetOlympicsStatsReturnsEmptyForNoData(): void
    {
        self::assertSame([], $this->repo->getOlympicsStats(999999999));
    }

    // ── getAllSimDates ───────────────────────────────────────────

    public function testGetAllSimDatesReturnsArray(): void
    {
        $dates = $this->repo->getAllSimDates();

        self::assertIsArray($dates);
        if ($dates !== []) {
            self::assertArrayHasKey('Sim', $dates[0]);
        }
    }

    // ── One-on-One wins/losses ──────────────────────────────────

    public function testGetOneOnOneWinsReturnsWinsWithLoserPid(): void
    {
        $this->insertTestPlayer(200010011, 'PLR WinTest', ['teamid' => 1]);
        $this->insertTestPlayer(200010012, 'PLR LoseTest', ['teamid' => 2]);

        $this->insertRow('ibl_one_on_one', [
            'gameid' => 900001,
            'playbyplay' => 'test',
            'winner' => 'PLR WinTest',
            'loser' => 'PLR LoseTest',
            'winscore' => 21,
            'lossscore' => 15,
            'owner' => 'testgm',
        ]);

        $wins = $this->repo->getOneOnOneWins('PLR WinTest');

        self::assertCount(1, $wins);
        self::assertSame('PLR WinTest', $wins[0]['winner']);
        self::assertSame('PLR LoseTest', $wins[0]['loser']);
        self::assertSame(21, $wins[0]['winscore']);
        self::assertSame(200010012, $wins[0]['loser_pid']);
    }

    public function testGetOneOnOneLossesReturnsLossesWithWinnerPid(): void
    {
        $this->insertTestPlayer(200010013, 'PLR Winner2', ['teamid' => 1]);
        $this->insertTestPlayer(200010014, 'PLR Loser2', ['teamid' => 2]);

        $this->insertRow('ibl_one_on_one', [
            'gameid' => 900002,
            'playbyplay' => 'test',
            'winner' => 'PLR Winner2',
            'loser' => 'PLR Loser2',
            'winscore' => 21,
            'lossscore' => 18,
            'owner' => 'testgm',
        ]);

        $losses = $this->repo->getOneOnOneLosses('PLR Loser2');

        self::assertCount(1, $losses);
        self::assertSame('PLR Winner2', $losses[0]['winner']);
        self::assertSame(200010013, $losses[0]['winner_pid']);
    }

    public function testGetOneOnOneWinsReturnsEmptyForNoWins(): void
    {
        self::assertSame([], $this->repo->getOneOnOneWins('PLR NoWins'));
    }

    // ── getPlayerIdByUuid ───────────────────────────────────────

    public function testGetPlayerIdByUuidReturnsPid(): void
    {
        $this->insertTestPlayer(200010015, 'PLR UuidTest', [
            'uuid' => 'b8-plr-uuid-test-015',
        ]);

        self::assertSame(200010015, $this->repo->getPlayerIdByUuid('b8-plr-uuid-test-015'));
    }

    public function testGetPlayerIdByUuidReturnsNullForUnknown(): void
    {
        self::assertNull($this->repo->getPlayerIdByUuid('nonexistent-uuid'));
    }

    // ── getPlayerNews ───────────────────────────────────────────

    public function testGetPlayerNewsReturnsEmptyForNoMentions(): void
    {
        self::assertSame([], $this->repo->getPlayerNews('PLR NoNewsAtAll'));
    }

    // ── getFreeAgencyDemands with data ──────────────────────────

    public function testGetFreeAgencyDemandsReturnsDemandValues(): void
    {
        $this->insertTestPlayer(200010016, 'PLR DemandVal');
        $this->insertDemandRow('PLR DemandVal', 200010016, [
            'dem1' => 2000,
            'dem2' => 2200,
            'dem3' => 0,
        ]);

        $demands = $this->repo->getFreeAgencyDemands(200010016);

        self::assertSame(2000, $demands['dem1']);
        self::assertSame(2200, $demands['dem2']);
        self::assertSame(0, $demands['dem3']);
    }
}
