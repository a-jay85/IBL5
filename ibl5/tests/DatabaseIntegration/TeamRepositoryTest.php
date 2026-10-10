<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

use League\League;
use Team\TeamRepository;

#[Group('database')]
class TeamRepositoryTest extends DatabaseTestCase
{
    private TeamRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new TeamRepository($this->db);
    }

    public function testGetTeamReturnsRowForKnownTeam(): void
    {
        // Team 1 exists in production data — just verify structure
        $team = $this->repo->getTeam(1);

        self::assertNotNull($team);
        self::assertSame(1, $team['teamid']);
        self::assertArrayHasKey('team_name', $team);
        self::assertArrayHasKey('team_city', $team);
        self::assertArrayHasKey('color1', $team);
    }

    public function testGetTeamReturnsNullForUnknown(): void
    {
        $team = $this->repo->getTeam(99999);

        self::assertNull($team);
    }

    public function testGetTeamPowerDataReturnsJoinedRow(): void
    {
        $this->ensureStandingsAndPowerExist(1, 'Atlantic', 'Eastern');

        // Get team name for teamid=1
        $stmt = $this->db->prepare("SELECT team_name FROM ibl_team_info WHERE teamid = 1");
        self::assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        self::assertNotNull($row);

        /** @var string $teamName */
        $teamName = $row['team_name'];

        $power = $this->repo->getTeamPowerData($teamName);

        self::assertNotNull($power);
        self::assertArrayHasKey('teamid', $power);
        self::assertArrayHasKey('team_name', $power);
        self::assertArrayHasKey('wins', $power);
        self::assertArrayHasKey('losses', $power);
        self::assertArrayHasKey('conference', $power);
        self::assertArrayHasKey('division', $power);
        self::assertArrayHasKey('ranking', $power);
        self::assertArrayHasKey('streak_type', $power);
        self::assertArrayHasKey('sos', $power);
    }

    public function testGetTeamPowerDataReturnsNullForUnknown(): void
    {
        $power = $this->repo->getTeamPowerData('Nonexistent Team');

        self::assertNull($power);
    }

    public function testGetDivisionStandingsReturnsTeamsInDivision(): void
    {
        // Ensure standings + power data exists for a test division
        $this->ensureStandingsAndPowerExist(1, 'Atlantic', 'Eastern');

        $standings = $this->repo->getDivisionStandings('Atlantic');

        self::assertNotEmpty($standings);
        foreach ($standings as $r) {
            self::assertSame('Atlantic', $r['division']);
            self::assertArrayHasKey('ranking', $r);
        }
    }

    public function testGetConferenceStandingsReturnsTeamsInConference(): void
    {
        $this->ensureStandingsAndPowerExist(1, 'Atlantic', 'Eastern');

        $standings = $this->repo->getConferenceStandings('Eastern');

        self::assertNotEmpty($standings);
        foreach ($standings as $r) {
            self::assertSame('Eastern', $r['conference']);
        }
    }

    public function testGetChampionshipBannersReturnsRows(): void
    {
        // Insert a test banner within the transaction
        $this->insertRow('ibl_banners', [
            'year' => 2099,
            'currentname' => 'TestBannerTeam',
            'bannername' => 'TestBannerTeam',
            'bannertype' => 1,
        ]);

        $banners = $this->repo->getChampionshipBanners('TestBannerTeam');

        self::assertNotEmpty($banners);
        self::assertSame(2099, $banners[0]['year']);
        self::assertSame('TestBannerTeam', $banners[0]['currentname']);
    }

    public function testGetChampionshipBannersReturnsEmptyForUnknown(): void
    {
        $banners = $this->repo->getChampionshipBanners('ZZZ_Nonexistent_Team');

        self::assertSame([], $banners);
    }

    public function testGetGMTenuresReturnsRows(): void
    {
        // Insert a test tenure within the transaction
        $this->insertRow('ibl_gm_tenures', [
            'franchise_id' => 1,
            'gm_display_name' => 'test_tenure_gm',
            'start_season_year' => 2098,
            'end_season_year' => 2099,
            'is_mid_season_start' => 0,
            'is_mid_season_end' => 0,
        ]);

        $tenures = $this->repo->getGMTenures(1);

        self::assertNotEmpty($tenures);
        // Find our inserted tenure
        $found = false;
        foreach ($tenures as $tenure) {
            if ($tenure['gm_display_name'] === 'test_tenure_gm') {
                self::assertSame(2098, $tenure['start_season_year']);
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Inserted GM tenure not found');
    }

    public function testGetRegularSeasonHistoryReadsFromMaterializedTable(): void
    {
        // ibl_team_season_records is the materialized table refreshed by
        // RefreshTeamSeasonRecordsStep. Insert directly to verify the lookup.
        $stmt = $this->db->prepare("SELECT team_name FROM ibl_team_info WHERE teamid = 1");
        self::assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        self::assertNotNull($row);

        /** @var string $teamName */
        $teamName = $row['team_name'];

        $this->insertTeamSeasonRecordRow(1, 9098, 1, $teamName, $teamName, 50, 32);

        $history = $this->repo->getRegularSeasonHistory($teamName);

        self::assertNotEmpty($history);
        $found = null;
        foreach ($history as $r) {
            if ($r['year'] === 9098) {
                $found = $r;
                break;
            }
        }
        self::assertNotNull($found, 'Expected 9098 row in history');
        self::assertSame(50, $found['wins']);
        self::assertSame(32, $found['losses']);
    }

    public function testGetRosterUnderContractReturnsContractedPlayers(): void
    {
        // Team 1 should have players in production data
        $roster = $this->repo->getRosterUnderContract(1);

        self::assertNotEmpty($roster);
        foreach ($roster as $player) {
            self::assertSame(1, $player['teamid']);
            self::assertSame(0, $player['retired']);
        }
    }

    public function testGetFreeAgentsReturnsPlayersWithHighOrdinal(): void
    {
        // Insert a free agent player with high ordinal in the transaction
        $this->insertRow('ibl_plr', [
            'pid' => 99990,
            'name' => 'Test Free Agent',
            'age' => 25,
            'teamid' => 0,
            'pos' => 'SG',
            'stamina' => 70,
            'exp' => 2,
            'bird' => 0,
            'cy' => 0,
            'cyt' => 0,
            'salary_yr1' => 0,
            'salary_yr2' => 0,
            'retired' => 0,
            'ordinal' => 1000,
            'droptime' => 0,
            'uuid' => '99990000-0000-0000-0000-000000000000',
        ]);

        $freeAgents = $this->repo->getFreeAgents();

        self::assertNotEmpty($freeAgents);
        foreach ($freeAgents as $player) {
            self::assertGreaterThan(959, $player['ordinal']);
            self::assertSame(0, $player['retired']);
        }
    }

    public function testGetAllTeamsReturnsOnlyRealTeams(): void
    {
        $teams = $this->repo->getAllTeams();

        self::assertCount(28, $teams);
        foreach ($teams as $team) {
            self::assertGreaterThanOrEqual(1, $team['teamid']);
            self::assertLessThanOrEqual(League::MAX_REAL_TEAMID, $team['teamid']);
        }
    }

    public function testGetFranchiseSeasonsReturnsRows(): void
    {
        $seasons = $this->repo->getFranchiseSeasons(1);

        // Production DB should have franchise season data
        self::assertNotEmpty($seasons);
        self::assertSame(1, $seasons[0]['franchise_id']);
        self::assertArrayHasKey('season_year', $seasons[0]);
        self::assertArrayHasKey('team_city', $seasons[0]);
        self::assertArrayHasKey('team_name', $seasons[0]);
    }

    public function testGetHistoricalRosterReturnsRows(): void
    {
        // Insert a test snapshot row (visible via ibl_hist VIEW)
        $this->insertHistRow(1, 'Test Hist Player', 2098);

        $roster = $this->repo->getHistoricalRoster(1, '2098');

        self::assertNotEmpty($roster);
        self::assertSame(1, $roster[0]['teamid']);
    }

    // ── getGMAwards ───────────────────────────────────────────

    public function testGetGMAwardsReturnsRowsForKnownGM(): void
    {
        $this->insertRow('ibl_gm_awards', [
            'name' => 'b9_test_gm',
            'award' => 'GM of the Year',
            'year' => 2099,
        ]);

        $result = $this->repo->getGMAwards('b9_test_gm');

        self::assertCount(1, $result);
        self::assertSame('b9_test_gm', $result[0]['name']);
        self::assertSame('GM of the Year', $result[0]['award']);
        self::assertSame(2099, $result[0]['year']);
    }

    public function testGetGMAwardsReturnsEmptyForUnknownGM(): void
    {
        self::assertSame([], $this->repo->getGMAwards('zz_no_such_gm_xyz'));
    }

    // ── getTeamAccomplishments ──────────────────────────────────

    public function testGetTeamAccomplishmentsReturnsTeamAwardRows(): void
    {
        $this->insertTeamAwardRow('B9TestTeam', 'Atlantic Division Title', 2099);

        $result = $this->repo->getTeamAccomplishments('B9TestTeam');

        self::assertNotEmpty($result);
        self::assertSame('Atlantic Division Title', $result[0]['award']);
    }

    public function testGetTeamAccomplishmentsReturnsEmptyForUnknownTeam(): void
    {
        self::assertSame([], $this->repo->getTeamAccomplishments('ZZ_Nonexistent_Batch9'));
    }

    public function testGetTeamAccomplishmentsSortsHierarchicallyWithinYear(): void
    {
        // Insert out of hierarchy order to prove the query sorts, not the fixture.
        $this->insertTeamAwardRow('B9TestTeam', 'IBL Draft Lottery Winners', 2097);
        $this->insertTeamAwardRow('B9TestTeam', 'Pacific Division Champions', 2097);
        $this->insertTeamAwardRow('B9TestTeam', 'Atlantic Division Champions', 2097);
        $this->insertTeamAwardRow('B9TestTeam', 'Western Conference Champions', 2097);
        $this->insertTeamAwardRow('B9TestTeam', 'Eastern Conference Champions', 2097);

        $result = $this->repo->getTeamAccomplishments('B9TestTeam');

        $awards = [];
        foreach ($result as $row) {
            if ((int) $row['year'] === 2097) {
                $awards[] = $row['award'];
            }
        }

        self::assertSame([
            'Eastern Conference Champions',
            'Western Conference Champions',
            'Atlantic Division Champions',
            'Pacific Division Champions',
            'IBL Draft Lottery Winners',
        ], $awards);
    }

    public function testGetTeamAccomplishmentsSortsYearDescendingFirst(): void
    {
        $this->insertTeamAwardRow('B9TestTeam', 'Atlantic Division Champions', 2095);
        $this->insertTeamAwardRow('B9TestTeam', 'IBL Draft Lottery Winners', 2096);

        $result = $this->repo->getTeamAccomplishments('B9TestTeam');

        $years = [];
        foreach ($result as $row) {
            $year = (int) $row['year'];
            if ($year === 2095 || $year === 2096) {
                $years[] = $year;
            }
        }

        // 2096 rows must come before 2095 rows regardless of hierarchy tier.
        self::assertSame([2096, 2095], $years);
    }

    // ── getHEATHistory ──────────────────────────────────────────

    public function testGetHEATHistoryReturnsArrayWithExpectedShape(): void
    {
        // ibl_team_season_records (game_type=3) is the materialized HEAT table.
        $this->insertTeamSeasonRecordRow(1, 9098, 3, 'Metros', 'Metros', 4, 2);

        $result = $this->repo->getHEATHistory('Metros');

        self::assertNotEmpty($result);
        $found = null;
        foreach ($result as $r) {
            if ($r['year'] === 9098) {
                $found = $r;
                break;
            }
        }
        self::assertNotNull($found, 'Expected 9098 HEAT row');
        self::assertSame(4, $found['wins']);
        self::assertSame(2, $found['losses']);
    }

    // ── getPlayoffResults ───────────────────────────────────────

    public function testGetPlayoffResultsReturnsPlayoffSeriesData(): void
    {
        // Insert directly into the materialized table (vw_playoff_series_results
        // is now a thin pass-through; RefreshPlayoffSeriesResultsStep populates
        // the table on every pipeline run).
        $this->insertPlayoffSeriesResultRow(9099, 1, 1, 2, 'Metros', 'Sharks', 3, 1);
        $this->insertFranchiseSeasonRow(1, 9099, 'Metros');
        $this->insertFranchiseSeasonRow(2, 9099, 'Sharks');

        $result = $this->repo->getPlayoffResults('Metros');

        $series = array_values(array_filter(
            $result,
            static fn (array $r): bool => $r['year'] === 9099,
        ));

        self::assertNotEmpty($series);
        self::assertSame(3, $series[0]['winner_games']);
        self::assertSame(1, $series[0]['loser_games']);
        self::assertSame('Metros', $series[0]['winner']);
    }

    public function testGetPlayoffResultsFiltersByTeamName(): void
    {
        // Insert two series in a far-future year — one involving Metros, one not.
        $this->insertPlayoffSeriesResultRow(9099, 1, 1, 2, 'Metros', 'Sharks', 3, 1);
        $this->insertPlayoffSeriesResultRow(9099, 1, 3, 4, 'Lakers', 'Celtics', 3, 0);

        $result = $this->repo->getPlayoffResults('Metros');

        $series = array_values(array_filter(
            $result,
            static fn (array $r): bool => $r['year'] === 9099,
        ));

        self::assertCount(1, $series);
        self::assertSame('Metros', $series[0]['winner']);
    }

    // ── getFreeAgencyRoster ────────────────────────────────────

    /**
     * SCOPE NOTE (see plan: team-page-expiring-players-faded).
     *
     * The `cyt != cy` exclusion pinned here is deliberately retained. As of the
     * expiring-players-faded change the Team page no longer uses this query --
     * TeamTableService::getTableOutput() calls getRosterUnderContract() in every
     * current-season phase so expiring players stay visible and render faded.
     *
     * getFreeAgencyRoster() is still the roster source for
     * TeamTableService::getRosterAndStarters(), and therefore for both
     * Trading\TradeRosterPreviewApiHandler and
     * DepthChart\DepthChartController. Those two consumers must NOT
     * show expiring players. Removing this filter or this test changes their
     * rendered output.
     *
     * The widened counterpart is covered by
     * testGetRosterUnderContractIncludesExpiringContracts() below.
     */
    public function testGetFreeAgencyRosterExcludesExpiringContracts(): void
    {
        // Non-expiring: cy=1, cyt=3 (cy != cyt → included)
        $this->insertTestPlayer(200100001, 'FA Roster Keep', ['teamid' => 1, 'cy' => 1, 'cyt' => 3]);
        // Expiring: cy=3, cyt=3 (cy == cyt → excluded by SQL `cyt != cy`)
        $this->insertTestPlayer(200100002, 'FA Roster Expire', ['teamid' => 1, 'cy' => 3, 'cyt' => 3]);

        $result = $this->repo->getFreeAgencyRoster(1);

        $names = array_column($result, 'name');
        self::assertContains('FA Roster Keep', $names);
        self::assertNotContains('FA Roster Expire', $names);
    }

    // ── getRosterUnderContract ─────────────────────────────────

    /**
     * The widened counterpart of the exclusion above. getRosterUnderContract() is
     * getFreeAgencyRoster() without the `cyt != cy` filter, which is exactly why the
     * Team page can now use one source in every current-season phase and render the
     * expiring players faded instead of dropping them.
     */
    public function testGetRosterUnderContractIncludesExpiringContracts(): void
    {
        // Non-expiring: cy=1, cyt=3 (cy != cyt)
        $this->insertTestPlayer(200100011, 'UC Roster Keep', ['teamid' => 1, 'cy' => 1, 'cyt' => 3]);
        // Expiring: cy=3, cyt=3 (cy == cyt) — excluded by getFreeAgencyRoster(), kept here
        $this->insertTestPlayer(200100012, 'UC Roster Expire', ['teamid' => 1, 'cy' => 3, 'cyt' => 3]);

        $result = $this->repo->getRosterUnderContract(1);

        $names = array_column($result, 'name');
        self::assertContains('UC Roster Keep', $names);
        self::assertContains('UC Roster Expire', $names);
    }

    /**
     * Boundary: widening for expiring contracts must not also widen for retired
     * players. A retired player has no business on any roster view.
     */
    public function testGetRosterUnderContractStillExcludesRetiredPlayers(): void
    {
        $this->insertTestPlayer(200100013, 'UC Active Guy', ['teamid' => 1, 'retired' => 0]);
        $this->insertTestPlayer(200100014, 'UC Retired Guy', ['teamid' => 1, 'retired' => 1]);
        // Retired AND expiring — the intersection must stay excluded.
        $this->insertTestPlayer(200100015, 'UC Retired Expire', ['teamid' => 1, 'retired' => 1, 'cy' => 3, 'cyt' => 3]);

        $result = $this->repo->getRosterUnderContract(1);

        $names = array_column($result, 'name');
        self::assertContains('UC Active Guy', $names);
        self::assertNotContains('UC Retired Guy', $names);
        self::assertNotContains('UC Retired Expire', $names);
    }

    // ── getEntireLeagueRoster ───────────────────────────────────

    public function testGetEntireLeagueRosterExcludesBuyoutAndRetired(): void
    {
        $this->insertTestPlayer(200100003, 'League Active', ['retired' => 0]);
        $this->insertTestPlayer(200100004, 'League Retired', ['retired' => 1]);
        $this->insertTestPlayer(200100005, 'Cash Buyouts', ['retired' => 0, 'name' => 'Cash Buyouts']);

        $result = $this->repo->getEntireLeagueRoster();

        self::assertNotEmpty($result);
        $names = array_column($result, 'name');
        self::assertContains('League Active', $names);
        self::assertNotContains('League Retired', $names);
        foreach ($names as $name) {
            self::assertStringNotContainsString('Buyouts', $name);
        }
    }

    public function testGetDivisionStandingsBreaksDivGbTieByTeamid(): void
    {
        $this->ensureStandingsAndPowerExist(2, 'B9TieDiv', 'B9TieConf');
        $this->ensureStandingsAndPowerExist(1, 'B9TieDiv', 'B9TieConf');

        self::assertSame([1, 2], array_column($this->repo->getDivisionStandings('B9TieDiv'), 'teamid'));
    }

    public function testGetConferenceStandingsBreaksConfGbTieByTeamid(): void
    {
        $this->ensureStandingsAndPowerExist(2, 'B9TieDiv', 'B9TieConf');
        $this->ensureStandingsAndPowerExist(1, 'B9TieDiv', 'B9TieConf');

        self::assertSame([1, 2], array_column($this->repo->getConferenceStandings('B9TieConf'), 'teamid'));
    }

    public function testGetChampionshipBannersBreaksYearTieById(): void
    {
        $firstId = $this->insertRow('ibl_banners', ['year' => 2099, 'currentname' => 'B9TieBanner', 'bannername' => 'B9TieBanner', 'bannertype' => 1]);
        $secondId = $this->insertRow('ibl_banners', ['year' => 2099, 'currentname' => 'B9TieBanner', 'bannername' => 'B9TieBanner', 'bannertype' => 1]);

        self::assertSame([$firstId, $secondId], array_column($this->repo->getChampionshipBanners('B9TieBanner'), 'id'));
    }

    public function testGetChampionshipBannersYearOrderDominatesIdTiebreaker(): void
    {
        $this->insertRow('ibl_banners', ['year' => 2099, 'currentname' => 'B9TieBanner', 'bannername' => 'B9TieBanner', 'bannertype' => 1]);
        $this->insertRow('ibl_banners', ['year' => 2098, 'currentname' => 'B9TieBanner', 'bannername' => 'B9TieBanner', 'bannertype' => 1]);

        self::assertSame([2098, 2099], array_column($this->repo->getChampionshipBanners('B9TieBanner'), 'year'));
    }

    public function testGetGMTenuresBreaksStartYearTieById(): void
    {
        $zetaId = $this->insertRow('ibl_gm_tenures', [
            'franchise_id' => 1,
            'gm_display_name' => 'b9_tie_gm_zeta',
            'start_season_year' => 2098,
            'end_season_year' => 2099,
            'is_mid_season_start' => 0,
            'is_mid_season_end' => 0,
        ]);
        $alphaId = $this->insertRow('ibl_gm_tenures', [
            'franchise_id' => 1,
            'gm_display_name' => 'b9_tie_gm_alpha',
            'start_season_year' => 2098,
            'end_season_year' => 2099,
            'is_mid_season_start' => 0,
            'is_mid_season_end' => 0,
        ]);

        $rows = array_values(array_filter(
            $this->repo->getGMTenures(1),
            static fn (array $r): bool => str_starts_with((string) $r['gm_display_name'], 'b9_tie_gm_')
        ));

        self::assertSame([$zetaId, $alphaId], array_column($rows, 'id'));
    }

    public function testGetRosterUnderContractBreaksNameTieByPid(): void
    {
        $this->insertTestPlayer(200100022, 'B9 Tie Twin', ['teamid' => 1, 'retired' => 0]);
        $this->insertTestPlayer(200100021, 'B9 Tie Twin', ['teamid' => 1, 'retired' => 0]);

        $rows = array_values(array_filter(
            $this->repo->getRosterUnderContract(1),
            static fn (array $r): bool => $r['name'] === 'B9 Tie Twin'
        ));

        self::assertSame([200100021, 200100022], array_column($rows, 'pid'));
    }

    public function testGetFreeAgencyRosterBreaksNameTieByPid(): void
    {
        $this->insertTestPlayer(200100024, 'B9 FA Twin', ['teamid' => 1, 'retired' => 0, 'cy' => 1, 'cyt' => 3]);
        $this->insertTestPlayer(200100023, 'B9 FA Twin', ['teamid' => 1, 'retired' => 0, 'cy' => 1, 'cyt' => 3]);

        $rows = array_values(array_filter(
            $this->repo->getFreeAgencyRoster(1),
            static fn (array $r): bool => $r['name'] === 'B9 FA Twin'
        ));

        self::assertSame([200100023, 200100024], array_column($rows, 'pid'));
    }

    public function testGetFreeAgentsBreaksOrdinalTieByPid(): void
    {
        $this->insertTestPlayer(200100026, 'B9 Ordinal Twin', ['ordinal' => 990, 'retired' => 0, 'cy' => 1, 'cyt' => 3]);
        $this->insertTestPlayer(200100025, 'B9 Ordinal Twin', ['ordinal' => 990, 'retired' => 0, 'cy' => 1, 'cyt' => 3]);

        $isTwin = static fn (array $r): bool => $r['name'] === 'B9 Ordinal Twin';

        $rows = array_values(array_filter($this->repo->getFreeAgents(), $isTwin));
        self::assertSame([200100025, 200100026], array_column($rows, 'pid'));

        $rows = array_values(array_filter($this->repo->getFreeAgents(true), $isTwin));
        self::assertSame([200100025, 200100026], array_column($rows, 'pid'));
    }

    public function testGetEntireLeagueRosterBreaksOrdinalTieByPid(): void
    {
        $this->insertTestPlayer(200100028, 'B9 League Twin', ['ordinal' => 5, 'retired' => 0]);
        $this->insertTestPlayer(200100027, 'B9 League Twin', ['ordinal' => 5, 'retired' => 0]);

        $rows = array_values(array_filter(
            $this->repo->getEntireLeagueRoster(),
            static fn (array $r): bool => $r['name'] === 'B9 League Twin'
        ));

        self::assertSame([200100027, 200100028], array_column($rows, 'pid'));
    }

    public function testGetHistoricalRosterBreaksNameTieByPid(): void
    {
        $this->insertHistRow(200100030, 'B9 Hist Twin', 2098, ['teamid' => 1]);
        $this->insertHistRow(200100029, 'B9 Hist Twin', 2098, ['teamid' => 1]);

        $rows = array_values(array_filter(
            $this->repo->getHistoricalRoster(1, '2098'),
            static fn (array $r): bool => $r['name'] === 'B9 Hist Twin'
        ));

        self::assertSame([200100029, 200100030], array_column($rows, 'pid'));
    }

    public function testGetGMAwardsOrdersSameYearAwardsAlphabetically(): void
    {
        $this->insertRow('ibl_gm_awards', ['name' => 'b9_tie_gm', 'award' => 'B9 Zeta Award', 'year' => 2097]);
        $this->insertRow('ibl_gm_awards', ['name' => 'b9_tie_gm', 'award' => 'B9 Alpha Award', 'year' => 2097]);
        $this->insertRow('ibl_gm_awards', ['name' => 'b9_tie_gm', 'award' => 'B9 Zzz Award', 'year' => 2096]);

        self::assertSame(
            ['B9 Zzz Award', 'B9 Alpha Award', 'B9 Zeta Award'],
            array_column($this->repo->getGMAwards('b9_tie_gm'), 'award')
        );
    }

    public function testGetRegularSeasonHistoryBreaksYearTieByTeamId(): void
    {
        $this->insertTeamSeasonRecordRow(2, 9097, 1, 'B9TieHist', 'B9TieHistB', 40, 42);
        $this->insertTeamSeasonRecordRow(1, 9097, 1, 'B9TieHist', 'B9TieHistA', 50, 32);
        $this->insertTeamSeasonRecordRow(2, 9098, 1, 'B9TieHist', 'B9TieHistB', 45, 37);

        self::assertSame([45, 50, 40], array_column($this->repo->getRegularSeasonHistory('B9TieHist'), 'wins'));
    }

    public function testGetPlayoffResultsOrdersSameYearSeriesByRound(): void
    {
        $this->insertPlayoffSeriesResultRow(9099, 2, 1, 3, 'B9PlayoffA', 'B9PlayoffC', 4, 2);
        $this->insertPlayoffSeriesResultRow(9099, 1, 1, 2, 'B9PlayoffA', 'B9PlayoffB', 4, 1);
        $this->insertPlayoffSeriesResultRow(9098, 1, 4, 1, 'B9PlayoffD', 'B9PlayoffA', 4, 3);

        $result = $this->repo->getPlayoffResults('B9PlayoffA');

        self::assertSame([1, 2, 1], array_column($result, 'round'));
        self::assertSame([9099, 9099, 9098], array_column($result, 'year'));
    }

    private function ensureStandingsAndPowerExist(int $teamid, string $division, string $conference): void
    {
        // Use REPLACE to ensure data exists within transaction regardless of DB state
        $this->db->query("DELETE FROM ibl_power WHERE teamid = $teamid");
        $this->db->query("DELETE FROM ibl_standings WHERE teamid = $teamid");
        $this->insertRow('ibl_standings', [
            'teamid' => $teamid,
            'team_name' => 'TestTeam',
            'wins' => 30,
            'losses' => 20,
            'pct' => 0.600,
            'league_record' => '30-20',
            'conference' => $conference,
            'division' => $division,
            'conf_record' => '18-12',
            'conf_gb' => 0.0,
            'div_record' => '8-4',
            'div_gb' => 0.0,
            'home_record' => '18-7',
            'away_record' => '12-13',
            'games_unplayed' => 32,
        ]);
        $this->insertRow('ibl_power', [
            'teamid' => $teamid,
            'ranking' => 75.5,
            'last_win' => 7,
            'last_loss' => 3,
            'streak_type' => 'W',
            'streak' => 3,
            'sos' => 0.510,
            'remaining_sos' => 0.490,
        ]);
    }
}
