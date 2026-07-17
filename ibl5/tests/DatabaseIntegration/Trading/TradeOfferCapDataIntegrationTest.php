<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\Trading;

use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;
use Trading\TradeOffer;
use Trading\TradeValidator;

/**
 * Seam-B characterization net: pins the cash-record contribution to the trade
 * salary-cap totals END-TO-END against the REAL `ibl_cash_considerations` SQL
 * (`BuyoutLedgerRepository::getTeamCashForSalary()` +
 * `sumCurrentSeasonSalaryFromRows()`) and the REAL `Season::advancesContractYears()`
 * contract-year rollover — observed through a capturing mock validator injected
 * into the REAL `TradeOffer` constructor.
 *
 * Why this exists alongside the in-memory pins. The unit-level pins in
 * `Tests\Trading\TradeOfferTest` / `TradeCapCalculatorTest` STUB
 * `getTeamCashForSalary()` and `Season`, so the actual SQL query, the
 * smallint columns, and the phase-driven `cy++` rollover are never exercised.
 * This is the branch only a real DB can pin, and it is the money-sensitive one.
 *
 * NOTE — this net was authored AFTER the cap-math refactor (PR #1143, which
 * extracted `TradeCapCalculator`) had already merged, inverting the original
 * "net-before-refactor" sequencing. These goldens therefore characterize
 * POST-refactor behavior. A human must verify the frozen values against the
 * documented cap rules before relying on them (`auto_merge: false`).
 *
 * Side-effect containment:
 *  - The injected validator returns `valid => false` from `validateSalaryCaps()`,
 *    so `createTradeOffer()` returns at the cap gate BEFORE `insertTradeOfferData()`
 *    or `sendTradeNotification()` — no `ibl_trade_info` / `ibl_trade_cash` write
 *    and no Discord dispatch ever happens.
 *  - `generateNextTradeOfferId()` does INSERT one `ibl_trade_offers` row before
 *    the cap gate, and the seeded `ibl_cash_considerations` rows are written too —
 *    but this test does NOT commit (unlike TradeProcessorIntegrationTest), so
 *    `DatabaseTestCase::tearDown()`'s `rollback()` discards everything.
 *
 * @see \Trading\TradeCapCalculator::calculateSalaryCapData()
 * @see \Trading\BuyoutLedgerRepository::sumCurrentSeasonSalaryFromRows()
 */
#[Group('database')]
class TradeOfferCapDataIntegrationTest extends DatabaseTestCase
{
    private const METROS_TID = 1;

