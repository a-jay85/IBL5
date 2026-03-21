<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Negotiation\NegotiationRepository;

/**
 * Tests NegotiationRepository against real MariaDB — team performance, salary queries, FA status.
 */
class NegotiationRepositoryTest extends DatabaseTestCase
{
    private NegotiationRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new NegotiationRepository($this->db);
    }

    public function testGetTeamPerformanceReturnsContractFields(): void
    {
        $perf = $this->repo->getTeamPerformance('Metros');

        self::assertArrayHasKey('Contract_Wins', $perf);
        self::assertArrayHasKey('Contract_Losses', $perf);
        self::assertArrayHasKey('Contract_AvgW', $perf);
        self::assertArrayHasKey('Contract_AvgL', $perf);
        self::assertIsInt($perf['Contract_Wins']);
        self::assertIsInt($perf['Contract_Losses']);
    }

    public function testGetTeamPerformanceReturnsDefaultsForUnknownTeam(): void
    {
        $perf = $this->repo->getTeamPerformance('Nonexistent Team');

        // Method returns defaults of 41 for unknown teams
        self::assertSame(41, $perf['Contract_Wins']);
        self::assertSame(41, $perf['Contract_Losses']);
        self::assertSame(41, $perf['Contract_AvgW']);
        self::assertSame(41, $perf['Contract_AvgL']);
    }

    public function testGetPositionSalaryCommitmentReturnsInt(): void
    {
        // Insert two test players at the same position on the same team
        $this->insertTestPlayer(200040001, 'NEG SalaryPG1', [
            'tid' => 1,
            'pos' => 'PG',
            'cy' => 1,
            'cyt' => 3,
            'cy1' => 1500,
            'cy2' => 1600,
        ]);
        $this->insertTestPlayer(200040002, 'NEG SalaryPG2', [
            'tid' => 1,
            'pos' => 'PG',
            'cy' => 1,
            'cyt' => 2,
            'cy1' => 2000,
            'cy2' => 2100,
        ]);

        // Exclude NEG SalaryPG1 — should only include NEG SalaryPG2's next year salary
        $commitment = $this->repo->getPositionSalaryCommitment('Metros', 'PG', 'NEG SalaryPG1');

        self::assertIsInt($commitment);
    }

    public function testGetTeamCapSpaceNextSeasonReturnsInt(): void
    {
        $capSpace = $this->repo->getTeamCapSpaceNextSeason('Metros');

        self::assertIsInt($capSpace);
    }

    public function testIsFreeAgencyActiveReturnsBool(): void
    {
        // Reads existing seed data for module active status
        $active = $this->repo->isFreeAgencyActive();

        self::assertIsBool($active);
    }

    public function testGetMarketMaximumsReturnsAllKeys(): void
    {
        $maximums = $this->repo->getMarketMaximums();

        $expectedKeys = [
            'fga', 'fgp', 'fta', 'ftp', 'tga', 'tgp',
            'orb', 'drb', 'ast', 'stl', 'to', 'blk',
            'foul', 'oo', 'od', 'do', 'dd', 'po', 'pd', 'td',
        ];

        foreach ($expectedKeys as $key) {
            self::assertArrayHasKey($key, $maximums, "Missing key: $key");
            self::assertIsInt($maximums[$key]);
            self::assertGreaterThanOrEqual(1, $maximums[$key], "Key $key should be >= 1");
        }
    }

    // ── Negative path ───────────────────────────────────────────

    public function testGetPositionSalaryCommitmentReturnsZeroForUnknownTeam(): void
    {
        $result = $this->repo->getPositionSalaryCommitment('ZZ_NoTeam_B9', 'PG', 'SomePlayer');

        self::assertSame(0, $result);
    }
}
