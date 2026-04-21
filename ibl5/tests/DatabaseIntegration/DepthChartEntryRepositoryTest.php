<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use DepthChartEntry\DepthChartEntryRepository;

/**
 * Tests DepthChartEntryRepository against real MariaDB — player roster queries,
 * depth chart column updates, and team history timestamp updates.
 */
class DepthChartEntryRepositoryTest extends DatabaseTestCase
{
    private DepthChartEntryRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new DepthChartEntryRepository($this->db);
    }

    // ── getPlayersOnTeam ────────────────────────────────────────

    public function testGetPlayersOnTeamReturnsActivePlayers(): void
    {
        $this->insertTestPlayer(200080001, 'DC Active P1', ['tid' => 1, 'retired' => 0, 'ordinal' => 1]);
        $this->insertTestPlayer(200080002, 'DC Active P2', ['tid' => 1, 'retired' => 0, 'ordinal' => 2]);

        $players = $this->repo->getPlayersOnTeam(1);

        $pids = array_column($players, 'pid');
        self::assertContains(200080001, $pids);
        self::assertContains(200080002, $pids);
    }

    public function testGetPlayersOnTeamExcludesRetiredAndWaiverOrdinal(): void
    {
        // Retired player (retired=1)
        $this->insertTestPlayer(200080003, 'DC Retired', ['tid' => 1, 'retired' => 1, 'ordinal' => 1]);
        // Waiver-ordinal player (ordinal=999, above WAIVERS_ORDINAL=960)
        $this->insertTestPlayer(200080004, 'DC Waiver Ord', ['tid' => 1, 'retired' => 0, 'ordinal' => 999]);

        $players = $this->repo->getPlayersOnTeam(1);

        $pids = array_column($players, 'pid');
        self::assertNotContains(200080003, $pids);
        self::assertNotContains(200080004, $pids);
    }

    // ── updatePlayerDepthChart ──────────────────────────────────

    public function testUpdatePlayerDepthChartSetsAllColumns(): void
    {
        $this->insertTestPlayer(200080005, 'DC Update Plyr', ['tid' => 1]);

        $depthChartValues = [
            'pg' => 3,
            'sg' => 2,
            'sf' => 1,
            'pf' => 0,
            'c' => 0,
            'canPlayInGame' => 1,
            'min' => 32,
        ];

        $result = $this->repo->updatePlayerDepthChart('DC Update Plyr', $depthChartValues);

        self::assertTrue($result);

        $stmt = $this->db->prepare(
            'SELECT dc_PGDepth, dc_SGDepth, dc_SFDepth, dc_PFDepth, dc_CDepth,
                    dc_canPlayInGame, dc_minutes, dc_of, dc_df, dc_oi, dc_di, dc_bh
             FROM ibl_plr WHERE pid = ?'
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $pid);
        $pid = 200080005;
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(3, $row['dc_PGDepth']);
        self::assertSame(2, $row['dc_SGDepth']);
        self::assertSame(1, $row['dc_SFDepth']);
        self::assertSame(0, $row['dc_PFDepth']);
        self::assertSame(0, $row['dc_CDepth']);
        self::assertSame(1, $row['dc_canPlayInGame']);
        self::assertSame(32, $row['dc_minutes']);
        // Role columns are hardcoded to 0 in the SQL
        self::assertSame(0, $row['dc_of']);
        self::assertSame(0, $row['dc_df']);
        self::assertSame(0, $row['dc_oi']);
        self::assertSame(0, $row['dc_di']);
        self::assertSame(0, $row['dc_bh']);
    }

    public function testUpdatePlayerDepthChartReturnsTrueOnSuccess(): void
    {
        $this->insertTestPlayer(200080006, 'DC Success Plyr', ['tid' => 1]);

        $result = $this->repo->updatePlayerDepthChart('DC Success Plyr', [
            'pg' => 1, 'sg' => 0, 'sf' => 0, 'pf' => 0, 'c' => 0,
            'canPlayInGame' => 1, 'min' => 20,
        ]);

        self::assertTrue($result);
    }

    // ── updateTeamHistory ───────────────────────────────────────

    public function testUpdateTeamHistoryUpdatesTimestamps(): void
    {
        $result = $this->repo->updateTeamHistory('Metros');

        self::assertTrue($result);

        $stmt = $this->db->prepare('SELECT depth, sim_depth FROM ibl_team_info WHERE team_name = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $tn);
        $tn = 'Metros';
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        // Timestamps should be within the last 5 seconds
        $now = time();
        $depth = strtotime($row['depth']);
        $simDepth = strtotime($row['sim_depth']);
        self::assertNotFalse($depth);
        self::assertNotFalse($simDepth);
        self::assertLessThanOrEqual(5, $now - $depth);
        self::assertLessThanOrEqual(5, $now - $simDepth);
    }
}
