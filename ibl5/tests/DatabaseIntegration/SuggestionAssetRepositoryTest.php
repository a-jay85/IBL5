<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use TradeSuggestions\ProductionSource;
use TradeSuggestions\SuggestionAssetRepository;
use TradeSuggestions\SuggestionPlayer;

/**
 * Tests SuggestionAssetRepository against real MariaDB — batched roster and pick reads.
 */
#[Group('database')]
class SuggestionAssetRepositoryTest extends DatabaseTestCase
{
    private const int LAST_SEASON = 2089;

    private SuggestionAssetRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SuggestionAssetRepository($this->db);
    }

    public function testRostersForTeamsGroupsByTeamAndExcludesRetired(): void
    {
        $this->insertTestPlayer(200000001, 'Suggest One', [
            'teamid' => 1, 'pos' => 'C',
            'cy' => 2, 'cyt' => 4, 'salary_yr1' => 900, 'salary_yr2' => 1000, 'salary_yr3' => 1100, 'salary_yr4' => 1200,
        ]);
        $this->insertTestPlayer(200000002, 'Suggest Two', ['teamid' => 2]);
        $this->insertTestPlayer(200000003, 'Suggest Retired', ['teamid' => 1, 'retired' => 1]);

        $rosters = $this->repo->getRostersForTeams([1, 2], false, self::LAST_SEASON);

        $team1Pids = array_map(static fn (SuggestionPlayer $p): int => $p->pid, $rosters[1] ?? []);
        $team2Pids = array_map(static fn (SuggestionPlayer $p): int => $p->pid, $rosters[2] ?? []);

        self::assertContains(200000001, $team1Pids);
        self::assertNotContains(200000002, $team1Pids);
        self::assertNotContains(200000003, $team1Pids);
        self::assertContains(200000002, $team2Pids);
        self::assertNotContains(200000001, $team2Pids);

        $one = null;
        foreach ($rosters[1] ?? [] as $player) {
            if ($player->pid === 200000001) {
                $one = $player;
            }
        }
        self::assertNotNull($one);
        self::assertSame(1000, $one->currentSalary);
        self::assertSame(3, $one->yearsRemaining);
        self::assertSame(1100 + 1200, $one->futureSalary);
        self::assertSame('C', $one->pos);
    }

    public function testRosterCarriesDepthRoleAndLastSeasonLine(): void
    {
        $this->insertTestPlayer(200000031, 'Suggest Starter', [
            'teamid' => 1, 'pos' => 'PG', 'dc_pg_depth' => 1, 'dc_minutes' => 34, 'stats_min' => 120,
        ]);
        $this->insertRow('ibl_hist', ['pid' => 200000031, 'year' => self::LAST_SEASON, 'name' => 'Suggest Starter', 'minutes' => 2400, 'pts' => 1200]);

        $player = self::byPid($this->repo->getRostersForTeams([1], false, self::LAST_SEASON)[1] ?? [])[200000031];

        self::assertSame('PG', $player->starterSlot());
        self::assertSame(34, $player->dcMinutes);
        self::assertNotNull($player->production);
        self::assertSame(ProductionSource::LastSeason, $player->production->source);
        self::assertSame(1200, $player->production->points);
    }

    public function testLastSeasonFetchIgnoresOtherYears(): void
    {
        $this->insertTestPlayer(200000032, 'Suggest Other Year', ['teamid' => 1, 'stats_min' => 120]);
        $this->insertRow('ibl_hist', ['pid' => 200000032, 'year' => self::LAST_SEASON + 1, 'name' => 'Suggest Other Year', 'minutes' => 2400, 'pts' => 1200]);

        $player = self::byPid($this->repo->getRostersForTeams([1], false, self::LAST_SEASON)[1] ?? [])[200000032];

        self::assertNull($player->production);
    }

    public function testRookieWithoutHistoryHasNoProduction(): void
    {
        $this->insertTestPlayer(200000033, 'Suggest Rookie', ['teamid' => 1, 'stats_min' => 50]);

        $player = self::byPid($this->repo->getRostersForTeams([1], false, self::LAST_SEASON)[1] ?? [])[200000033];

        self::assertFalse($player->hasProduction());
    }

    public function testRostersForTeamsEmptyListReturnsEmpty(): void
    {
        self::assertSame([], $this->repo->getRostersForTeams([], false, self::LAST_SEASON));
    }

    /**
     * In the offseason the contract year rolls forward: a player in the last
     * year of a deal has nothing left to trade, and one with a year to go
     * has exactly one year left.
     */
    public function testOffseasonShiftsTheContractYear(): void
    {
        $this->insertTestPlayer(200000011, 'Suggest Expiring', ['teamid' => 1, 'cy' => 2, 'cyt' => 2, 'salary_yr1' => 800, 'salary_yr2' => 900]);
        $this->insertTestPlayer(200000012, 'Suggest Last Year', ['teamid' => 1, 'cy' => 1, 'cyt' => 2, 'salary_yr1' => 800, 'salary_yr2' => 900]);

        $inSeason = self::byPid($this->repo->getRostersForTeams([1], false, self::LAST_SEASON)[1] ?? []);
        $offseason = self::byPid($this->repo->getRostersForTeams([1], true, self::LAST_SEASON)[1] ?? []);

        self::assertSame(1, $inSeason[200000011]->yearsRemaining);
        self::assertTrue($inSeason[200000011]->tradeable);
        self::assertSame(0, $offseason[200000011]->yearsRemaining);
        self::assertFalse($offseason[200000011]->tradeable);

        self::assertSame(2, $inSeason[200000012]->yearsRemaining);
        self::assertSame(900, $inSeason[200000012]->futureSalary);
        self::assertSame(1, $offseason[200000012]->yearsRemaining);
        self::assertSame(900, $offseason[200000012]->currentSalary);
        self::assertSame(0, $offseason[200000012]->futureSalary);
    }

    public function testLaterSalariesListFollowsEffectiveYear(): void
    {
        $this->insertTestPlayer(200000041, 'Suggest Later', [
            'teamid' => 1, 'cy' => 2, 'cyt' => 4, 'salary_yr1' => 900, 'salary_yr2' => 1000, 'salary_yr3' => 1100, 'salary_yr4' => 1200,
        ]);

        $inSeason = self::byPid($this->repo->getRostersForTeams([1], false, self::LAST_SEASON)[1] ?? [])[200000041];
        $offseason = self::byPid($this->repo->getRostersForTeams([1], true, self::LAST_SEASON)[1] ?? [])[200000041];

        self::assertSame(1000, $inSeason->currentSalary);
        self::assertSame([1100, 1200], $inSeason->laterSalaries);
        self::assertSame(1100, $offseason->currentSalary);
        self::assertSame([1200], $offseason->laterSalaries);
    }

    /**
     * In season the load reads `salary_yr{cy}`. Two 3500 deals sum to exactly
     * the 7000 hard cap, so the effective-year payroll is not over.
     */
    public function testCurrentSalaryUsesCurrentContractYearInSeason(): void
    {
        $players = $this->effectiveYearPair(false);

        self::assertSame(3500, $players[200000051]->currentSalary);
        self::assertSame(3500, $players[200000052]->currentSalary);
        self::assertSame(7000, self::payrollOf($players));
        self::assertLessThanOrEqual(\League\League::HARD_CAP_MAX, self::payrollOf($players));
    }

    /**
     * In the offseason the load reads `salary_yr{cy+1}`. The same two deals
     * rise to 3600 each, so the effective-year payroll goes over the cap.
     */
    public function testCurrentSalaryUsesNextContractYearInOffseason(): void
    {
        $players = $this->effectiveYearPair(true);

        self::assertSame(3600, $players[200000051]->currentSalary);
        self::assertSame(3600, $players[200000052]->currentSalary);
        self::assertSame(7200, self::payrollOf($players));
        self::assertGreaterThan(\League\League::HARD_CAP_MAX, self::payrollOf($players));
    }

    /**
     * @return array<int, SuggestionPlayer>
     */
    private function effectiveYearPair(bool $advancesContractYears): array
    {
        foreach ([200000051, 200000052] as $pid) {
            $this->insertTestPlayer($pid, 'Suggest Cap ' . $pid, [
                'teamid' => 1, 'cy' => 1, 'cyt' => 3, 'salary_yr1' => 3500, 'salary_yr2' => 3600, 'salary_yr3' => 3700,
            ]);
        }
        $all = self::byPid($this->repo->getRostersForTeams([1], $advancesContractYears, self::LAST_SEASON)[1] ?? []);

        return [200000051 => $all[200000051], 200000052 => $all[200000052]];
    }

    /**
     * @param array<int, SuggestionPlayer> $players
     */
    private static function payrollOf(array $players): int
    {
        return array_sum(array_map(static fn (SuggestionPlayer $p): int => $p->currentSalary, $players));
    }

    /**
     * The roster count must match TradeFormRepository::getTeamPlayerCount(),
     * which in the offseason counts anyone with a salary next contract year,
     * including a cy=0 player with salary_yr1 set.
     */
    public function testOffseasonRosterCountMatchesTheValidator(): void
    {
        $this->insertTestPlayer(200000031, 'Suggest Expiring', ['teamid' => 1, 'cy' => 2, 'cyt' => 2, 'salary_yr1' => 800, 'salary_yr2' => 900, 'salary_yr3' => 0]);
        $this->insertTestPlayer(200000032, 'Suggest Signed Rookie', ['teamid' => 1, 'cy' => 0, 'cyt' => 3, 'salary_yr1' => 500]);

        $offseason = self::byPid($this->repo->getRostersForTeams([1], true, self::LAST_SEASON)[1] ?? []);
        $inSeason = self::byPid($this->repo->getRostersForTeams([1], false, self::LAST_SEASON)[1] ?? []);

        self::assertFalse($offseason[200000031]->countsOnRoster);
        self::assertTrue($offseason[200000032]->countsOnRoster);
        self::assertFalse($offseason[200000032]->tradeable);
        self::assertTrue($inSeason[200000031]->countsOnRoster);

        $validatorCount = (new \Trading\TradeFormRepository($this->db))->getTeamPlayerCount(1, true);
        $engineCount = count(array_filter($offseason, static fn (SuggestionPlayer $p): bool => $p->countsOnRoster));
        self::assertSame($validatorCount, $engineCount);
    }

    public function testWaivedPlayerIsLeftOutAndUnsignedPlayerIsNotTradeable(): void
    {
        $this->insertTestPlayer(200000021, 'Suggest Waived', ['teamid' => 1, 'ordinal' => \League\JsbConstants::WAIVERS_ORDINAL + 1]);
        $this->insertTestPlayer(200000022, 'Suggest Unsigned', ['teamid' => 1, 'cy' => 0]);

        $players = self::byPid($this->repo->getRostersForTeams([1], false, self::LAST_SEASON)[1] ?? []);

        self::assertArrayNotHasKey(200000021, $players);
        self::assertFalse($players[200000022]->tradeable);
        self::assertSame(0, $players[200000022]->yearsRemaining);
    }

    public function testRegularSeasonRecordsReadOneYear(): void
    {
        $this->insertRow('ibl_team_season_records', ['team_id' => 1, 'year' => 2090, 'game_type' => 1, 'currentname' => 'Test', 'namethatyear' => 'Test', 'wins' => 60, 'losses' => 22]);
        $this->insertRow('ibl_team_season_records', ['team_id' => 1, 'year' => 2090, 'game_type' => 2, 'currentname' => 'Test', 'namethatyear' => 'Test', 'wins' => 9, 'losses' => 3]);
        $this->insertRow('ibl_team_season_records', ['team_id' => 1, 'year' => 2089, 'game_type' => 1, 'currentname' => 'Test', 'namethatyear' => 'Test', 'wins' => 20, 'losses' => 62]);

        self::assertSame([1 => ['wins' => 60, 'losses' => 22]], $this->repo->getRegularSeasonRecords(2090));
    }

    /**
     * @param list<SuggestionPlayer> $players
     * @return array<int, SuggestionPlayer>
     */
    private static function byPid(array $players): array
    {
        $byPid = [];
        foreach ($players as $player) {
            $byPid[$player->pid] = $player;
        }

        return $byPid;
    }

    public function testPicksForTeamsFiltersYearAndRound(): void
    {
        $current = $this->insertDraftPickRow(1, 3, 2090, 1);
        $secondRound = $this->insertDraftPickRow(1, 4, 2091, 2);
        $past = $this->insertDraftPickRow(1, 5, 2088, 1);
        $thirdRound = $this->insertDraftPickRow(1, 6, 2090, 3);

        $picks = $this->repo->getPicksForTeams([1], 2090);

        $ids = array_map(static fn (array $row): int => $row['pickid'], $picks[1] ?? []);
        self::assertContains($current, $ids);
        self::assertContains($secondRound, $ids);
        self::assertNotContains($past, $ids);
        self::assertNotContains($thirdRound, $ids);

        foreach ($picks[1] ?? [] as $row) {
            if ($row['pickid'] === $current) {
                self::assertSame(['pickid' => $current, 'owner_teamid' => 1, 'teampick_teamid' => 3, 'year' => 2090, 'round' => 1], $row);
            }
        }
    }
}
