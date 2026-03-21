<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use League\League;
use ProjectedDraftOrder\ProjectedDraftOrderRepository;

/**
 * Database integration tests for ProjectedDraftOrderRepository.
 *
 * Tests read queries and write operations (saveFinalDraftOrder).
 * Write operations use transactional() with savepoint support, so
 * DatabaseTestCase's transaction rollback handles all cleanup.
 */
class ProjectedDraftOrderRepositoryTest extends DatabaseTestCase
{
    private ProjectedDraftOrderRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new ProjectedDraftOrderRepository($this->db);
    }

    public function testGetAllTeamsWithStandingsReturnsJoinedRows(): void
    {
        $result = $this->repo->getAllTeamsWithStandings();

        self::assertCount(28, $result);
        $first = $result[0];
        self::assertArrayHasKey('tid', $first);
        self::assertArrayHasKey('team_name', $first);
        self::assertArrayHasKey('wins', $first);
        self::assertArrayHasKey('losses', $first);
        self::assertArrayHasKey('conference', $first);
        self::assertArrayHasKey('division', $first);
        self::assertArrayHasKey('color1', $first);

        // Should only include real teams (1-28)
        foreach ($result as $row) {
            self::assertGreaterThanOrEqual(1, $row['tid']);
            self::assertLessThanOrEqual(League::MAX_REAL_TEAMID, $row['tid']);
        }
    }

    public function testGetPlayedGamesFiltersUnplayed(): void
    {
        // Seed schedule has Year=2025, VScore=85, HScore=104 (played)
        // Insert an unplayed game (scores=0)
        $this->insertRow('ibl_schedule', [
            'Year' => 2025,
            'BoxID' => 0,
            'Date' => '2025-02-01',
            'Visitor' => 1,
            'VScore' => 0,
            'Home' => 2,
            'HScore' => 0,
            'uuid' => 'sched-test-unplayed-000000000001',
        ]);

        $result = $this->repo->getPlayedGames(2025);

        // All returned games should have scores > 0
        foreach ($result as $row) {
            self::assertGreaterThan(0, $row['VScore']);
            self::assertGreaterThan(0, $row['HScore']);
        }
    }

    public function testGetPlayedGamesFiltersByYear(): void
    {
        // Insert played game for year 2099
        $this->insertRow('ibl_schedule', [
            'Year' => 2099,
            'BoxID' => 0,
            'Date' => '2099-01-15',
            'Visitor' => 2,
            'VScore' => 90,
            'Home' => 1,
            'HScore' => 100,
            'uuid' => 'sched-test-yr2099-000000000001',
        ]);

        $result = $this->repo->getPlayedGames(2099);

        self::assertCount(1, $result);
        self::assertSame(90, $result[0]['VScore']);
    }

    public function testGetPickOwnershipFiltersRoundsOneAndTwo(): void
    {
        // Insert picks for rounds 1, 2, and 3
        $this->insertRow('ibl_draft_picks', [
            'ownerofpick' => 'Metros',
            'owner_tid' => 1,
            'teampick' => 'Metros',
            'teampick_tid' => 1,
            'year' => 2099,
            'round' => 1,
            'notes' => 'Test R1',
        ]);
        $this->insertRow('ibl_draft_picks', [
            'ownerofpick' => 'Metros',
            'owner_tid' => 1,
            'teampick' => 'Metros',
            'teampick_tid' => 1,
            'year' => 2099,
            'round' => 2,
            'notes' => 'Test R2',
        ]);
        $this->insertRow('ibl_draft_picks', [
            'ownerofpick' => 'Metros',
            'owner_tid' => 1,
            'teampick' => 'Metros',
            'teampick_tid' => 1,
            'year' => 2099,
            'round' => 3,
            'notes' => 'Test R3',
        ]);

        $result = $this->repo->getPickOwnership(2099);

        foreach ($result as $row) {
            self::assertContains($row['round'], [1, 2], 'Only rounds 1-2 should be returned');
        }
        self::assertCount(2, $result);
    }

    public function testGetPointDifferentialsAggregates(): void
    {
        // Insert two played games for year 2099
        $this->insertRow('ibl_schedule', [
            'Year' => 2099,
            'BoxID' => 0,
            'Date' => '2099-02-10',
            'Visitor' => 2,
            'VScore' => 90,
            'Home' => 1,
            'HScore' => 110,
            'uuid' => 'sched-ptdiff-001-000000000001',
        ]);
        $this->insertRow('ibl_schedule', [
            'Year' => 2099,
            'BoxID' => 0,
            'Date' => '2099-02-12',
            'Visitor' => 1,
            'VScore' => 95,
            'Home' => 2,
            'HScore' => 100,
            'uuid' => 'sched-ptdiff-002-000000000001',
        ]);

        $result = $this->repo->getPointDifferentials(2099);

        self::assertNotEmpty($result);
        // Find Metros (tid=1): Game 1 home: 110 scored, 90 allowed. Game 2 visitor: 95 scored, 100 allowed.
        $metros = null;
        foreach ($result as $row) {
            if ($row['tid'] === 1) {
                $metros = $row;
                break;
            }
        }
        self::assertNotNull($metros);
        self::assertSame(205.0, (float) $metros['pointsFor']); // 110 + 95
        self::assertSame(190.0, (float) $metros['pointsAgainst']); // 90 + 100
    }

    public function testIsDraftOrderFinalizedReturnsFalse(): void
    {
        // Explicitly set to 'No' within the transaction to avoid relying on seed/production state
        $stmt = $this->db->prepare("UPDATE ibl_settings SET value = 'No' WHERE name = 'Draft Order Finalized'");
        self::assertNotFalse($stmt);
        $stmt->execute();
        $stmt->close();

        self::assertFalse($this->repo->isDraftOrderFinalized());
    }

    public function testSaveFinalDraftOrderInsertsPicks(): void
    {
        $picks = [
            ['round' => 1, 'pick' => 1, 'team' => 'Metros', 'tid' => 1],
            ['round' => 1, 'pick' => 2, 'team' => 'Sharks', 'tid' => 2],
        ];

        $this->repo->saveFinalDraftOrder(2099, $picks);

        // Verify via direct query
        $stmt = $this->db->prepare("SELECT pick, team FROM ibl_draft WHERE year = 2099 AND round = 1 ORDER BY pick");
        self::assertNotFalse($stmt);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        self::assertCount(2, $rows);
        self::assertSame('Metros', $rows[0]['team']);
        self::assertSame('Sharks', $rows[1]['team']);
    }

    public function testSaveFinalDraftOrderSetsFinalized(): void
    {
        $picks = [
            ['round' => 1, 'pick' => 1, 'team' => 'Metros', 'tid' => 1],
        ];

        $this->repo->saveFinalDraftOrder(2099, $picks);

        self::assertTrue($this->repo->isDraftOrderFinalized());
    }

    public function testSaveFinalDraftOrderDeletesOldEmptySlots(): void
    {
        // Pre-insert an empty draft slot
        $this->insertRow('ibl_draft', [
            'year' => 2099,
            'round' => 1,
            'pick' => 1,
            'team' => 'OldTeam',
            'tid' => 1,
            'player' => '',
            'uuid' => 'draft-old-slot-0000-000000000001',
        ]);

        $picks = [
            ['round' => 1, 'pick' => 1, 'team' => 'Metros', 'tid' => 1],
        ];

        $this->repo->saveFinalDraftOrder(2099, $picks);

        // Verify old slot was replaced
        $rows = $this->repo->getFinalDraftOrder(2099, 1);
        self::assertCount(1, $rows);
        self::assertSame('Metros', $rows[0]['team']);
    }

    public function testGetFinalDraftOrderReturnsSavedPicks(): void
    {
        $picks = [
            ['round' => 1, 'pick' => 1, 'team' => 'Metros', 'tid' => 1],
            ['round' => 1, 'pick' => 2, 'team' => 'Sharks', 'tid' => 2],
            ['round' => 2, 'pick' => 1, 'team' => 'Sharks', 'tid' => 2],
        ];

        $this->repo->saveFinalDraftOrder(2099, $picks);

        $round1 = $this->repo->getFinalDraftOrder(2099, 1);
        self::assertCount(2, $round1);
        self::assertSame(1, $round1[0]['pick']);
        self::assertSame(2, $round1[1]['pick']);

        $round2 = $this->repo->getFinalDraftOrder(2099, 2);
        self::assertCount(1, $round2);
    }

    public function testIsDraftStartedReturnsTrueWhenPlayerFilled(): void
    {
        // Insert a draft row with a player name
        $this->insertRow('ibl_draft', [
            'year' => 2099,
            'round' => 1,
            'pick' => 1,
            'team' => 'Metros',
            'tid' => 1,
            'player' => 'John Doe',
            'uuid' => 'draft-started-0000-000000000001',
        ]);

        self::assertTrue($this->repo->isDraftStarted(2099));
    }

    public function testIsDraftStartedReturnsFalseWhenEmpty(): void
    {
        self::assertFalse($this->repo->isDraftStarted(9999));
    }

    public function testUpsertLotteryWinnerAwardInsertsRow(): void
    {
        $this->repo->upsertLotteryWinnerAward(2099, 'Metros');

        // Verify via direct query
        $stmt = $this->db->prepare(
            "SELECT name FROM ibl_team_awards WHERE year = 2099 AND Award = 'IBL Draft Lottery Winners'"
        );
        self::assertNotFalse($stmt);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame('Metros', $row['name']);
    }

    public function testUpsertLotteryWinnerAwardUpdatesExisting(): void
    {
        $this->repo->upsertLotteryWinnerAward(2099, 'Metros');
        $this->repo->upsertLotteryWinnerAward(2099, 'Sharks');

        $stmt = $this->db->prepare(
            "SELECT name FROM ibl_team_awards WHERE year = 2099 AND Award = 'IBL Draft Lottery Winners'"
        );
        self::assertNotFalse($stmt);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame('Sharks', $row['name']);
    }
}
