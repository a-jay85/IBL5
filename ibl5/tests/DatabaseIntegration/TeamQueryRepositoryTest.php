<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use League\League;
use Team\TeamQueryRepository;

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

    public function testGetBuyoutsReturnsPlayersWithBuyoutInName(): void
    {
        $this->insertTestPlayer(200000101, 'Test Buyout Player', [
            'cy' => 1,
            'cyt' => 1,
            'cy1' => 500,
        ]);

        $result = $this->repo->getBuyouts(self::TEST_TID);

        $found = false;
        foreach ($result as $player) {
            if ($player['pid'] === 200000101) {
                $found = true;
                self::assertSame('Test Buyout Player', $player['name']);
                break;
            }
        }
        self::assertTrue($found, 'Buyout player should be found');
    }

    public function testGetBuyoutsReturnsEmptyForTeamWithNoBuyouts(): void
    {
        // Team 99 doesn't exist in production — no buyout players
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
            'owner_tid' => self::TEST_TID,
            'teampick' => 'TestPickTeam',
            'teampick_tid' => self::TEST_TID,
            'year' => 2098,
            'round' => 1,
            'notes' => 'Integration test pick',
        ]);

        $result = $this->repo->getDraftPicks(self::TEST_TID);

        $found = false;
        foreach ($result as $pick) {
            if ($pick['year'] === 2098 && $pick['notes'] === 'Integration test pick') {
                $found = true;
                self::assertSame(self::TEST_TID, $pick['owner_tid']);
                break;
            }
        }
        self::assertTrue($found, 'Draft pick for year 2098 should be found');
    }

    // --- Free Agency Offers ---

    public function testGetFreeAgencyOffersReturnsOffers(): void
    {
        $this->insertTestPlayer(200090103, 'FA Offer Target', ['tid' => 0]);

        $this->insertRow('ibl_fa_offers', [
            'name' => 'FA Offer Target',
            'pid' => 200090103,
            'team' => 'Test Team',
            'tid' => self::TEST_TID,
            'offer1' => 1000,
            'offer2' => 1100,
            'offer3' => 0,
            'offer4' => 0,
            'offer5' => 0,
            'offer6' => 0,
            'modifier' => 1.0,
            'random' => 0.5,
            'perceivedvalue' => 1000.0,
            'MLE' => 0,
            'LLE' => 0,
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
            'PGDepth' => 1,
            'pos' => 'PG',
        ]);
        // Clear other starters for this position
        $this->db->query("UPDATE ibl_plr SET PGDepth = 0 WHERE tid = " . self::TEST_TID . " AND pid != 200090107 AND PGDepth = 1");

        $result = $this->repo->getLastSimStarterPlayerIDForPosition(self::TEST_TID, 'PG');

        self::assertSame(200090107, $result);
    }

    public function testGetCurrentlySetStarterPlayerIDForPosition(): void
    {
        $this->insertTestPlayer(200090108, 'DC PG Starter', [
            'dc_PGDepth' => 1,
            'pos' => 'PG',
        ]);
        $this->db->query("UPDATE ibl_plr SET dc_PGDepth = 0 WHERE tid = " . self::TEST_TID . " AND pid != 200090108 AND dc_PGDepth = 1");

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
        $this->insertTestPlayer(200090109, 'Under Contract Guy', ['cy1' => 2000]);

        $result = $this->repo->getAllPlayersUnderContract(self::TEST_TID);

        self::assertNotEmpty($result);
        foreach ($result as $player) {
            self::assertNotSame(0, $player['cy1']);
        }
    }

    public function testGetPlayersUnderContractByPositionFiltersPos(): void
    {
        $this->insertTestPlayer(200090110, 'PG Contract', [
            'pos' => 'PG',
            'cy1' => 1500,
        ]);
        $this->insertTestPlayer(200090111, 'SF Contract', [
            'pos' => 'SF',
            'cy1' => 1500,
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

    // --- Salary Cap ---

    public function testGetSalaryCapArrayCalculatesMultiYearBreakdown(): void
    {
        $season = new \Season($this->db);

        $result = $this->repo->getSalaryCapArray('Test', self::TEST_TID, $season);

        self::assertIsArray($result);
        // Should have at least year1 if there are players under contract
        if ($result !== []) {
            self::assertArrayHasKey('year1', $result);
            self::assertGreaterThan(0, $result['year1']);
        }
    }

    // --- Hard Cap Check ---

    public function testCanAddContractWithoutGoingOverHardCapReturnsCorrectly(): void
    {
        // Adding 0 should always be under the cap
        $resultUnder = $this->repo->canAddContractWithoutGoingOverHardCap(self::TEST_TID, 0);
        self::assertTrue($resultUnder);

        // Adding the entire hard cap should exceed it (since team already has players)
        $resultOver = $this->repo->canAddContractWithoutGoingOverHardCap(self::TEST_TID, League::HARD_CAP_MAX);
        self::assertFalse($resultOver);
    }

    // ── getTotalCurrentSeasonSalaries ───────────────────────────

    public function testGetTotalCurrentSeasonSalariesSumsContracts(): void
    {
        // Use a real team with isolated test player. First clear any existing
        // cy1>0 players on team 28 to get a predictable sum.
        $this->db->query('UPDATE ibl_plr SET cy1 = 0 WHERE tid = 28');

        $this->insertTestPlayer(200000150, 'TQ CurSal', [
            'tid' => 28,
            'cy' => 1,
            'cyt' => 2,
            'cy1' => 3000,
            'cy2' => 3200,
        ]);

        $rows = $this->repo->getAllPlayersUnderContract(28);
        $total = $this->repo->getTotalCurrentSeasonSalaries($rows);

        self::assertIsInt($total);
        self::assertSame(3000, $total);
    }

    // ── getTotalNextSeasonSalaries ──────────────────────────────

    public function testGetTotalNextSeasonSalariesSumsContracts(): void
    {
        $this->db->query('UPDATE ibl_plr SET cy1 = 0 WHERE tid = 28');

        $this->insertTestPlayer(200000151, 'TQ NxtSal', [
            'tid' => 28,
            'cy' => 1,
            'cyt' => 2,
            'cy1' => 1500,
            'cy2' => 2200,
        ]);

        $rows = $this->repo->getAllPlayersUnderContract(28);
        $total = $this->repo->getTotalNextSeasonSalaries($rows);

        self::assertIsInt($total);
        self::assertSame(2200, $total);
    }

    // ── canAddBuyoutWithoutExceedingBuyoutLimit ─────────────────

    public function testCanAddBuyoutWithinLimitReturnsTrue(): void
    {
        // Team 99999 has no players → buyout sum is 0, adding 0 is within limit
        self::assertTrue($this->repo->canAddBuyoutWithoutExceedingBuyoutLimit(99999, 0));
    }

    public function testCanAddBuyoutExceedingLimitReturnsFalse(): void
    {
        // Adding the entire hard cap always exceeds the buyout percentage limit
        self::assertFalse($this->repo->canAddBuyoutWithoutExceedingBuyoutLimit(99999, \League::HARD_CAP_MAX));
    }
}
