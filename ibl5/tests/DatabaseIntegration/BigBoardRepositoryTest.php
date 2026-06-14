<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use BigBoard\BigBoardRepository;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration coverage for BigBoardRepository against real MariaDB.
 *
 * Teams 1 (Metros) and 2 (Stars) are pre-seeded by the DB fixture, so they
 * satisfy fk_bigboard_team. Each prospect is inserted via insertDraftClassRow()
 * (returns the autoincrement id) to satisfy fk_bigboard_prospect. All work rolls
 * back per test (DatabaseTestCase).
 */
#[Group('database')]
class BigBoardRepositoryTest extends DatabaseTestCase
{
    private const TEAM_A = 1;
    private const TEAM_B = 2;

    private BigBoardRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new BigBoardRepository($this->db);
    }

    public function testTableSchemaHasFksUniqueKeyAndSignedFkColumns(): void
    {
        // Row 1/2: table + FKs + UNIQUE present; FK columns signed int to match targets.
        $fks = $this->scalarColumn(
            "SELECT GROUP_CONCAT(constraint_name ORDER BY constraint_name) AS c
             FROM information_schema.table_constraints
             WHERE table_schema = DATABASE() AND table_name = 'gm_draft_big_board'
               AND constraint_type = 'FOREIGN KEY'"
        );
        self::assertStringContainsString('fk_bigboard_prospect', $fks);
        self::assertStringContainsString('fk_bigboard_team', $fks);

        $unique = $this->scalarColumn(
            "SELECT COUNT(*) FROM information_schema.table_constraints
             WHERE table_schema = DATABASE() AND table_name = 'gm_draft_big_board'
               AND constraint_type = 'UNIQUE' AND constraint_name = 'uniq_team_prospect'"
        );
        self::assertSame('1', $unique);

        // Signedness: teamid and prospect_id must NOT be unsigned (targets are signed int).
        $unsigned = $this->scalarColumn(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'gm_draft_big_board'
               AND column_name IN ('teamid', 'prospect_id')
               AND column_type LIKE '%unsigned%'"
        );
        self::assertSame('0', $unsigned);
    }

    public function testAddSetRankSetNoteRemoveRoundTrips(): void
    {
        $pid = $this->insertDraftClassRow('Round Trip Prospect', 'SG');

        self::assertTrue($this->repo->addEntry(self::TEAM_A, $pid, 3, 'first look'));

        $rows = $this->repo->getBoardForTeam(self::TEAM_A);
        self::assertCount(1, $rows);
        self::assertSame($pid, $rows[0]['prospect_id']);
        self::assertSame(3, $rows[0]['rank']);
        self::assertSame('first look', $rows[0]['note']);
        self::assertSame('Round Trip Prospect', $rows[0]['name']);

        $entryId = $rows[0]['id'];
        self::assertSame(1, $this->repo->setRank(self::TEAM_A, $entryId, 1));
        self::assertSame(1, $this->repo->setNote(self::TEAM_A, $entryId, 'moved up'));

        $rows = $this->repo->getBoardForTeam(self::TEAM_A);
        self::assertSame(1, $rows[0]['rank']);
        self::assertSame('moved up', $rows[0]['note']);

        self::assertSame(1, $this->repo->removeEntry(self::TEAM_A, $entryId));
        self::assertSame([], $this->repo->getBoardForTeam(self::TEAM_A));
    }

    public function testBoardOrdersByRankThenId(): void
    {
        $p1 = $this->insertDraftClassRow('Alpha', 'PG');
        $p2 = $this->insertDraftClassRow('Bravo', 'SF');
        $p3 = $this->insertDraftClassRow('Charlie', 'C');

        // Insert out of rank order; equal ranks tie-break by insertion id.
        $this->repo->addEntry(self::TEAM_A, $p1, 5, '');
        $this->repo->addEntry(self::TEAM_A, $p2, 2, '');
        $this->repo->addEntry(self::TEAM_A, $p3, 2, '');

        $rows = $this->repo->getBoardForTeam(self::TEAM_A);
        $order = array_map(static fn (array $r): int => $r['prospect_id'], $rows);
        self::assertSame([$p2, $p3, $p1], $order);
    }

    public function testDuplicateAddRejectedByUniqueKey(): void
    {
        // Row 5: a second add of the same (teamid, prospect_id) returns false and
        // does NOT create a second row.
        $pid = $this->insertDraftClassRow('Dup Prospect', 'PF');

        self::assertTrue($this->repo->addEntry(self::TEAM_A, $pid, 1, ''));
        self::assertFalse($this->repo->addEntry(self::TEAM_A, $pid, 9, 'second'));

        self::assertCount(1, $this->repo->getBoardForTeam(self::TEAM_A));
    }

    public function testGetAvailableProspectsExcludesDraftedEvenIfOnBoard(): void
    {
        // Row 6: a drafted=1 prospect on the board is excluded from available.
        $available = $this->insertDraftClassRow('Still Available', 'PG', ['drafted' => 0]);
        $drafted = $this->insertDraftClassRow('Already Gone', 'C', ['drafted' => 1]);

        $this->repo->addEntry(self::TEAM_A, $available, 1, '');
        $this->repo->addEntry(self::TEAM_A, $drafted, 2, '');

        // Full board has both; available has only the undrafted one.
        self::assertCount(2, $this->repo->getBoardForTeam(self::TEAM_A));

        $avail = $this->repo->getAvailableProspects(self::TEAM_A);
        self::assertCount(1, $avail);
        self::assertSame($available, $avail[0]['prospect_id']);
    }

    public function testGetAddableProspectsExcludesDraftedAndAlreadyBoarded(): void
    {
        // Row 3: addable = undrafted prospects not already on this team's board.
        $onBoard = $this->insertDraftClassRow('On Board', 'PG', ['drafted' => 0]);
        $addable = $this->insertDraftClassRow('Addable', 'SG', ['drafted' => 0]);
        $drafted = $this->insertDraftClassRow('Drafted', 'C', ['drafted' => 1]);

        $this->repo->addEntry(self::TEAM_A, $onBoard, 1, '');

        $ids = array_map(
            static fn (array $r): int => $r['id'],
            $this->repo->getAddableProspects(self::TEAM_A)
        );

        self::assertContains($addable, $ids);
        self::assertNotContains($onBoard, $ids);
        self::assertNotContains($drafted, $ids);
    }

    public function testSetRankDoesNotMutateAnotherTeamsEntry(): void
    {
        // Row 7 (IDOR): team A cannot setRank team B's entry.
        $pid = $this->insertDraftClassRow('Team B Prospect', 'SF');
        $this->repo->addEntry(self::TEAM_B, $pid, 4, 'B note');
        $bEntry = $this->repo->getBoardForTeam(self::TEAM_B)[0]['id'];

        self::assertSame(0, $this->repo->setRank(self::TEAM_A, $bEntry, 99));

        $bRows = $this->repo->getBoardForTeam(self::TEAM_B);
        self::assertSame(4, $bRows[0]['rank']);
    }

    public function testSetNoteDoesNotMutateAnotherTeamsEntry(): void
    {
        // Row 7 (IDOR): team A cannot setNote team B's entry.
        $pid = $this->insertDraftClassRow('Team B Prospect', 'SF');
        $this->repo->addEntry(self::TEAM_B, $pid, 4, 'B private');
        $bEntry = $this->repo->getBoardForTeam(self::TEAM_B)[0]['id'];

        self::assertSame(0, $this->repo->setNote(self::TEAM_A, $bEntry, 'A injected'));

        $bRows = $this->repo->getBoardForTeam(self::TEAM_B);
        self::assertSame('B private', $bRows[0]['note']);
    }

    public function testRemoveEntryDoesNotAffectAnotherTeamsEntry(): void
    {
        // Row 7 (IDOR): team A cannot remove team B's entry.
        $pid = $this->insertDraftClassRow('Team B Prospect', 'SF');
        $this->repo->addEntry(self::TEAM_B, $pid, 4, '');
        $bEntry = $this->repo->getBoardForTeam(self::TEAM_B)[0]['id'];

        self::assertSame(0, $this->repo->removeEntry(self::TEAM_A, $bEntry));

        self::assertCount(1, $this->repo->getBoardForTeam(self::TEAM_B));
    }

    private function scalarColumn(string $sql): string
    {
        $result = $this->db->query($sql);
        self::assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_row();
        $result->free();
        self::assertIsArray($row);

        return (string) ($row[0] ?? '');
    }
}
