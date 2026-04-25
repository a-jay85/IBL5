<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use ContractList\ContractListRepository;

/**
 * Tests ContractListRepository against real MariaDB — active player
 * contract listings with team info JOINs.
 */
class ContractListRepositoryTest extends DatabaseTestCase
{
    private ContractListRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new ContractListRepository($this->db);
    }

    // ── getActivePlayerContracts ─────────────────────────────────

    public function testGetActivePlayerContractsReturnsNonEmptyList(): void
    {
        $this->insertTestPlayer(200110001, 'Contract Plyr', ['teamid' => 1, 'retired' => 0]);

        $contracts = $this->repo->getActivePlayerContracts();

        self::assertNotEmpty($contracts);
    }

    public function testGetActivePlayerContractsIncludesContractFields(): void
    {
        $this->insertTestPlayer(200110002, 'Contract Fields', ['teamid' => 1, 'retired' => 0]);

        $contracts = $this->repo->getActivePlayerContracts();

        self::assertNotEmpty($contracts);
        $first = $contracts[0];
        self::assertArrayHasKey('pid', $first);
        self::assertArrayHasKey('name', $first);
        self::assertArrayHasKey('pos', $first);
        self::assertArrayHasKey('teamname', $first);
        self::assertArrayHasKey('cy', $first);
        self::assertArrayHasKey('cyt', $first);
        self::assertArrayHasKey('salary_yr1', $first);
        self::assertArrayHasKey('salary_yr2', $first);
        self::assertArrayHasKey('salary_yr3', $first);
        self::assertArrayHasKey('salary_yr4', $first);
        self::assertArrayHasKey('salary_yr5', $first);
        self::assertArrayHasKey('salary_yr6', $first);
        self::assertArrayHasKey('bird', $first);
        self::assertArrayHasKey('team_city', $first);
        self::assertArrayHasKey('color1', $first);
        self::assertArrayHasKey('color2', $first);
    }
}
