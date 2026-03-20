<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PlayerMovement\PlayerMovementRepository;

class PlayerMovementRepositoryTest extends DatabaseTestCase
{
    private PlayerMovementRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new PlayerMovementRepository($this->db);
    }

    public function testReturnsMovedPlayer(): void
    {
        $pid = 200110001;
        $this->insertTestPlayer($pid, 'PMV Moved', ['tid' => 2]);
        $this->insertHistRow($pid, 'PMV Moved', 2020, ['teamid' => 1, 'team' => 'Metros']);

        $results = $this->repo->getPlayerMovements(2020);

        $found = $this->findByPid($results, $pid);
        self::assertNotNull($found, 'Player who moved should appear in results');
        self::assertSame(1, $found['old_teamid']);
        self::assertSame(2, $found['new_teamid']);
    }

    public function testExcludesNonMovedPlayer(): void
    {
        $pid = 200110002;
        $this->insertTestPlayer($pid, 'PMV Stayed', ['tid' => 1]);
        $this->insertHistRow($pid, 'PMV Stayed', 2020, ['teamid' => 1]);

        $results = $this->repo->getPlayerMovements(2020);

        $found = $this->findByPid($results, $pid);
        self::assertNull($found, 'Player who stayed should not appear');
    }

    public function testMovementRowHasExpectedKeys(): void
    {
        $pid = 200110003;
        $this->insertTestPlayer($pid, 'PMV Keys', ['tid' => 2]);
        $this->insertHistRow($pid, 'PMV Keys', 2020, ['teamid' => 1]);

        $results = $this->repo->getPlayerMovements(2020);

        $found = $this->findByPid($results, $pid);
        self::assertNotNull($found);
        $expectedKeys = [
            'pid', 'name', 'old_teamid', 'old_team', 'new_teamid', 'new_team',
            'old_city', 'old_color1', 'old_color2', 'new_city', 'new_color1', 'new_color2',
        ];
        foreach ($expectedKeys as $key) {
            self::assertArrayHasKey($key, $found, "Missing key: $key");
        }
    }

    public function testFreeAgentMovementIncluded(): void
    {
        $pid = 200110004;
        // Current team is Free Agents (tid=0), previously on team 3
        $this->insertTestPlayer($pid, 'PMV FreeAgent', ['tid' => 0]);
        $this->insertHistRow($pid, 'PMV FreeAgent', 2020, ['teamid' => 3, 'team' => 'Stallions']);

        $results = $this->repo->getPlayerMovements(2020);

        $found = $this->findByPid($results, $pid);
        self::assertNotNull($found, 'Free agent movement should be included');
        self::assertSame(3, $found['old_teamid']);
        self::assertSame(0, $found['new_teamid']);
    }

    public function testFiltersToRequestedYearOnly(): void
    {
        $pid = 200110005;
        $this->insertTestPlayer($pid, 'PMV YearFilt', ['tid' => 2]);
        $this->insertHistRow($pid, 'PMV YearFilt', 2019, ['teamid' => 1]);

        $results = $this->repo->getPlayerMovements(2020);

        $found = $this->findByPid($results, $pid);
        self::assertNull($found, 'Player with hist in different year should not appear');
    }

    public function testNoMovementsForUnknownYearReturnsEmpty(): void
    {
        $results = $this->repo->getPlayerMovements(9999);

        // Filter out any seed data movements
        $testResults = array_filter(
            $results,
            static fn (array $row): bool => str_starts_with($row['name'], 'PMV '),
        );
        self::assertCount(0, $testResults);
    }

    public function testOrderedByNewTeamName(): void
    {
        // Player 1 moves to Sharks (tid=2), Player 2 moves to Metros (tid=1)
        // Seed: tid=1=Metros, tid=2=Sharks — Metros < Sharks alphabetically
        $pid1 = 200110006;
        $pid2 = 200110007;
        $this->insertTestPlayer($pid1, 'PMV OrderA', ['tid' => 2]);
        $this->insertHistRow($pid1, 'PMV OrderA', 2087, ['teamid' => 1, 'team' => 'Metros']);
        $this->insertTestPlayer($pid2, 'PMV OrderB', ['tid' => 1]);
        $this->insertHistRow($pid2, 'PMV OrderB', 2087, ['teamid' => 2, 'team' => 'Sharks']);

        $results = $this->repo->getPlayerMovements(2087);

        // Filter to only test players to avoid seed data ordering issues
        $testResults = array_values(array_filter(
            $results,
            static fn (array $row): bool => str_starts_with($row['name'], 'PMV Order'),
        ));
        self::assertCount(2, $testResults);
        // Metros sorts before Sharks alphabetically
        self::assertSame($pid2, $testResults[0]['pid'], 'Player moving to Metros should be first');
        self::assertSame($pid1, $testResults[1]['pid'], 'Player moving to Sharks should be second');
    }

    public function testCorrectCountMatchesOnlyMovedPlayers(): void
    {
        $pid1 = 200110008;
        $pid2 = 200110009;
        $pid3 = 200110010;
        // Two moved, one stayed
        $this->insertTestPlayer($pid1, 'PMV CntMov1', ['tid' => 2]);
        $this->insertHistRow($pid1, 'PMV CntMov1', 2088, ['teamid' => 1]);
        $this->insertTestPlayer($pid2, 'PMV CntMov2', ['tid' => 3]);
        $this->insertHistRow($pid2, 'PMV CntMov2', 2088, ['teamid' => 1]);
        $this->insertTestPlayer($pid3, 'PMV CntStay', ['tid' => 1]);
        $this->insertHistRow($pid3, 'PMV CntStay', 2088, ['teamid' => 1]);

        $results = $this->repo->getPlayerMovements(2088);

        $testResults = array_filter(
            $results,
            static fn (array $row): bool => str_starts_with($row['name'], 'PMV Cnt'),
        );
        self::assertCount(2, $testResults);
    }

    /**
     * @param array<int, array<string, mixed>> $results
     * @return array<string, mixed>|null
     */
    private function findByPid(array $results, int $pid): ?array
    {
        foreach ($results as $row) {
            if ($row['pid'] === $pid) {
                return $row;
            }
        }
        return null;
    }
}