    /** @var array{userCurrentSeasonCapTotal: int, partnerCurrentSeasonCapTotal: int, userCapSentToPartner: int, partnerCapSentToUser: int}|null */
    private ?array $capturedCapData = null;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['SERVER_NAME'] = 'localhost';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['SERVER_NAME']);
        parent::tearDown();
    }

    /**
     * Seed one cash-consideration row for a team. Only the columns the cap walk
     * reads (`teamid`, `cy`, `salary_yr1..6`) carry meaning; `type`/`label` are
     * given explicit values so the row is realistic.
     */
    private function seedCash(int $teamid, int $cy, int $yr1, int $yr2 = 0): void
    {
        $this->insertRow('ibl_cash_considerations', [
            'teamid' => $teamid,
            'type' => 'cash',
            'label' => 'Cash test',
            'cy' => $cy,
            'cyt' => 1,
            'salary_yr1' => $yr1,
            'salary_yr2' => $yr2,
            'salary_yr3' => 0,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
        ]);
    }

    /**
     * Build the REAL TradeOffer (only the validator is a double) for a zero-player,
     * zero-new-cash offer between Metros (user) and Stars (partner), run
     * createTradeOffer(), and return the cap-data array the calculator produced —
     * captured off the validator's validateSalaryCaps() argument.
     *
     * NOTE: \Season\Season is aliased to Tests\WideUnit\Mocks\Season by TestAliasesBootstrap,
     * so new Season($db) never reads the DB. Control the phase via $season->phase.
     *
     * @param \Season\Season|null $season Season to inject (null = default mock, phase 'Regular Season')
     * @return array{userCurrentSeasonCapTotal: int, partnerCurrentSeasonCapTotal: int, userCapSentToPartner: int, partnerCapSentToUser: int}
     */
    private function captureCapData(?\Season\Season $season = null): array
    {
        $validator = $this->createMock(TradeValidator::class);
        $validator->method('validateMinimumCashAmounts')->willReturn(['valid' => true, 'error' => null]);
        $validator->method('getCurrentSeasonCashConsiderations')->willReturn([
            'cashSentToThem' => 0,
            'cashSentToMe' => 0,
        ]);
        $validator->expects($this->once())->method('validateSalaryCaps')
            ->with(self::callback(function (array $capData): bool {
                /** @var array{userCurrentSeasonCapTotal: int, partnerCurrentSeasonCapTotal: int, userCapSentToPartner: int, partnerCapSentToUser: int} $capData */
                $this->capturedCapData = $capData;
                return true;
            }))
            ->willReturn([
                'valid' => false,
                'errors' => ['characterization stop'],
                'userPostTradeCapTotal' => 0,
                'partnerPostTradeCapTotal' => 0,
            ]);

        $offer = new TradeOffer(
            $this->db,
            new \Repositories\TeamIdentityRepository($this->db),
            'localhost',
            null, // offerRepository  (real)
            null, // assetRepository  (real)
            null, // cashRepository   (real)
            null, // cashConsiderationRepository (real — drives the SQL under test)
            $season, // null → TradeOffer creates new \Season\Season (aliased mock, phase 'Regular Season')
            $validator,
        );

        $tradeData = [
            'offeringTeam' => 'Metros',
            'listeningTeam' => 'Stars',
            'switchCounter' => 0,
            'fieldsCounter' => 0,
            'check' => [],
            'index' => [],
            'type' => [],
            'contract' => [],
            'userSendsCash' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0],
            'partnerSendsCash' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0],
        ];

        $result = $offer->createTradeOffer($tradeData);

        // The injected validator short-circuits the cap gate — no write, no Discord.
        self::assertFalse($result['success']);
        self::assertNotNull($this->capturedCapData, 'validateSalaryCaps was never reached');

        return $this->capturedCapData;
    }

    /**
     * Regular Season (seed default phase) → advancesContractYears() = false →
     * cy stays 1 → salary_yr1 is the current slot. The 500 cash row lands in the
     * user (Metros) total via the real SQL walk; Stars has none → 0.
     */
    public function testCashRecordSalaryIncludedInUserCapTotalRegularSeason(): void
    {
        $this->seedCash(self::METROS_TID, cy: 1, yr1: 500);

        $capData = $this->captureCapData();

        self::assertSame(500, $capData['userCurrentSeasonCapTotal']);
        self::assertSame(0, $capData['partnerCurrentSeasonCapTotal']);
    }

    /**
     * Playoffs counts as offseason for trade cap math → advancesContractYears() = true
     * → cy 1→2 → salary_yr2 is the current slot. The user total must reflect
     * the next-year amount (350), NOT salary_yr1 (999).
     *
     * NOTE: \Season\Season is aliased to the mock (TestAliasesBootstrap), so we set
     * phase directly on the injected mock instead of via a DB UPDATE.
     */
    public function testCashRecordSalaryUsesNextYearSlotInOffseason(): void
    {
        $season = new \Season\Season($this->db);
        $season->phase = 'Playoffs';

        $this->seedCash(self::METROS_TID, cy: 1, yr1: 999, yr2: 350);

        $capData = $this->captureCapData($season);

        self::assertSame(350, $capData['userCurrentSeasonCapTotal']);
    }

    /**
     * Negative cash-record salary (incoming cash) lowers the cap total — pins the
     * "may be negative" contract on the cash-record sum.
     */
    public function testNegativeCashRecordSalaryLowersCapTotal(): void
    {
        $this->seedCash(self::METROS_TID, cy: 1, yr1: -200);

        $capData = $this->captureCapData();

        self::assertSame(-200, $capData['userCurrentSeasonCapTotal']);
    }

    /**
     * A team with no cash-consideration rows contributes 0 — pins the empty-result
     * SQL path (Stars is never seeded).
     */
    public function testNoCashRecordsContributesZero(): void
    {
        $this->seedCash(self::METROS_TID, cy: 1, yr1: 500);

        $capData = $this->captureCapData();

        self::assertSame(0, $capData['partnerCurrentSeasonCapTotal']);
    }
}
