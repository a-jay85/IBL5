<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

use Trading\TradeExecutionRepository;

/**
 * Tests TradeExecutionRepository against real MariaDB.
 */
#[Group('database')]
class TradeExecutionRepositoryTest extends DatabaseTestCase
{
    private TradeExecutionRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new TradeExecutionRepository($this->db);
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
}
