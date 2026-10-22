<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use TradeSuggestions\SuggestionAssetRepository;
use TradeSuggestions\SuggestionPlayer;

/**
 * Tests SuggestionAssetRepository against real MariaDB — batched roster and pick reads.
 */
#[Group('database')]
class SuggestionAssetRepositoryTest extends DatabaseTestCase
{
    private SuggestionAssetRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SuggestionAssetRepository($this->db);
    }

    public function testRostersForTeamsGroupsByTeamAndExcludesRetired(): void
    {
        $this->insertTestPlayer(200000001, 'Suggest One', [
            'teamid' => 1, 'pos' => 'C', 'talent' => 5, 'skill' => 5, 'intangibles' => 5,
            'r_fga' => 50, 'oo' => 25,
            'cy' => 2, 'cyt' => 4, 'salary_yr1' => 900, 'salary_yr2' => 1000, 'salary_yr3' => 1100, 'salary_yr4' => 1200,
        ]);
        $this->insertTestPlayer(200000002, 'Suggest Two', ['teamid' => 2]);
        $this->insertTestPlayer(200000003, 'Suggest Retired', ['teamid' => 1, 'retired' => 1]);

        $rosters = $this->repo->getRostersForTeams([1, 2], self::maximums(), false);

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
        // ovr reads the rating fields, not the 1-5 scouting grades: r_fga at the
        // league max (100%), oo at half of it (50%), the other 19 at zero.
        self::assertSame((int) round(150 / 21), $one->ovr);
        self::assertSame(1000, $one->currentSalary);
        self::assertSame(3, $one->yearsRemaining);
        self::assertSame(1100 + 1200, $one->futureSalary);
        self::assertSame('C', $one->pos);
    }

    public function testRostersForTeamsEmptyListReturnsEmpty(): void
    {
        self::assertSame([], $this->repo->getRostersForTeams([], self::maximums(), false));
    }

    /**
     * In the offseason the contract year rolls forward: a player in the last
     * year of his deal has nothing left to trade, and one with a year to go
     * has exactly one year left.
     */
    public function testOffseasonShiftsTheContractYear(): void
    {
        $this->insertTestPlayer(200000011, 'Suggest Expiring', ['teamid' => 1, 'cy' => 2, 'cyt' => 2, 'salary_yr1' => 800, 'salary_yr2' => 900]);
        $this->insertTestPlayer(200000012, 'Suggest Last Year', ['teamid' => 1, 'cy' => 1, 'cyt' => 2, 'salary_yr1' => 800, 'salary_yr2' => 900]);

        $inSeason = self::byPid($this->repo->getRostersForTeams([1], self::maximums(), false)[1] ?? []);
        $offseason = self::byPid($this->repo->getRostersForTeams([1], self::maximums(), true)[1] ?? []);

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

    public function testWaivedPlayerIsLeftOutAndUnsignedPlayerIsNotTradeable(): void
    {
        $this->insertTestPlayer(200000021, 'Suggest Waived', ['teamid' => 1, 'ordinal' => \League\JsbConstants::WAIVERS_ORDINAL + 1]);
        $this->insertTestPlayer(200000022, 'Suggest Unsigned', ['teamid' => 1, 'cy' => 0]);

        $players = self::byPid($this->repo->getRostersForTeams([1], self::maximums(), false)[1] ?? []);

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
     * League maximums of 50 for every rating key.
     *
     * @return array{fga: int, fgp: int, fta: int, ftp: int, tga: int, tgp: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, foul: int, oo: int, od: int, do: int, dd: int, po: int, pd: int, to: int, td: int}
     */
    private static function maximums(): array
    {
        return ['fga' => 50, 'fgp' => 50, 'fta' => 50, 'ftp' => 50, 'tga' => 50, 'tgp' => 50, 'orb' => 50, 'drb' => 50, 'ast' => 50, 'stl' => 50, 'tov' => 50, 'blk' => 50, 'foul' => 50, 'oo' => 50, 'od' => 50, 'do' => 50, 'dd' => 50, 'po' => 50, 'pd' => 50, 'to' => 50, 'td' => 50];
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
