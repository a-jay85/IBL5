<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use AllStarAppearances\AllStarAppearancesRepository;

class AllStarAppearancesRepositoryTest extends DatabaseTestCase
{
    private AllStarAppearancesRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new AllStarAppearancesRepository($this->db);
    }

    public function testReturnsAllStarPlayerWithCorrectAppearanceCount(): void
    {
        $this->insertAwardRow('ASA TestStar', 'Eastern Conference All-Star', 2020);
        $this->insertAwardRow('ASA TestStar', 'Western Conference All-Star', 2021);
        $this->insertAwardRow('ASA TestStar', 'Eastern Conference All-Star', 2022);

        $results = $this->repo->getAllStarAppearances();

        $found = $this->findByName($results, 'ASA TestStar');
        self::assertNotNull($found, 'Expected ASA TestStar in results');
        self::assertSame(3, $found['appearances']);
    }

    public function testExcludesNonAllStarAwards(): void
    {
        $this->insertAwardRow('ASA NonStar', 'MVP', 2020);
        $this->insertAwardRow('ASA NonStar', 'Defensive Player of the Year', 2021);

        $results = $this->repo->getAllStarAppearances();

        $found = $this->findByName($results, 'ASA NonStar');
        self::assertNull($found, 'Non-all-star awards should be excluded');
    }

    public function testCountsBothConferenceAllStarAwards(): void
    {
        $this->insertAwardRow('ASA DualConf', 'Eastern Conference All-Star', 2020);
        $this->insertAwardRow('ASA DualConf', 'Western Conference All-Star', 2021);

        $results = $this->repo->getAllStarAppearances();

        $found = $this->findByName($results, 'ASA DualConf');
        self::assertNotNull($found);
        self::assertSame(2, $found['appearances']);
    }

    public function testOrderedByAppearancesDescThenNameAsc(): void
    {
        $this->insertAwardRow('ASA Zeta', 'Eastern Conference All-Star', 2020);
        $this->insertAwardRow('ASA Zeta', 'Eastern Conference All-Star', 2021);
        $this->insertAwardRow('ASA Zeta', 'Eastern Conference All-Star', 2022);
        $this->insertAwardRow('ASA Alpha', 'Western Conference All-Star', 2020);
        $this->insertAwardRow('ASA Alpha', 'Western Conference All-Star', 2021);
        $this->insertAwardRow('ASA Beta', 'Eastern Conference All-Star', 2020);

        $results = $this->repo->getAllStarAppearances();

        $testNames = [];
        foreach ($results as $row) {
            if (str_starts_with($row['name'], 'ASA ')) {
                $testNames[] = $row['name'];
            }
        }

        // Zeta has 3, Alpha has 2, Beta has 1
        // Same count: Alpha before Zeta (alpha sort)
        $zetaPos = array_search('ASA Zeta', $testNames, true);
        $alphaPos = array_search('ASA Alpha', $testNames, true);
        $betaPos = array_search('ASA Beta', $testNames, true);

        self::assertNotFalse($zetaPos);
        self::assertNotFalse($alphaPos);
        self::assertNotFalse($betaPos);
        self::assertLessThan($alphaPos, $zetaPos, 'Zeta (3) should be before Alpha (2)');
        self::assertLessThan($betaPos, $alphaPos, 'Alpha (2) should be before Beta (1)');
    }

    public function testPidResolvedFromHistJoin(): void
    {
        $pid = 200130001;
        $this->insertTestPlayer($pid, 'ASA HistPlyr');
        $this->insertAwardRow('ASA HistPlyr', 'Eastern Conference All-Star', 2020);
        $this->insertHistRow($pid, 'ASA HistPlyr', 2020);

        $results = $this->repo->getAllStarAppearances();

        $found = $this->findByName($results, 'ASA HistPlyr');
        self::assertNotNull($found);
        self::assertSame($pid, $found['pid']);
    }

    public function testPidNullWhenPlayerNotInHist(): void
    {
        $this->insertAwardRow('ASA NoHist', 'Western Conference All-Star', 2020);

        $results = $this->repo->getAllStarAppearances();

        $found = $this->findByName($results, 'ASA NoHist');
        self::assertNotNull($found);
        self::assertNull($found['pid']);
    }

    public function testResultRowHasExpectedKeys(): void
    {
        $this->insertAwardRow('ASA KeyCheck', 'Eastern Conference All-Star', 2020);

        $results = $this->repo->getAllStarAppearances();

        $found = $this->findByName($results, 'ASA KeyCheck');
        self::assertNotNull($found);
        self::assertArrayHasKey('name', $found);
        self::assertArrayHasKey('pid', $found);
        self::assertArrayHasKey('appearances', $found);
    }

    public function testMultiplePlayersReturnedWithCorrectCounts(): void
    {
        $this->insertAwardRow('ASA Player A', 'Eastern Conference All-Star', 2020);
        $this->insertAwardRow('ASA Player A', 'Eastern Conference All-Star', 2021);
        $this->insertAwardRow('ASA Player B', 'Western Conference All-Star', 2020);

        $results = $this->repo->getAllStarAppearances();

        $a = $this->findByName($results, 'ASA Player A');
        $b = $this->findByName($results, 'ASA Player B');
        self::assertNotNull($a);
        self::assertNotNull($b);
        self::assertSame(2, $a['appearances']);
        self::assertSame(1, $b['appearances']);
    }

    public function testReturnsArrayOfResults(): void
    {
        $results = $this->repo->getAllStarAppearances();
        self::assertIsArray($results);
    }

    /**
     * @param array<int, array<string, mixed>> $results
     * @return array<string, mixed>|null
     */
    private function findByName(array $results, string $name): ?array
    {
        foreach ($results as $row) {
            if ($row['name'] === $name) {
                return $row;
            }
        }
        return null;
    }
}
