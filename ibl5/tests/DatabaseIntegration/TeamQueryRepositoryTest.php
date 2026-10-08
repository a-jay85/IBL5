<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;

use Team\TeamQueryRepository;
use Team\TeamCapCalculator;

#[Group('database')]
class TeamQueryRepositoryTest extends DatabaseTestCase
{
    private TeamQueryRepository $repo;

    /** Team ID used for test data — must be a real team in the DB (1-28) */
    private const TEST_TID = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new TeamQueryRepository($this->db);
    }

    // --- Buyouts ---

    public function testGetBuyoutsReturnsBuyoutRowsFromCashConsiderations(): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO ibl_cash_considerations (teamid, type, label, cy, cyt, salary_yr1) VALUES (?, 'buyout', ?, ?, ?, ?)"
        );
        self::assertNotFalse($stmt);
        $label = 'Test Buyout';
        $cy = 1;
        $cyt = 1;
        $salaryYr1 = 500;
        $stmt->bind_param('isiii', ...[ self::TEST_TID, $label, $cy, $cyt, $salaryYr1 ]);
        $stmt->execute();
        $stmt->close();

        $result = $this->repo->getBuyouts(self::TEST_TID);

        $found = false;
        foreach ($result as $row) {
            if ($row['label'] === 'Test Buyout') {
                $found = true;
                self::assertSame(500, $row['salary_yr1']);
                break;
            }
        }
        self::assertTrue($found, 'Buyout row should be found in ibl_cash_considerations');
    }

    public function testGetBuyoutsReturnsEmptyForTeamWithNoBuyouts(): void
    {
        // Team ID 9999 has no buyouts
        $result = $this->repo->getBuyouts(9999);

        self::assertSame([], $result);
    }

    // --- Draft History ---

    public function testGetDraftHistoryReturnsPlayersDraftedByTeam(): void
    {
        $this->insertTestPlayer(200000102, 'Drafted Test Guy', [
            'draftedby' => 'TestDraftTeam',
            'draftyear' => 2098,
            'draftround' => 1,
            'draftpickno' => 5,
        ]);

        $result = $this->repo->getDraftHistory('TestDraftTeam');

        self::assertNotEmpty($result);
        $found = false;
        foreach ($result as $player) {
            if ($player['pid'] === 200000102) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Draft history should include test player');
    }

    // --- Draft Picks ---

    public function testGetDraftPicksReturnsOwnedPicks(): void
    {
        $this->insertRow('ibl_draft_picks', [
            'ownerofpick' => 'TestPickOwner',
            'owner_teamid' => self::TEST_TID,
            'teampick' => 'TestPickTeam',
            'teampick_teamid' => self::TEST_TID,
            'year' => 2098,
            'round' => 1,
            'notes' => 'Integration test pick',
        ]);

        $result = $this->repo->getDraftPicks(self::TEST_TID);

        $found = false;
        foreach ($result as $pick) {
            if ($pick['year'] === 2098 && $pick['notes'] === 'Integration test pick') {
                $found = true;
                self::assertSame(self::TEST_TID, $pick['owner_teamid']);
                break;
            }
        }
        self::assertTrue($found, 'Draft pick for year 2098 should be found');
    }

    // --- Free Agency Offers ---

    public function testGetFreeAgencyOffersReturnsOffers(): void
    {
        $this->insertTestPlayer(200090103, 'FA Offer Target', ['teamid' => 0]);

        $this->insertRow('ibl_fa_offers', [
            'name' => 'FA Offer Target',
            'pid' => 200090103,
            'team' => 'Test Team',
            'teamid' => self::TEST_TID,
            'offer1' => 1000,
            'offer2' => 1100,
            'offer3' => 0,
            'offer4' => 0,
            'offer5' => 0,
            'offer6' => 0,
            'modifier' => 1.0,
            'random' => 0.5,
            'perceivedvalue' => 1000.0,
            'mle' => 0,
            'lle' => 0,
            'offer_type' => 0,
        ]);

        $result = $this->repo->getFreeAgencyOffers(self::TEST_TID);

        $found = false;
        foreach ($result as $offer) {
            if ($offer['name'] === 'FA Offer Target') {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'FA offer should be found');
    }

    // --- Free Agency Roster ---

    public function testGetFreeAgencyRosterOrderedByNameFiltersCorrectly(): void
    {
        // cyt != cy → eligible for free agency roster
        $this->insertTestPlayer(200090104, 'FA Roster Guy', [
            'cy' => 1,
            'cyt' => 3,
        ]);

        $result = $this->repo->getFreeAgencyRosterOrderedByName(self::TEST_TID);

        $found = false;
        foreach ($result as $player) {
            if ($player['pid'] === 200090104) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'FA eligible player should appear');
    }

    // --- Healthy and Injured Players ---

    public function testGetHealthyAndInjuredPlayersOrderedByName(): void
    {
        $this->insertTestPlayer(200090105, 'Healthy Test', ['ordinal' => 5]);

        $result = $this->repo->getHealthyAndInjuredPlayersOrderedByName(self::TEST_TID);

        self::assertNotEmpty($result);
        $found = false;
        foreach ($result as $player) {
            if ($player['pid'] === 200090105) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Healthy player should be in result');
    }

    public function testGetHealthyPlayersOrderedByNameExcludesInjured(): void
    {
        $this->insertTestPlayer(200090106, 'Injured Test Guy', [
            'injured' => 1,
            'ordinal' => 5,
        ]);

        $result = $this->repo->getHealthyPlayersOrderedByName(self::TEST_TID);

        foreach ($result as $player) {
            self::assertNotSame(200090106, $player['pid'], 'Injured player should be excluded');
        }
    }

    // --- Starter Position Depth ---

    public function testGetLastSimStarterPlayerIDForPosition(): void
    {
        $this->insertTestPlayer(200090107, 'PG Starter Test', [
            'pg_depth' => 1,
            'pos' => 'PG',
        ]);
        // Clear other starters for this position
        $this->db->query("UPDATE ibl_plr SET pg_depth = 0 WHERE teamid = " . self::TEST_TID . " AND pid != 200090107 AND pg_depth = 1");

        $result = $this->repo->getLastSimStarterPlayerIDForPosition(self::TEST_TID, 'PG');

        self::assertSame(200090107, $result);
    }

    /**
     * @return list<array{string}>
     */
    public static function jsbPositionProvider(): array
    {
        return array_map(
            static fn (string $pos): array => [$pos],
            \League\JsbConstants::PLAYER_POSITIONS
        );
    }

    #[DataProvider('jsbPositionProvider')]
    public function testStarterLookupsResolveEveryJsbPosition(string $pos): void
    {
        $pid = 200090310;
        $col = strtolower($pos) . '_depth';
        $this->insertTestPlayer($pid, $pos . ' Every Position Starter', [
            $col => 1,
            'dc_' . $col => 1,
            'pos' => $pos,
        ]);
        // Clear other starters for this position so only the seeded row matches
        $this->db->query("UPDATE ibl_plr SET {$col} = 0 WHERE teamid = " . self::TEST_TID . " AND pid != {$pid} AND {$col} = 1");
        $this->db->query("UPDATE ibl_plr SET dc_{$col} = 0 WHERE teamid = " . self::TEST_TID . " AND pid != {$pid} AND dc_{$col} = 1");

        self::assertSame($pid, $this->repo->getLastSimStarterPlayerIDForPosition(self::TEST_TID, $pos));
        self::assertSame($pid, $this->repo->getCurrentlySetStarterPlayerIDForPosition(self::TEST_TID, $pos));
    }

    public function testStarterLookupsAcceptLowercasePosition(): void
    {
        $pid = 200090311;
        $this->insertTestPlayer($pid, 'Lowercase Position Starter', [
            'pg_depth' => 1,
            'dc_pg_depth' => 1,
            'pos' => 'PG',
        ]);
        $this->db->query("UPDATE ibl_plr SET pg_depth = 0 WHERE teamid = " . self::TEST_TID . " AND pid != {$pid} AND pg_depth = 1");
        $this->db->query("UPDATE ibl_plr SET dc_pg_depth = 0 WHERE teamid = " . self::TEST_TID . " AND pid != {$pid} AND dc_pg_depth = 1");

        $upperLastSim = $this->repo->getLastSimStarterPlayerIDForPosition(self::TEST_TID, 'PG');
        $upperCurrent = $this->repo->getCurrentlySetStarterPlayerIDForPosition(self::TEST_TID, 'PG');

        self::assertSame($pid, $upperLastSim);
        self::assertSame($pid, $upperCurrent);
        self::assertSame($upperLastSim, $this->repo->getLastSimStarterPlayerIDForPosition(self::TEST_TID, 'pg'));
        self::assertSame($upperCurrent, $this->repo->getCurrentlySetStarterPlayerIDForPosition(self::TEST_TID, 'pg'));
    }

    public function testGetCurrentlySetStarterPlayerIDForPosition(): void
    {
        $this->insertTestPlayer(200090108, 'DC PG Starter', [
            'dc_pg_depth' => 1,
            'pos' => 'PG',
        ]);
        $this->db->query("UPDATE ibl_plr SET dc_pg_depth = 0 WHERE teamid = " . self::TEST_TID . " AND pid != 200090108 AND dc_pg_depth = 1");

        $result = $this->repo->getCurrentlySetStarterPlayerIDForPosition(self::TEST_TID, 'PG');

        self::assertSame(200090108, $result);
    }

    public function testGetLastSimStarterReturnsZeroWhenNone(): void
    {
        // Use a team with no players at all
        $result = $this->repo->getLastSimStarterPlayerIDForPosition(9999, 'C');

        self::assertSame(0, $result);
    }

    // --- Players Under Contract ---

    public function testGetAllPlayersUnderContractFiltersByCy1(): void
    {
        $this->insertTestPlayer(200090109, 'Under Contract Guy', ['salary_yr1' => 2000]);

        $result = $this->repo->getAllPlayersUnderContract(self::TEST_TID);

        self::assertNotEmpty($result);
        foreach ($result as $player) {
            self::assertNotSame(0, $player['salary_yr1']);
        }
    }

    public function testGetPlayersUnderContractByPositionFiltersPos(): void
    {
        $this->insertTestPlayer(200090110, 'PG Contract', [
            'pos' => 'PG',
            'salary_yr1' => 1500,
        ]);
        $this->insertTestPlayer(200090111, 'SF Contract', [
            'pos' => 'SF',
            'salary_yr1' => 1500,
            'uuid' => 'tq-test-sf11-0000-000000000001',
        ]);

        $result = $this->repo->getPlayersUnderContractByPosition(self::TEST_TID, 'PG');

        foreach ($result as $player) {
            self::assertSame('PG', $player['pos']);
        }
        // Verify the SF player is NOT in PG results
        $pids = array_column($result, 'pid');
        self::assertNotContains(200090111, $pids, 'SF player should not be in PG query');
    }

    // --- Unique-pid contract (feeds TeamCapCalculator salary totals) ---

    /**
     * TeamCapCalculator sums every row it is given, so pid uniqueness is this
     * query layer's contract (backlog a-jay85/IBL5-backlog#228).
     *
     * @return list<int> Team-1 pids that every under-contract query must return once
     */
    private function seedUniquePidRoster(): array
    {
        $this->insertTestPlayer(200090201, 'Unique Pid A', ['teamid' => 1, 'pos' => 'PG', 'salary_yr1' => 1000, 'salary_yr2' => 2000]);
        $this->insertTestPlayer(200090202, 'Unique Pid B', ['teamid' => 1, 'pos' => 'PG', 'salary_yr1' => 1100, 'salary_yr2' => 2100]);
        $this->insertTestPlayer(200090203, 'Unique Pid C', ['teamid' => 1, 'pos' => 'PG', 'salary_yr1' => 1200, 'salary_yr2' => 2200]);
        $this->insertTestPlayer(200090204, 'Unique Pid Team Two', ['teamid' => 2, 'pos' => 'PG', 'salary_yr1' => 1300, 'salary_yr2' => 2300]);
        $this->insertTestPlayer(200090205, 'Unique Pid Team Three', ['teamid' => 3, 'pos' => 'PG', 'salary_yr1' => 1400, 'salary_yr2' => 2400]);
        $this->insertTestPlayer(200090206, 'Unique Pid Retired', ['teamid' => 1, 'pos' => 'PG', 'retired' => 1]);

        return [200090201, 200090202, 200090203];
    }

    /**
     * @return list<string>
     */
    private function primaryKeyColumns(string $table): array
    {
        $stmt = $this->db->prepare(
            "SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY' ORDER BY ORDINAL_POSITION"
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);

        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = (string) $row['COLUMN_NAME'];
        }
        $stmt->close();

        return $columns;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function underContractQueryProvider(): array
    {
        return [
            'all under contract' => ['all'],
            'by position PG' => ['byPosition'],
            'roster ordered by name' => ['rosterByName'],
        ];
    }

    #[DataProvider('underContractQueryProvider')]
    public function testUnderContractQueriesReturnEachPidOnce(string $query): void
    {
        $seeded = $this->seedUniquePidRoster();

        $rows = match ($query) {
            'all' => $this->repo->getAllPlayersUnderContract(self::TEST_TID),
            'byPosition' => $this->repo->getPlayersUnderContractByPosition(self::TEST_TID, 'PG'),
            'rosterByName' => $this->repo->getRosterUnderContractOrderedByName(self::TEST_TID),
            default => self::fail('Unknown query label: ' . $query),
        };
        $pids = array_column($rows, 'pid');

        self::assertCount(
            count($rows),
            array_unique($pids),
            $query . ' returned a repeated pid, which TeamCapCalculator would double-count'
        );
        foreach ($seeded as $pid) {
            self::assertContains($pid, $pids, $query . ' should return seeded team-1 pid ' . $pid);
        }
        self::assertNotContains(200090204, $pids, $query . ' should not return a team-2 player');
        self::assertNotContains(200090205, $pids, $query . ' should not return a team-3 player');
        self::assertNotContains(200090206, $pids, $query . ' should not return a retired player');
    }

    public function testPlayerAndTeamPrimaryKeysAreSingleColumn(): void
    {
        self::assertSame(['pid'], $this->primaryKeyColumns('ibl_plr'));
        self::assertSame(['teamid'], $this->primaryKeyColumns('ibl_team_info'));
    }

    public function testCapTotalsOverFetchedRowsEqualTotalsOverDistinctPids(): void
    {
        $this->seedUniquePidRoster();
        $calculator = new TeamCapCalculator($this->db);

        $rosterRows = $this->repo->getRosterUnderContractOrderedByName(self::TEST_TID);
        $rosterDistinct = array_values(array_column($rosterRows, null, 'pid'));
        $currentTotal = $calculator->getTotalCurrentSeasonSalaries($rosterRows);
        self::assertGreaterThan(0, $currentTotal);
        self::assertSame($calculator->getTotalCurrentSeasonSalaries($rosterDistinct), $currentTotal);

        $allRows = $this->repo->getAllPlayersUnderContract(self::TEST_TID);
        $allDistinct = array_values(array_column($allRows, null, 'pid'));
        $nextTotalAll = $calculator->getTotalNextSeasonSalaries($allRows);
        self::assertGreaterThan(0, $nextTotalAll);
        self::assertSame($calculator->getTotalNextSeasonSalaries($allDistinct), $nextTotalAll);

        $positionRows = $this->repo->getPlayersUnderContractByPosition(self::TEST_TID, 'PG');
        $positionDistinct = array_values(array_column($positionRows, null, 'pid'));
        $nextTotalPosition = $calculator->getTotalNextSeasonSalaries($positionRows);
        self::assertGreaterThan(0, $nextTotalPosition);
        self::assertSame($calculator->getTotalNextSeasonSalaries($positionDistinct), $nextTotalPosition);
    }

    // --- Roster Ordering ---

    public function testGetRosterUnderContractOrderedByName(): void
    {
        $result = $this->repo->getRosterUnderContractOrderedByName(self::TEST_TID);

        self::assertNotEmpty($result);
        // Verify name ordering
        $names = array_column($result, 'name');
        $sorted = $names;
        sort($sorted);
        self::assertSame($sorted, $names);
    }

    public function testGetRosterUnderContractOrderedByOrdinal(): void
    {
        $result = $this->repo->getRosterUnderContractOrderedByOrdinal(self::TEST_TID);

        self::assertNotEmpty($result);
        // Verify ordinal ordering
        $ordinals = array_column($result, 'ordinal');
        $sorted = $ordinals;
        sort($sorted);
        self::assertSame($sorted, $ordinals);
    }
}
