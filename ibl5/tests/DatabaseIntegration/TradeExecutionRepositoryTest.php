<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Trading\TradeExecutionRepository;

/**
 * Tests TradeExecutionRepository against real MariaDB — trade queue CRUD and execution.
 *
 * IMPORTANT: testClearTradeQueueTruncatesTable MUST be last in this file.
 * TRUNCATE TABLE causes an implicit commit, making tearDown rollback a no-op.
 * The table starts empty from seed, so no residual data concerns.
 */
class TradeExecutionRepositoryTest extends DatabaseTestCase
{
    private TradeExecutionRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new TradeExecutionRepository($this->db);
    }

    // ── Queue insert + retrieve ─────────────────────────────────

    public function testInsertAndGetQueuedTrades(): void
    {
        $params = ['pid' => 100, 'teamid' => 5];
        $this->repo->insertTradeQueue('player_transfer', $params, 'Player X to Team Y');

        $queued = $this->repo->getQueuedTrades();

        self::assertNotEmpty($queued);
        $last = $queued[count($queued) - 1];
        self::assertSame('player_transfer', $last['operation_type']);
        self::assertSame('Player X to Team Y', $last['tradeline']);

        $decodedParams = json_decode($last['params'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(100, $decodedParams['pid']);
        self::assertSame(5, $decodedParams['teamid']);
    }

    public function testGetQueuedTradesOrderedById(): void
    {
        $this->repo->insertTradeQueue('player_transfer', ['pid' => 1], 'First trade');
        $this->repo->insertTradeQueue('pick_transfer', ['pickId' => 2], 'Second trade');

        $queued = $this->repo->getQueuedTrades();

        self::assertGreaterThanOrEqual(2, count($queued));
        $lastTwo = array_slice($queued, -2);
        self::assertSame('player_transfer', $lastTwo[0]['operation_type']);
        self::assertSame('pick_transfer', $lastTwo[1]['operation_type']);
        self::assertLessThan($lastTwo[1]['id'], $lastTwo[0]['id']);
    }

    // ── Queue delete ────────────────────────────────────────────

    public function testDeleteQueuedTradeRemovesEntry(): void
    {
        $queueId = $this->insertTradeQueueRow('player_transfer', ['pid' => 99], 'Delete me');

        $affected = $this->repo->deleteQueuedTrade($queueId);

        self::assertSame(1, $affected);

        // Verify it's gone
        $queued = $this->repo->getQueuedTrades();
        $ids = array_column($queued, 'id');
        self::assertNotContains($queueId, $ids);
    }

    // ── Player transfer execution ───────────────────────────────

    public function testExecuteQueuedPlayerTransferChangesTeamId(): void
    {
        $this->insertTestPlayer(200031001, 'Exec Transfer P', ['teamid' => 1]);

        $affected = $this->repo->executeQueuedPlayerTransfer(200031001, 7);

        self::assertSame(1, $affected);

        // Verify via raw SELECT
        $stmt = $this->db->prepare("SELECT teamid FROM ibl_plr WHERE pid = ?");
        self::assertNotFalse($stmt);
        $pid = 200031001;
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(7, $row['teamid']);
    }

    // ── Pick transfer execution ─────────────────────────────────

    public function testExecuteQueuedPickTransferUpdatesOwner(): void
    {
        $pickId = $this->insertDraftPickRow(1, 1, 2034, 1);

        $affected = $this->repo->executeQueuedPickTransfer($pickId, 'Sharks', 2);

        self::assertSame(1, $affected);

        // Verify via raw SELECT
        $stmt = $this->db->prepare("SELECT ownerofpick, owner_teamid FROM ibl_draft_picks WHERE pickid = ?");
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $pickId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame('Sharks', $row['ownerofpick']);
        self::assertSame(2, $row['owner_teamid']);
    }

    // ── Clear trade info ────────────────────────────────────────

    public function testClearTradeInfoDeletesAllRows(): void
    {
        $offerId = $this->insertTradeOfferRow();
        $this->insertTradeInfoRow($offerId, 1, '1', 'Metros', 'Sharks');
        $this->insertTradeInfoRow($offerId, 2, '0', 'Sharks', 'Metros');

        $this->repo->clearTradeInfo();

        $stmt = $this->db->prepare("SELECT COUNT(*) AS cnt FROM ibl_trade_info");
        self::assertNotFalse($stmt);
        $stmt->execute();
        /** @var array{cnt: int} $row */
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertSame(0, $row['cnt']);
    }

    // ── TRUNCATE (implicit commit) — MUST BE LAST ───────────────

    public function testClearTradeQueueTruncatesTable(): void
    {
        // Table starts empty from seed. Insert a row, then TRUNCATE.
        $this->insertTradeQueueRow('player_transfer', ['pid' => 1], 'Will be truncated');

        // TRUNCATE causes implicit commit — outer transaction is committed
        $this->repo->clearTradeQueue();

        $stmt = $this->db->prepare("SELECT COUNT(*) AS cnt FROM ibl_trade_queue");
        self::assertNotFalse($stmt);
        $stmt->execute();
        /** @var array{cnt: int} $row */
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertSame(0, $row['cnt']);
    }
}
