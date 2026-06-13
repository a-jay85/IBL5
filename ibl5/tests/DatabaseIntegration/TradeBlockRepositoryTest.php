<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use TradeBlock\TradeBlockRepository;

/**
 * Migration 146 (gm-02): gm_trade_block (pid PK, FK -> ibl_plr ON DELETE CASCADE)
 * and gm_trade_seeking (teamid PK, FK -> ibl_team_info ON DELETE CASCADE).
 *
 * Proves: tables/columns exist; setOnBlock -> getAllAvailable derives the live
 * team at read time; a traded player self-corrects to the new team; a retired
 * player is excluded; the pid FK cascades on player delete; the seeking note
 * upserts to a single row.
 */
#[Group('database')]
class TradeBlockRepositoryTest extends DatabaseTestCase
{
    private const PID = 200000910;

    private function repo(): TradeBlockRepository
    {
        return new TradeBlockRepository($this->db);
    }

    public function testTradeBlockTablesExistWithExpectedColumns(): void
    {
        $blockCols = $this->columnNames('gm_trade_block');
        self::assertContains('pid', $blockCols);
        self::assertContains('note', $blockCols);
        self::assertContains('created_at', $blockCols);

        $seekingCols = $this->columnNames('gm_trade_seeking');
        self::assertContains('teamid', $seekingCols);
        self::assertContains('seeking_note', $seekingCols);
        self::assertContains('updated_at', $seekingCols);
    }

    public function testSetOnBlockThenGetAllAvailableDerivesTeamName(): void
    {
        $this->insertTestPlayer(self::PID, 'Block Tester', ['teamid' => 1]);

        self::assertTrue($this->repo()->setOnBlock(self::PID, 'available'));

        $row = $this->findAvailable(self::PID);
        self::assertNotNull($row, 'player should appear on the board');
        self::assertSame('available', $row['note']);
        self::assertNotSame('', (string) $row['team_name'], 'team_name is derived via JOIN');
    }

    public function testTradedPlayerAppearsUnderNewTeam(): void
    {
        $this->insertTestPlayer(self::PID, 'Trade Tester', ['teamid' => 1]);
        $this->repo()->setOnBlock(self::PID, '');

        $before = $this->findAvailable(self::PID);
        self::assertNotNull($before);
        $teamBefore = (int) $before['teamid'];

        // Trade the player to a different team — no stored teamid to go stale.
        $newTeam = $teamBefore === 3 ? 2 : 3;
        $this->db->query("UPDATE ibl_plr SET teamid = {$newTeam} WHERE pid = " . self::PID);

        $after = $this->findAvailable(self::PID);
        self::assertNotNull($after);
        self::assertSame($newTeam, (int) $after['teamid'], 'derived team self-corrects after a trade');
    }

    public function testRetiredPlayerIsExcluded(): void
    {
        $this->insertTestPlayer(self::PID, 'Retire Tester', ['teamid' => 1]);
        $this->repo()->setOnBlock(self::PID, '');
        self::assertNotNull($this->findAvailable(self::PID));

        $this->db->query('UPDATE ibl_plr SET retired = 1 WHERE pid = ' . self::PID);

        self::assertNull($this->findAvailable(self::PID), 'retired player must drop off the board');
    }

    public function testRemoveFromBlock(): void
    {
        $this->insertTestPlayer(self::PID, 'Remove Tester', ['teamid' => 1]);
        $this->repo()->setOnBlock(self::PID, '');
        self::assertNotNull($this->findAvailable(self::PID));

        self::assertTrue($this->repo()->removeFromBlock(self::PID));
        self::assertNull($this->findAvailable(self::PID));
    }

    public function testDeletingPlayerCascadesBlockRow(): void
    {
        $this->insertTestPlayer(self::PID, 'Cascade Tester', ['teamid' => 1]);
        $this->repo()->setOnBlock(self::PID, '');

        $this->db->query('DELETE FROM ibl_plr WHERE pid = ' . self::PID);

        $row = $this->db->query('SELECT COUNT(*) AS c FROM gm_trade_block WHERE pid = ' . self::PID)->fetch_assoc();
        self::assertNotNull($row);
        self::assertSame(0, (int) $row['c'], 'deleting the player must cascade-delete its block row');
    }

    public function testOrphanPidInsertIsRejected(): void
    {
        try {
            $this->db->query('INSERT INTO gm_trade_block (pid, note) VALUES (999999000, \'\')');
            self::fail('orphan-pid block insert was not rejected');
        } catch (\mysqli_sql_exception $e) {
            self::assertSame(1452, $e->getCode(), 'expected FK violation errno 1452');
        }
    }

    public function testUpsertSeekingNoteUpdatesSingleRow(): void
    {
        $repo = $this->repo();

        self::assertTrue($repo->upsertSeekingNote(1, 'first'));
        self::assertSame('first', $repo->getSeekingNoteForTeam(1));

        self::assertTrue($repo->upsertSeekingNote(1, 'second'));
        self::assertSame('second', $repo->getSeekingNoteForTeam(1));

        $row = $this->db->query('SELECT COUNT(*) AS c FROM gm_trade_seeking WHERE teamid = 1')->fetch_assoc();
        self::assertNotNull($row);
        self::assertSame(1, (int) $row['c'], 'upsert must keep exactly one row per team');
    }

    public function testGetSeekingNoteForTeamDefaultsToEmpty(): void
    {
        self::assertSame('', $this->repo()->getSeekingNoteForTeam(999999));
    }

    public function testGetBlockPidsForTeamReturnsNoteMap(): void
    {
        $this->insertTestPlayer(self::PID, 'Map Tester', ['teamid' => 1]);
        $this->repo()->setOnBlock(self::PID, 'trade me');

        $map = $this->repo()->getBlockPidsForTeam(1);
        self::assertArrayHasKey(self::PID, $map);
        self::assertSame('trade me', $map[self::PID]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findAvailable(int $pid): ?array
    {
        foreach ($this->repo()->getAllAvailable() as $row) {
            if ((int) $row['pid'] === $pid) {
                return $row;
            }
        }
        return null;
    }

    /**
     * @return list<string>
     */
    private function columnNames(string $table): array
    {
        $result = $this->db->query("SHOW COLUMNS FROM `{$table}`");
        self::assertInstanceOf(\mysqli_result::class, $result);

        $names = [];
        while (true) {
            $row = $result->fetch_assoc();
            if (!is_array($row)) {
                break;
            }
            $names[] = (string) $row['Field'];
        }
        $result->free();

        return $names;
    }
}
