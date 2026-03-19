<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use FreeAgency\FreeAgencyRepository;

/**
 * Tests FreeAgencyRepository against real MariaDB — offer CRUD, player signing status.
 */
class FreeAgencyRepositoryTest extends DatabaseTestCase
{
    private FreeAgencyRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new FreeAgencyRepository($this->db);
    }

    /**
     * Build a valid OfferData array for saveOffer().
     *
     * @param array<string, int|float|string> $overrides
     * @return array<string, int|float|string>
     */
    private function buildOfferData(int $pid, int $tid, array $overrides = []): array
    {
        return array_merge([
            'playerName' => 'FA TestPlayer',
            'pid' => $pid,
            'teamName' => 'Metros',
            'tid' => $tid,
            'offer1' => 1500,
            'offer2' => 1600,
            'offer3' => 1700,
            'offer4' => 0,
            'offer5' => 0,
            'offer6' => 0,
            'modifier' => 1.0,
            'random' => 0.5,
            'perceivedValue' => 4800,
            'mle' => 0,
            'lle' => 0,
            'offerType' => 0,
        ], $overrides);
    }

    public function testSaveOfferInsertsRow(): void
    {
        $this->insertTestPlayer(200020001, 'FA TestPlayer');

        $offerData = $this->buildOfferData(200020001, 1);
        $result = $this->repo->saveOffer($offerData);

        self::assertTrue($result);

        $offer = $this->repo->getExistingOffer(1, 200020001);
        self::assertNotNull($offer);
        self::assertSame(1500, $offer['offer1']);
    }

    public function testSaveOfferReplacesExisting(): void
    {
        $this->insertTestPlayer(200020002, 'FA ReplaceTest');

        $this->repo->saveOffer($this->buildOfferData(200020002, 1, ['offer1' => 1000]));
        $this->repo->saveOffer($this->buildOfferData(200020002, 1, ['offer1' => 2000]));

        $offer = $this->repo->getExistingOffer(1, 200020002);
        self::assertNotNull($offer);
        self::assertSame(2000, $offer['offer1']);
    }

    public function testDeleteOfferRemovesRow(): void
    {
        $this->insertTestPlayer(200020003, 'FA DeleteTest');

        $this->repo->saveOffer($this->buildOfferData(200020003, 1));
        $deleted = $this->repo->deleteOffer(1, 200020003);

        self::assertSame(1, $deleted);

        $offer = $this->repo->getExistingOffer(1, 200020003);
        self::assertNull($offer);
    }

    public function testGetExistingOfferReturnsNullWhenNone(): void
    {
        $offer = $this->repo->getExistingOffer(1, 999999999);

        self::assertNull($offer);
    }

    public function testGetExistingOfferReturnsRowWithOfferKeys(): void
    {
        $this->insertTestPlayer(200020004, 'FA OfferKeys');

        $this->repo->saveOffer($this->buildOfferData(200020004, 1, [
            'offer1' => 1100,
            'offer2' => 1200,
            'offer3' => 1300,
        ]));

        $offer = $this->repo->getExistingOffer(1, 200020004);

        self::assertNotNull($offer);
        self::assertArrayHasKey('offer1', $offer);
        self::assertArrayHasKey('offer2', $offer);
        self::assertArrayHasKey('offer3', $offer);
        self::assertArrayHasKey('offer4', $offer);
        self::assertArrayHasKey('offer5', $offer);
        self::assertArrayHasKey('offer6', $offer);
        self::assertSame(1100, $offer['offer1']);
        self::assertSame(1200, $offer['offer2']);
        self::assertSame(1300, $offer['offer3']);
    }

    public function testIsPlayerAlreadySignedReturnsFalseWhenContracted(): void
    {
        // cy=1 means in year 1 of contract — not a "signed" free agent
        $this->insertTestPlayer(200020005, 'FA ContractTst', ['cy' => 1, 'cy1' => 1500]);

        self::assertFalse($this->repo->isPlayerAlreadySigned(200020005));
    }

    public function testIsPlayerAlreadySignedReturnsTrueWhenCyZeroAndCy1Set(): void
    {
        // cy=0, cy1 != 0 means player was just signed (contract year 0 with salary set)
        $this->insertTestPlayer(200020006, 'FA SignedTest', ['cy' => 0, 'cy1' => 1500]);

        self::assertTrue($this->repo->isPlayerAlreadySigned(200020006));
    }

    public function testGetAllPlayersExcludingTeamExcludesCorrectTeam(): void
    {
        $this->insertTestPlayer(200020007, 'FA ExclTeam1', ['tid' => 1]);
        $this->insertTestPlayer(200020008, 'FA ExclTeam2', ['tid' => 2]);

        $players = $this->repo->getAllPlayersExcludingTeam(1);

        $pids = array_column($players, 'pid');
        self::assertNotContains(200020007, $pids);
        self::assertContains(200020008, $pids);
    }
}
