<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\Trading;

use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;
use Trading\TradeCashRepository;
use Trading\TradeExecutionService;
use Trading\TradeItemType;
use Trading\TradeOfferRepository;
use Trading\TradeProcessor;
use Trading\TradeValidator;
use Repositories\SalaryCapRepository;
use Repositories\TeamIdentityRepository;

/**
 * End-to-end (real-wiring) proof of the accept path's NEW behavior: cap/roster
 * validation now runs before execution. Guards the highest-risk regression —
 * a legitimate 2-team trade that executed fine before must still pass the new
 * validation — and proves the IDOR gate rejects a non-party GM against the real
 * schema/view (vw_current_salary). processTrade commits internally, so this
 * uses manual tearDown cleanup like TradeProcessorIntegrationTest.
 */
#[Group('database')]
class TradeExecutionServiceIntegrationTest extends DatabaseTestCase
{
    private TradeExecutionService $service;

    /** @var list<int> */
    private array $createdPids = [];
    /** @var list<int> */
    private array $createdOfferIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->commit();
        $_SERVER['SERVER_NAME'] = 'localhost';

        $teamIdentity = new TeamIdentityRepository($this->db);
        $offerRepo = new TradeOfferRepository($this->db, 'localhost');
        $processor = new TradeProcessor($this->db, $teamIdentity);
        $validator = new TradeValidator($this->db);
        $salaryCap = new SalaryCapRepository($this->db);
        $cashRepo = new TradeCashRepository($this->db);

        $this->service = new TradeExecutionService(
            $offerRepo,
            $processor,
            $validator,
            $salaryCap,
            $teamIdentity,
            $cashRepo,
        );
    }

    protected function tearDown(): void
    {
        if (!isset($this->db)) {
            parent::tearDown();
            return;
        }

        $this->db->query("DELETE FROM ibl_trade_queue");
        $this->db->query("DELETE FROM nuke_stories WHERE sid > 2");

        foreach ($this->createdOfferIds as $offerId) {
            $this->db->query("DELETE FROM ibl_trade_info WHERE tradeofferid = $offerId");
            $this->db->query("DELETE FROM ibl_trade_cash WHERE trade_offer_id = $offerId");
            $this->db->query("DELETE FROM ibl_trade_offers WHERE id = $offerId");
        }
        foreach ($this->createdPids as $pid) {
            $this->db->query("DELETE FROM ibl_plr WHERE pid = $pid");
        }

        unset($_SERVER['SERVER_NAME']);

        try {
            $this->db->close();
        } catch (\Throwable) {
        }

        parent::tearDown();
    }

    /**
     * Matrix #15 — a legitimate party GM's 1-on-1 accept still succeeds end to
     * end through the new validate-then-execute path. The swap is cap- and
     * roster-neutral (equal-salary players, one-for-one), so it passes for any
     * cap-legal seed roster.
     */
    public function testValidTwoTeamAcceptExecutesThroughValidation(): void
    {
        $pidA = 200040050;
        $pidB = 200040051;
        $this->seedEqualSalaryPlayer($pidA, 'Exec A', 1, 'PG');
        $this->seedEqualSalaryPlayer($pidB, 'Exec B', 2, 'SG');

        $offerId = $this->seedPendingTrade([
            ['id' => $pidA, 'type' => TradeItemType::Player->value, 'from' => 'Metros', 'to' => 'Stars'],
            ['id' => $pidB, 'type' => TradeItemType::Player->value, 'from' => 'Stars', 'to' => 'Metros'],
        ]);

        $result = $this->service->validateAndExecute($offerId, 'Metros');

        self::assertTrue($result['success'], 'cap/roster-neutral 1-for-1 swap must pass accept-time validation');
        self::assertSame(2, $this->getPlayerTeamId($pidA));
        self::assertSame(1, $this->getPlayerTeamId($pidB));
    }

    /**
     * Matrix #12 (IDOR), real wiring — a GM whose team is NOT a party cannot
     * execute: validateAndExecute returns failure and no player moves.
     */
    public function testNonPartyGmCannotExecuteAndNothingMoves(): void
    {
        $pidA = 200040052;
        $pidB = 200040053;
        $this->seedEqualSalaryPlayer($pidA, 'Idor A', 1, 'PG');
        $this->seedEqualSalaryPlayer($pidB, 'Idor B', 2, 'SG');

        $offerId = $this->seedPendingTrade([
            ['id' => $pidA, 'type' => TradeItemType::Player->value, 'from' => 'Metros', 'to' => 'Stars'],
            ['id' => $pidB, 'type' => TradeItemType::Player->value, 'from' => 'Stars', 'to' => 'Metros'],
        ]);

        // Cougars (teamid 3) is not a party to this Metros<->Stars offer.
        $result = $this->service->validateAndExecute($offerId, 'Cougars');

        self::assertFalse($result['success']);
        self::assertSame(1, $this->getPlayerTeamId($pidA), 'no player may move on an unauthorized accept');
        self::assertSame(2, $this->getPlayerTeamId($pidB));
        // The offer itself survives (was never processed).
        self::assertNotSame([], (new TradeOfferRepository($this->db, 'localhost'))->getTradesByOfferId($offerId));
    }

    public function testDerivePartiesReadsRealRows(): void
    {
        $pidA = 200040054;
        $pidB = 200040055;
        $pidC = 200040056;
        $this->seedEqualSalaryPlayer($pidA, 'Party A', 1, 'PG');
        $this->seedEqualSalaryPlayer($pidB, 'Party B', 2, 'SG');
        $this->seedEqualSalaryPlayer($pidC, 'Party C', 3, 'C');

        $offerId = $this->seedPendingTrade([
            ['id' => $pidA, 'type' => TradeItemType::Player->value, 'from' => 'Metros', 'to' => 'Stars'],
            ['id' => $pidB, 'type' => TradeItemType::Player->value, 'from' => 'Stars', 'to' => 'Cougars'],
            ['id' => $pidC, 'type' => TradeItemType::Player->value, 'from' => 'Cougars', 'to' => 'Metros'],
        ]);

        self::assertSame(['Metros', 'Stars', 'Cougars'], $this->service->deriveParties($offerId));
    }

    // ── Helpers ─────────────────────────────────────────────────

    private function seedEqualSalaryPlayer(int $pid, string $name, int $teamId, string $pos): void
    {
        // cy=1 -> vw_current_salary.current_salary = salary_yr1. Equal salaries on
        // both sides make a 1-for-1 swap cap-neutral.
        $this->insertTestPlayer($pid, $name, [
            'teamid' => $teamId,
            'pos' => $pos,
            'cy' => 1,
            'cyt' => 3,
            'salary_yr1' => 100,
            'salary_yr2' => 100,
            'ordinal' => 1,
        ]);
        $this->createdPids[] = $pid;
    }

    /** @param list<array{id: int, type: string, from: string, to: string}> $items */
    private function seedPendingTrade(array $items): int
    {
        $offerId = $this->insertTradeOfferRow();
        $this->createdOfferIds[] = $offerId;

        foreach ($items as $item) {
            $this->insertTradeInfoRow($offerId, $item['id'], $item['type'], $item['from'], $item['to']);
        }

        return $offerId;
    }

    private function getPlayerTeamId(int $pid): int
    {
        $stmt = $this->db->prepare("SELECT teamid FROM ibl_plr WHERE pid = ?");
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row !== null ? (int) $row['teamid'] : -1;
    }
}
