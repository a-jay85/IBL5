<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use FreeAgency\FreeAgencyAdminRepository;

/**
 * Tests FreeAgencyAdminRepository against real MariaDB — offers, demands,
 * contract updates, MLE/LLE marking, news stories, and offer clearing.
 */
class FreeAgencyAdminRepositoryTest extends DatabaseTestCase
{
    private FreeAgencyAdminRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new FreeAgencyAdminRepository($this->db);
    }

    // ── getAllOffersWithBirdYears ────────────────────────────────

    public function testGetAllOffersWithBirdYearsReturnsJoinedData(): void
    {
        $this->insertTestPlayer(200060001, 'FA Offer Player', ['tid' => 0, 'bird' => 5]);
        $this->insertFaOfferRow(200060001, 1, 'FA Offer Player', 'Metros');

        $results = $this->repo->getAllOffersWithBirdYears();

        self::assertNotEmpty($results);

        // Find our test row
        $found = null;
        foreach ($results as $row) {
            if ($row['pid'] === 200060001) {
                $found = $row;
                break;
            }
        }

        self::assertNotNull($found);
        self::assertSame(5, $found['bird']);
        self::assertSame(1500, $found['offer1']);
        self::assertSame('Metros', $found['team']);
    }

    public function testGetAllOffersWithBirdYearsReturnsEmptyAfterClearing(): void
    {
        // Insert an offer so clearAllOffers has something to delete
        $this->insertTestPlayer(200060008, 'FA Empty Test', ['tid' => 0, 'bird' => 2]);
        $this->insertFaOfferRow(200060008, 1, 'FA Empty Test', 'Metros');

        $this->repo->clearAllOffers();

        $results = $this->repo->getAllOffersWithBirdYears();

        self::assertSame([], $results);
    }

    // ── getPlayerDemandsBatch ───────────────────────────────────

    public function testGetPlayerDemandsBatchReturnsMapKeyedByPid(): void
    {
        $this->insertTestPlayer(200060003, 'FA Batch P1');
        $this->insertTestPlayer(200060004, 'FA Batch P2');
        $this->insertDemandRow('FA Batch P1', 200060003, ['dem1' => 3000]);
        $this->insertDemandRow('FA Batch P2', 200060004, ['dem1' => 4000]);

        $result = $this->repo->getPlayerDemandsBatch([200060003, 200060004]);

        self::assertCount(2, $result);
        self::assertArrayHasKey(200060003, $result);
        self::assertArrayHasKey(200060004, $result);
        self::assertSame(3000, $result[200060003]['dem1']);
        self::assertSame(4000, $result[200060004]['dem1']);
    }

    public function testGetPlayerDemandsBatchReturnsEmptyForEmptyInput(): void
    {
        $result = $this->repo->getPlayerDemandsBatch([]);

        self::assertSame([], $result);
    }

    // ── updatePlayerContract ────────────────────────────────────

    public function testUpdatePlayerContractSetsFieldsCorrectly(): void
    {
        $this->insertTestPlayer(200060005, 'FA Contract Plyr', ['tid' => 0, 'cy' => 0, 'cyt' => 0, 'cy1' => 0]);

        $affected = $this->repo->updatePlayerContract(
            200060005,
            1,    // tid (Metros)
            3,    // offerYears
            1500, // offer1
            1600, // offer2
            1700, // offer3
            0,    // offer4
            0,    // offer5
            0     // offer6
        );

        self::assertSame(1, $affected);

        $stmt = $this->db->prepare('SELECT cy, cyt, cy1, cy2, cy3, cy4, tid, fa_signing_flag FROM ibl_plr WHERE pid = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $pid);
        $pid = 200060005;
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(0, $row['cy']);
        self::assertSame(3, $row['cyt']);
        self::assertSame(1500, $row['cy1']);
        self::assertSame(1600, $row['cy2']);
        self::assertSame(1700, $row['cy3']);
        self::assertSame(0, $row['cy4']);
        self::assertSame(1, $row['tid']);
        self::assertSame(1, $row['fa_signing_flag']);
    }

    // ── markMleUsed / markLleUsed ───────────────────────────────

    public function testMarkMleUsedSetsHasMleToZero(): void
    {
        // Seed has Metros in ibl_team_info — set HasMLE=1 first
        $stmt = $this->db->prepare('UPDATE ibl_team_info SET HasMLE = 1 WHERE team_name = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $team);
        $team = 'Metros';
        $stmt->execute();
        $stmt->close();

        $this->repo->markMleUsed('Metros');

        $stmt = $this->db->prepare('SELECT HasMLE FROM ibl_team_info WHERE team_name = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $team);
        $team = 'Metros';
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(0, $row['HasMLE']);
    }

    public function testMarkLleUsedSetsHasLleToZero(): void
    {
        // Set HasLLE=1 first
        $stmt = $this->db->prepare('UPDATE ibl_team_info SET HasLLE = 1 WHERE team_name = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $team);
        $team = 'Metros';
        $stmt->execute();
        $stmt->close();

        $this->repo->markLleUsed('Metros');

        $stmt = $this->db->prepare('SELECT HasLLE FROM ibl_team_info WHERE team_name = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $team);
        $team = 'Metros';
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(0, $row['HasLLE']);
    }

    // ── insertNewsStory (nuke_stories is InnoDB — rolls back) ──

    public function testInsertNewsStoryCreatesRow(): void
    {
        $affected = $this->repo->insertNewsStory(
            'B10 FA Signing Test',
            'Home text content',
            'Body text content'
        );

        self::assertGreaterThan(0, $affected);

        $stmt = $this->db->prepare("SELECT title, hometext, bodytext FROM nuke_stories WHERE title = ?");
        self::assertNotFalse($stmt);
        $title = 'B10 FA Signing Test';
        $stmt->bind_param('s', $title);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame('Home text content', $row['hometext']);
        self::assertSame('Body text content', $row['bodytext']);
    }

    // ── clearAllOffers ──────────────────────────────────────────

    public function testClearAllOffersDeletesAllRows(): void
    {
        $this->insertTestPlayer(200060006, 'FA Clear P1', ['tid' => 0]);
        $this->insertTestPlayer(200060007, 'FA Clear P2', ['tid' => 0]);
        $this->insertFaOfferRow(200060006, 1, 'FA Clear P1', 'Metros');
        $this->insertFaOfferRow(200060007, 2, 'FA Clear P2', 'Stars');

        $this->repo->clearAllOffers();

        $stmt = $this->db->prepare('SELECT COUNT(*) AS total FROM ibl_fa_offers');
        self::assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(0, $row['total']);
    }
}
