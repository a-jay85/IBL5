<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Trading\TradeCashRepository;
use Trading\TradeAssetRepository;
use Trading\TradeFormRepository;
use Trading\TradeItemType;
use Trading\TradeOfferRepository;

/**
 * Tests Trading repositories against real MariaDB — trade offers, items, player/pick lookups.
 *
 * IMPORTANT: testDeleteTradeOfferRemovesAllRelatedRows MUST be last in this file.
 * deleteTradeOffer() calls begin_transaction() internally, which implicitly commits
 * the outer DatabaseTestCase transaction. tearDown rollback becomes a no-op.
 */
class TradingRepositoryTest extends DatabaseTestCase
{
    private TradeOfferRepository $offerRepo;
    private TradeAssetRepository $assetRepo;
    private TradeFormRepository $formRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['SERVER_NAME'] = 'localhost';
        $cashRepo = new TradeCashRepository($this->db);
        $this->offerRepo = new TradeOfferRepository($this->db, $cashRepo);
        $this->assetRepo = new TradeAssetRepository($this->db);
        $this->formRepo = new TradeFormRepository($this->db);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['SERVER_NAME']);
        parent::tearDown();
    }

    // ── Team queries (seed validation) ──────────────────────────

    public function testGetAllTeamsWithCityReturns28Teams(): void
    {
        $teams = $this->formRepo->getAllTeamsWithCity();

        self::assertCount(28, $teams);
        self::assertArrayHasKey('team_city', $teams[0]);
    }

    // ── Player batch fetch (dynamic IN) ─────────────────────────

    public function testGetPlayersByIdsWithMultipleIds(): void
    {
        $this->insertTestPlayer(200030101, 'Trade Batch P1', ['teamid' => 1]);
        $this->insertTestPlayer(200030102, 'Trade Batch P2', ['teamid' => 2]);

        $result = $this->assetRepo->getPlayersByIds([200030101, 200030102]);

        self::assertCount(2, $result);
        self::assertArrayHasKey(200030101, $result);
        self::assertArrayHasKey(200030102, $result);
        self::assertSame('Trade Batch P1', $result[200030101]['name']);
        self::assertSame('Trade Batch P2', $result[200030102]['name']);
    }

    public function testGetPlayersByIdsEmptyArrayReturnsEmpty(): void
    {
        $result = $this->assetRepo->getPlayersByIds([]);

        self::assertSame([], $result);
    }

    // ── Draft pick batch fetch (dynamic IN) ─────────────────────

    public function testGetDraftPicksByIdsWithMultipleIds(): void
    {
        $pickId1 = $this->insertDraftPickRow(1, 1, 2030, 1);
        $pickId2 = $this->insertDraftPickRow(2, 2, 2030, 2);

        $result = $this->assetRepo->getDraftPicksByIds([$pickId1, $pickId2]);

        self::assertCount(2, $result);
        self::assertArrayHasKey($pickId1, $result);
        self::assertArrayHasKey($pickId2, $result);
    }

    public function testGetDraftPicksByIdsEmptyArrayReturnsEmpty(): void
    {
        $result = $this->assetRepo->getDraftPicksByIds([]);

        self::assertSame([], $result);
    }

    // ── Trade offer ID generation ───────────────────────────────

    public function testGenerateNextTradeOfferIdIsMonotonicallyIncreasing(): void
    {
        $id1 = $this->offerRepo->generateNextTradeOfferId();
        $id2 = $this->offerRepo->generateNextTradeOfferId();

        self::assertGreaterThan(0, $id1);
        self::assertGreaterThan($id1, $id2);
    }

    // ── Trade item insert + retrieve ────────────────────────────

    public function testInsertTradeItemAndRetrieveByOfferId(): void
    {
        $offerId = $this->insertTradeOfferRow();
        $this->insertTestPlayer(200030103, 'Trade Item Plr', ['teamid' => 1]);

        $this->offerRepo->insertTradeItem($offerId, 200030103, TradeItemType::Player, 'Metros', 'Sharks', 'Metros');

        $trades = $this->offerRepo->getTradesByOfferId($offerId);
        self::assertCount(1, $trades);
        self::assertSame($offerId, $trades[0]['tradeofferid']);
        self::assertSame(200030103, $trades[0]['itemid']);
        self::assertSame('1', $trades[0]['itemtype']);
        self::assertSame('Metros', $trades[0]['trade_from']);
        self::assertSame('Sharks', $trades[0]['trade_to']);
        // SERVER_NAME=localhost causes approval to be set to 'test'
        self::assertSame('test', $trades[0]['approval']);
    }

    public function testGetTradesByOfferIdForUpdateReturnsRows(): void
    {
        $offerId = $this->insertTradeOfferRow();
        $this->insertTradeInfoRow($offerId, 1, '1', 'Metros', 'Sharks');

        $trades = $this->offerRepo->getTradesByOfferIdForUpdate($offerId);

        self::assertCount(1, $trades);
        self::assertSame($offerId, $trades[0]['tradeofferid']);
    }

    // ── Mark completed / delete info ────────────────────────────

    public function testMarkTradeInfoCompletedSetsApproval(): void
    {
        $offerId = $this->insertTradeOfferRow();
        $this->insertTradeInfoRow($offerId, 1, '1', 'Metros', 'Sharks', 'Metros');

        $this->offerRepo->markTradeInfoCompleted($offerId);

        $trades = $this->offerRepo->getTradesByOfferId($offerId);
        self::assertCount(1, $trades);
        self::assertSame('completed', $trades[0]['approval']);
    }

    public function testDeleteTradeInfoByOfferIdRemovesAllItems(): void
    {
        $offerId = $this->insertTradeOfferRow();
        $this->insertTradeInfoRow($offerId, 1, '1', 'Metros', 'Sharks');
        $this->insertTradeInfoRow($offerId, 2, '0', 'Sharks', 'Metros');

        $this->offerRepo->deleteTradeInfoByOfferId($offerId);

        $trades = $this->offerRepo->getTradesByOfferId($offerId);
        self::assertCount(0, $trades);
    }

    // ── getAllTradeOffers excludes completed ─────────────────────

    public function testGetAllTradeOffersExcludesCompleted(): void
    {
        $offerId1 = $this->insertTradeOfferRow();
        $this->insertTradeInfoRow($offerId1, 1, '1', 'Metros', 'Sharks', 'completed');

        $offerId2 = $this->insertTradeOfferRow();
        $this->insertTradeInfoRow($offerId2, 2, '1', 'Sharks', 'Metros', 'Metros');

        $offers = $this->offerRepo->getAllTradeOffers();

        $offerIds = array_column($offers, 'tradeofferid');
        self::assertNotContains($offerId1, $offerIds);
        self::assertContains($offerId2, $offerIds);
    }

    // ── Team player count ───────────────────────────────────────

    public function testGetTeamPlayerCountRegularSeason(): void
    {
        // Use a team ID unlikely to have seed players: teamid=3
        // First count existing players on teamid=3
        $baseline = $this->formRepo->getTeamPlayerCount(3);

        $this->insertTestPlayer(200030104, 'Trade Count P1', ['teamid' => 3, 'ordinal' => 100]);
        $this->insertTestPlayer(200030105, 'Trade Count P2', ['teamid' => 3, 'ordinal' => 200]);
        $this->insertTestPlayer(200030106, 'Trade Count P3', ['teamid' => 3, 'ordinal' => 300]);

        $count = $this->formRepo->getTeamPlayerCount(3);

        self::assertSame($baseline + 3, $count);
    }

    public function testGetTeamPlayerCountOffseasonExcludesExpired(): void
    {
        // Player with cy=1, cyt=1 — next year is cy+1=2, salary_yr2=0 → expired
        $this->insertTestPlayer(200030107, 'Trade Expired', [
            'teamid' => 4,
            'ordinal' => 100,
            'cy' => 1,
            'cyt' => 1,
            'salary_yr1' => 1500,
            'salary_yr2' => 0,
        ]);

        $baseline = $this->formRepo->getTeamPlayerCount(4, true);

        // The expired player should not be counted (salary_yr2=0 means no salary next year)
        // Insert a non-expired player for comparison
        $this->insertTestPlayer(200030108, 'Trade Active', [
            'teamid' => 4,
            'ordinal' => 200,
            'cy' => 1,
            'cyt' => 3,
            'salary_yr1' => 1500,
            'salary_yr2' => 1600,
        ]);

        $count = $this->formRepo->getTeamPlayerCount(4, true);

        // Only the active player adds to the count
        self::assertSame($baseline + 1, $count);
    }

    // ── Player existence check ──────────────────────────────────

    public function testPlayerIdExistsReturnsTrueForSeedPlayer(): void
    {
        // PID 1 exists in the CI seed
        self::assertTrue($this->assetRepo->playerIdExists(1));
    }

    public function testPlayerIdExistsReturnsFalseForUnknown(): void
    {
        self::assertFalse($this->assetRepo->playerIdExists(999999999));
    }

    // ── Player/pick updates ─────────────────────────────────────

    public function testUpdatePlayerTeamAndVerify(): void
    {
        $this->insertTestPlayer(200030109, 'Trade Update P', ['teamid' => 1]);

        $affected = $this->assetRepo->updatePlayerTeam(200030109, 5);
        self::assertSame(1, $affected);

        $player = $this->assetRepo->getPlayerById(200030109);
        self::assertNotNull($player);
        self::assertSame(5, $player['teamid']);
    }

    public function testUpdateDraftPickOwnerByIdUpdatesFields(): void
    {
        $pickId = $this->insertDraftPickRow(1, 1, 2031, 1);

        $affected = $this->assetRepo->updateDraftPickOwnerById($pickId, 'Sharks', 2);
        self::assertSame(1, $affected);

        $pick = $this->assetRepo->getDraftPickById($pickId);
        self::assertNotNull($pick);
        self::assertSame('Sharks', $pick['ownerofpick']);
        self::assertSame(2, $pick['owner_teamid']);
    }

    // ── Single player/pick fetch ────────────────────────────────

    public function testGetPlayerByIdReturnsRow(): void
    {
        $this->insertTestPlayer(200030110, 'Trade Fetch P', ['teamid' => 2]);

        $player = $this->assetRepo->getPlayerById(200030110);

        self::assertNotNull($player);
        self::assertSame('Trade Fetch P', $player['name']);
        self::assertSame(2, $player['teamid']);
    }

    public function testGetDraftPickByIdReturnsRow(): void
    {
        $pickId = $this->insertDraftPickRow(1, 1, 2032, 1);

        $pick = $this->assetRepo->getDraftPickById($pickId);

        self::assertNotNull($pick);
        self::assertSame($pickId, $pick['pickid']);
    }

    // ── Trade validation helper ─────────────────────────────────

    public function testGetPlayerForTradeValidationReturnsOrdinalAndCy(): void
    {
        $this->insertTestPlayer(200030111, 'Trade Valid P', ['ordinal' => 150, 'cy' => 2]);

        $result = $this->assetRepo->getPlayerForTradeValidation(200030111);

        self::assertNotNull($result);
        self::assertSame(150, $result['ordinal']);
        self::assertSame(2, $result['cy']);
    }

    // ── Team players/picks for trading UI ───────────────────────

    public function testGetTeamPlayersForTradingReturnsTeamPlayers(): void
    {
        $this->insertTestPlayer(200030112, 'Trade UI Plr', ['teamid' => 5, 'ordinal' => 100]);

        $players = $this->formRepo->getTeamPlayersForTrading(5);

        $names = array_column($players, 'name');
        self::assertContains('Trade UI Plr', $names);
    }

    public function testGetTeamDraftPicksForTradingReturnsPicks(): void
    {
        $pickId = $this->insertDraftPickRow(6, 6, 2033, 1, ['ownerofpick' => 'Metros', 'owner_teamid' => 6]);

        $picks = $this->formRepo->getTeamDraftPicksForTrading(6);

        $pickIds = array_column($picks, 'pickid');
        self::assertContains($pickId, $pickIds);
    }

    // ── deleteTradeOfferById ───────────────────────────────────

    public function testDeleteTradeOfferByIdRemovesOffer(): void
    {
        $offerId = $this->insertTradeOfferRow();

        $affected = $this->offerRepo->deleteTradeOfferById($offerId);

        self::assertSame(1, $affected);

        $stmt = $this->db->prepare("SELECT id FROM ibl_trade_offers WHERE id = ?");
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $offerId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        self::assertNull($row);
    }

    // ── deleteTradeOffer (nested transaction) ───────────────────
    // MUST BE LAST: begin_transaction() inside implicitly commits the outer tx.

    public function testDeleteTradeOfferRemovesAllRelatedRows(): void
    {
        $offerId = $this->insertTradeOfferRow();
        $this->insertTradeInfoRow($offerId, 1, '1', 'Metros', 'Sharks');
        $this->insertTradeCashRow($offerId, 'Metros', 'Sharks', ['salary_yr1' => 500]);

        // This implicitly commits the outer DatabaseTestCase transaction
        $this->offerRepo->deleteTradeOffer($offerId);

        // Verify all related rows are deleted
        $trades = $this->offerRepo->getTradesByOfferId($offerId);
        self::assertCount(0, $trades);

        $stmt = $this->db->prepare("SELECT id FROM ibl_trade_offers WHERE id = ?");
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $offerId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        self::assertNull($row);

        $stmt2 = $this->db->prepare("SELECT id FROM ibl_trade_cash WHERE tradeOfferID = ?");
        self::assertNotFalse($stmt2);
        $stmt2->bind_param('i', $offerId);
        $stmt2->execute();
        $cashRow = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
        self::assertNull($cashRow);
    }
}
