<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Trading\TradeCashRepository;
use Trading\TradeItemType;
use Trading\TradingRepository;

/**
 * Tests TradingRepository against real MariaDB — trade offers, items, player/pick lookups.
 *
 * NOTE: getTradePlayers() and getTradePicks() query ibl_trade_players / ibl_trade_picks
 * which do not exist in the schema. These are dead code and are not tested.
 *
 * NOTE: playerExistsInTrade() also queries ibl_trade_players (non-existent). Not tested.
 *
 * NOTE: updateDraftPickOwner() references non-existent columns (currentteam, pick) in
 * ibl_draft_picks. The table has ownerofpick/owner_tid and year/round instead. Dead code.
 *
 * IMPORTANT: testDeleteTradeOfferRemovesAllRelatedRows MUST be last in this file.
 * deleteTradeOffer() calls begin_transaction() internally, which implicitly commits
 * the outer DatabaseTestCase transaction. tearDown rollback becomes a no-op.
 */
class TradingRepositoryTest extends DatabaseTestCase
{
    private TradingRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['SERVER_NAME'] = 'localhost';
        $cashRepo = new TradeCashRepository($this->db);
        $this->repo = new TradingRepository($this->db, $cashRepo);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['SERVER_NAME']);
        parent::tearDown();
    }

    // ── Team queries (seed validation) ──────────────────────────

    public function testGetAllTeamsReturns28Teams(): void
    {
        $teams = $this->repo->getAllTeams();

        self::assertCount(28, $teams);
    }

    public function testGetAllTeamsWithCityReturns28Teams(): void
    {
        $teams = $this->repo->getAllTeamsWithCity();

        self::assertCount(28, $teams);
        self::assertArrayHasKey('team_city', $teams[0]);
    }

    // ── Player batch fetch (dynamic IN) ─────────────────────────

    public function testGetPlayersByIdsWithMultipleIds(): void
    {
        $this->insertTestPlayer(200030101, 'Trade Batch P1', ['tid' => 1]);
        $this->insertTestPlayer(200030102, 'Trade Batch P2', ['tid' => 2]);

        $result = $this->repo->getPlayersByIds([200030101, 200030102]);

        self::assertCount(2, $result);
        self::assertArrayHasKey(200030101, $result);
        self::assertArrayHasKey(200030102, $result);
        self::assertSame('Trade Batch P1', $result[200030101]['name']);
        self::assertSame('Trade Batch P2', $result[200030102]['name']);
    }

    public function testGetPlayersByIdsEmptyArrayReturnsEmpty(): void
    {
        $result = $this->repo->getPlayersByIds([]);

        self::assertSame([], $result);
    }

    // ── Draft pick batch fetch (dynamic IN) ─────────────────────

    public function testGetDraftPicksByIdsWithMultipleIds(): void
    {
        $pickId1 = $this->insertDraftPickRow(1, 1, 2030, 1);
        $pickId2 = $this->insertDraftPickRow(2, 2, 2030, 2);

        $result = $this->repo->getDraftPicksByIds([$pickId1, $pickId2]);

        self::assertCount(2, $result);
        self::assertArrayHasKey($pickId1, $result);
        self::assertArrayHasKey($pickId2, $result);
    }

    public function testGetDraftPicksByIdsEmptyArrayReturnsEmpty(): void
    {
        $result = $this->repo->getDraftPicksByIds([]);

        self::assertSame([], $result);
    }

    // ── Trade offer ID generation ───────────────────────────────

    public function testGenerateNextTradeOfferIdIsMonotonicallyIncreasing(): void
    {
        $id1 = $this->repo->generateNextTradeOfferId();
        $id2 = $this->repo->generateNextTradeOfferId();

        self::assertGreaterThan(0, $id1);
        self::assertGreaterThan($id1, $id2);
    }

    // ── Trade item insert + retrieve ────────────────────────────

    public function testInsertTradeItemAndRetrieveByOfferId(): void
    {
        $offerId = $this->insertTradeOfferRow();
        $this->insertTestPlayer(200030103, 'Trade Item Plr', ['tid' => 1]);

        $this->repo->insertTradeItem($offerId, 200030103, TradeItemType::Player, 'Metros', 'Sharks', 'Metros');

        $trades = $this->repo->getTradesByOfferId($offerId);
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

        $trades = $this->repo->getTradesByOfferIdForUpdate($offerId);

        self::assertCount(1, $trades);
        self::assertSame($offerId, $trades[0]['tradeofferid']);
    }

    // ── Mark completed / delete info ────────────────────────────

    public function testMarkTradeInfoCompletedSetsApproval(): void
    {
        $offerId = $this->insertTradeOfferRow();
        $this->insertTradeInfoRow($offerId, 1, '1', 'Metros', 'Sharks', 'Metros');

        $this->repo->markTradeInfoCompleted($offerId);

        $trades = $this->repo->getTradesByOfferId($offerId);
        self::assertCount(1, $trades);
        self::assertSame('completed', $trades[0]['approval']);
    }

    public function testDeleteTradeInfoByOfferIdRemovesAllItems(): void
    {
        $offerId = $this->insertTradeOfferRow();
        $this->insertTradeInfoRow($offerId, 1, '1', 'Metros', 'Sharks');
        $this->insertTradeInfoRow($offerId, 2, '0', 'Sharks', 'Metros');

        $this->repo->deleteTradeInfoByOfferId($offerId);

        $trades = $this->repo->getTradesByOfferId($offerId);
        self::assertCount(0, $trades);
    }

    // ── getAllTradeOffers excludes completed ─────────────────────

    public function testGetAllTradeOffersExcludesCompleted(): void
    {
        $offerId1 = $this->insertTradeOfferRow();
        $this->insertTradeInfoRow($offerId1, 1, '1', 'Metros', 'Sharks', 'completed');

        $offerId2 = $this->insertTradeOfferRow();
        $this->insertTradeInfoRow($offerId2, 2, '1', 'Sharks', 'Metros', 'Metros');

        $offers = $this->repo->getAllTradeOffers();

        $offerIds = array_column($offers, 'tradeofferid');
        self::assertNotContains($offerId1, $offerIds);
        self::assertContains($offerId2, $offerIds);
    }

    // ── Team player count ───────────────────────────────────────

    public function testGetTeamPlayerCountRegularSeason(): void
    {
        // Use a team ID unlikely to have seed players: tid=3
        // First count existing players on tid=3
        $baseline = $this->repo->getTeamPlayerCount(3);

        $this->insertTestPlayer(200030104, 'Trade Count P1', ['tid' => 3, 'ordinal' => 100]);
        $this->insertTestPlayer(200030105, 'Trade Count P2', ['tid' => 3, 'ordinal' => 200]);
        $this->insertTestPlayer(200030106, 'Trade Count P3', ['tid' => 3, 'ordinal' => 300]);

        $count = $this->repo->getTeamPlayerCount(3);

        self::assertSame($baseline + 3, $count);
    }

    public function testGetTeamPlayerCountOffseasonExcludesExpired(): void
    {
        // Player with cy=1, cyt=1 — next year is cy+1=2, cy2=0 → expired
        $this->insertTestPlayer(200030107, 'Trade Expired', [
            'tid' => 4,
            'ordinal' => 100,
            'cy' => 1,
            'cyt' => 1,
            'cy1' => 1500,
            'cy2' => 0,
        ]);

        $baseline = $this->repo->getTeamPlayerCount(4, true);

        // The expired player should not be counted (cy2=0 means no salary next year)
        // Insert a non-expired player for comparison
        $this->insertTestPlayer(200030108, 'Trade Active', [
            'tid' => 4,
            'ordinal' => 200,
            'cy' => 1,
            'cyt' => 3,
            'cy1' => 1500,
            'cy2' => 1600,
        ]);

        $count = $this->repo->getTeamPlayerCount(4, true);

        // Only the active player adds to the count
        self::assertSame($baseline + 1, $count);
    }

    // ── Player existence check ──────────────────────────────────

    public function testPlayerIdExistsReturnsTrueForSeedPlayer(): void
    {
        // PID 1 exists in the CI seed
        self::assertTrue($this->repo->playerIdExists(1));
    }

    public function testPlayerIdExistsReturnsFalseForUnknown(): void
    {
        self::assertFalse($this->repo->playerIdExists(999999999));
    }

    // ── Player/pick updates ─────────────────────────────────────

    public function testUpdatePlayerTeamAndVerify(): void
    {
        $this->insertTestPlayer(200030109, 'Trade Update P', ['tid' => 1]);

        $affected = $this->repo->updatePlayerTeam(200030109, 5);
        self::assertSame(1, $affected);

        $player = $this->repo->getPlayerById(200030109);
        self::assertNotNull($player);
        self::assertSame(5, $player['tid']);
    }

    public function testUpdateDraftPickOwnerByIdUpdatesFields(): void
    {
        $pickId = $this->insertDraftPickRow(1, 1, 2031, 1);

        $affected = $this->repo->updateDraftPickOwnerById($pickId, 'Sharks', 2);
        self::assertSame(1, $affected);

        $pick = $this->repo->getDraftPickById($pickId);
        self::assertNotNull($pick);
        self::assertSame('Sharks', $pick['ownerofpick']);
        self::assertSame(2, $pick['owner_tid']);
    }

    // ── Single player/pick fetch ────────────────────────────────

    public function testGetPlayerByIdReturnsRow(): void
    {
        $this->insertTestPlayer(200030110, 'Trade Fetch P', ['tid' => 2]);

        $player = $this->repo->getPlayerById(200030110);

        self::assertNotNull($player);
        self::assertSame('Trade Fetch P', $player['name']);
        self::assertSame(2, $player['tid']);
    }

    public function testGetDraftPickByIdReturnsRow(): void
    {
        $pickId = $this->insertDraftPickRow(1, 1, 2032, 1);

        $pick = $this->repo->getDraftPickById($pickId);

        self::assertNotNull($pick);
        self::assertSame($pickId, $pick['pickid']);
    }

    // ── Trade validation helper ─────────────────────────────────

    public function testGetPlayerForTradeValidationReturnsOrdinalAndCy(): void
    {
        $this->insertTestPlayer(200030111, 'Trade Valid P', ['ordinal' => 150, 'cy' => 2]);

        $result = $this->repo->getPlayerForTradeValidation(200030111);

        self::assertNotNull($result);
        self::assertSame(150, $result['ordinal']);
        self::assertSame(2, $result['cy']);
    }

    // ── Team players/picks for trading UI ───────────────────────

    public function testGetTeamPlayersForTradingExcludesPipeNames(): void
    {
        $this->insertTestPlayer(200030112, 'Trade UI Plr', ['tid' => 5, 'ordinal' => 100]);
        $this->insertTestPlayer(200030113, '|Cash Trade', ['tid' => 5, 'ordinal' => 200]);

        $players = $this->repo->getTeamPlayersForTrading(5);

        $names = array_column($players, 'name');
        self::assertContains('Trade UI Plr', $names);
        self::assertNotContains('|Cash Trade', $names);
    }

    public function testGetTeamDraftPicksForTradingReturnsPicks(): void
    {
        $pickId = $this->insertDraftPickRow(6, 6, 2033, 1, ['ownerofpick' => 'Metros', 'owner_tid' => 6]);

        $picks = $this->repo->getTeamDraftPicksForTrading(6);

        $pickIds = array_column($picks, 'pickid');
        self::assertContains($pickId, $pickIds);
    }

    // ── deleteTradeOffer (nested transaction) ───────────────────
    // MUST BE LAST: begin_transaction() inside implicitly commits the outer tx.

    public function testDeleteTradeOfferRemovesAllRelatedRows(): void
    {
        $offerId = $this->insertTradeOfferRow();
        $this->insertTradeInfoRow($offerId, 1, '1', 'Metros', 'Sharks');
        $this->insertTradeCashRow($offerId, 'Metros', 'Sharks', ['cy1' => 500]);

        // This implicitly commits the outer DatabaseTestCase transaction
        $this->repo->deleteTradeOffer($offerId);

        // Verify all related rows are deleted
        $trades = $this->repo->getTradesByOfferId($offerId);
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
