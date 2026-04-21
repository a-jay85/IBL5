<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PlayerDatabase\PlayerDatabaseRepository;

class PlayerDatabaseRepositoryTest extends DatabaseTestCase
{
    private PlayerDatabaseRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new PlayerDatabaseRepository($this->db);
    }

    public function testSearchByNameLikeReturnsMatch(): void
    {
        $this->insertTestPlayer(200100001, 'PDB NameTest', ['oo' => 70]);

        $result = $this->repo->searchPlayers($this->buildParams(['search_name' => 'PDB Name']));

        self::assertGreaterThanOrEqual(1, $result['count']);
        $found = $this->findByPid($result['results'], 200100001);
        self::assertNotNull($found);
        self::assertSame('PDB NameTest', $found['name']);
    }

    public function testFilterByPositionExactMatch(): void
    {
        $this->insertTestPlayer(200100002, 'PDB PosTest', ['pos' => 'C']);
        $this->insertTestPlayer(200100003, 'PDB PosOther', ['pos' => 'PG']);

        $result = $this->repo->searchPlayers($this->buildParams(['search_name' => 'PDB Pos', 'pos' => 'C']));

        $found = $this->findByPid($result['results'], 200100002);
        $notFound = $this->findByPid($result['results'], 200100003);
        self::assertNotNull($found);
        self::assertNull($notFound);
    }

    public function testActiveFilterExcludesRetiredPlayers(): void
    {
        $this->insertTestPlayer(200100004, 'PDB ActiveP', ['retired' => 0]);
        $this->insertTestPlayer(200100005, 'PDB Retired', ['retired' => 1]);

        $result = $this->repo->searchPlayers($this->buildParams([
            'search_name' => 'PDB ',
            'active' => 0,
        ]));

        $active = $this->findByPid($result['results'], 200100004);
        $retired = $this->findByPid($result['results'], 200100005);
        self::assertNotNull($active);
        self::assertNull($retired);
    }

    public function testActiveNullIncludesRetiredPlayers(): void
    {
        $this->insertTestPlayer(200100006, 'PDB InclRet', ['retired' => 1]);

        $result = $this->repo->searchPlayers($this->buildParams([
            'search_name' => 'PDB InclRet',
            'active' => null,
        ]));

        $found = $this->findByPid($result['results'], 200100006);
        self::assertNotNull($found);
    }

    public function testAgeUpperBoundFilter(): void
    {
        $this->insertTestPlayer(200100007, 'PDB Young', ['age' => 22]);
        $this->insertTestPlayer(200100008, 'PDB Old', ['age' => 35]);

        $result = $this->repo->searchPlayers($this->buildParams([
            'search_name' => 'PDB ',
            'age' => 25,
        ]));

        $young = $this->findByPid($result['results'], 200100007);
        $old = $this->findByPid($result['results'], 200100008);
        self::assertNotNull($young);
        self::assertNull($old);
    }

    public function testExpMinimumFilter(): void
    {
        $this->insertTestPlayer(200100009, 'PDB ExpHigh', ['exp' => 10]);
        $this->insertTestPlayer(200100010, 'PDB ExpLow', ['exp' => 2]);

        $result = $this->repo->searchPlayers($this->buildParams([
            'search_name' => 'PDB Exp',
            'exp' => 5,
        ]));

        $high = $this->findByPid($result['results'], 200100009);
        $low = $this->findByPid($result['results'], 200100010);
        self::assertNotNull($high);
        self::assertNull($low);
    }

    public function testExpMaximumFilter(): void
    {
        $this->insertTestPlayer(200100011, 'PDB ExpMax', ['exp' => 3]);
        $this->insertTestPlayer(200100012, 'PDB ExpOver', ['exp' => 10]);

        $result = $this->repo->searchPlayers($this->buildParams([
            'search_name' => 'PDB Exp',
            'exp_max' => 5,
        ]));

        $within = $this->findByPid($result['results'], 200100011);
        $over = $this->findByPid($result['results'], 200100012);
        self::assertNotNull($within);
        self::assertNull($over);
    }

    public function testBirdRangeFilters(): void
    {
        $this->insertTestPlayer(200100013, 'PDB BirdIn', ['bird' => 3]);
        $this->insertTestPlayer(200100014, 'PDB BirdOut', ['bird' => 0]);

        $result = $this->repo->searchPlayers($this->buildParams([
            'search_name' => 'PDB Bird',
            'bird' => 2,
            'bird_max' => 4,
        ]));

        $inRange = $this->findByPid($result['results'], 200100013);
        $outRange = $this->findByPid($result['results'], 200100014);
        self::assertNotNull($inRange);
        self::assertNull($outRange);
    }

    public function testRatingThresholdOo(): void
    {
        $this->insertTestPlayer(200100015, 'PDB OoHigh', ['oo' => 80]);
        $this->insertTestPlayer(200100016, 'PDB OoLow', ['oo' => 30]);

        $result = $this->repo->searchPlayers($this->buildParams([
            'search_name' => 'PDB Oo',
            'oo' => 50,
        ]));

        $high = $this->findByPid($result['results'], 200100015);
        $low = $this->findByPid($result['results'], 200100016);
        self::assertNotNull($high);
        self::assertNull($low);
    }

    public function testReservedWordColumnFilterDo(): void
    {
        $this->insertTestPlayer(200100017, 'PDB DoHigh', ['r_drive_off' => 85]);
        $this->insertTestPlayer(200100018, 'PDB DoLow', ['r_drive_off' => 20]);

        $result = $this->repo->searchPlayers($this->buildParams([
            'search_name' => 'PDB Do',
            'do' => 50,
        ]));

        $high = $this->findByPid($result['results'], 200100017);
        $low = $this->findByPid($result['results'], 200100018);
        self::assertNotNull($high);
        self::assertNull($low);
    }

    public function testReservedWordColumnFilterTo(): void
    {
        $this->insertTestPlayer(200100019, 'PDB ToHigh', ['r_trans_off' => 75]);
        $this->insertTestPlayer(200100020, 'PDB ToLow', ['r_trans_off' => 10]);

        $result = $this->repo->searchPlayers($this->buildParams([
            'search_name' => 'PDB To',
            'to' => 50,
        ]));

        $high = $this->findByPid($result['results'], 200100019);
        $low = $this->findByPid($result['results'], 200100020);
        self::assertNotNull($high);
        self::assertNull($low);
    }

    public function testMultipleFiltersAppliedSimultaneously(): void
    {
        $this->insertTestPlayer(200100001, 'PDB MultiOK', ['pos' => 'SG', 'age' => 25, 'exp' => 5, 'oo' => 70]);
        $this->insertTestPlayer(200100002, 'PDB MultiFl', ['pos' => 'C', 'age' => 25, 'exp' => 5, 'oo' => 70]);

        $result = $this->repo->searchPlayers($this->buildParams([
            'search_name' => 'PDB Multi',
            'pos' => 'SG',
            'age' => 30,
            'exp' => 3,
            'oo' => 60,
        ]));

        $ok = $this->findByPid($result['results'], 200100001);
        $fail = $this->findByPid($result['results'], 200100002);
        self::assertNotNull($ok);
        self::assertNull($fail);
    }

    public function testResultIncludesTeamnameFromLeftJoin(): void
    {
        $this->insertTestPlayer(200100001, 'PDB TeamJoin', ['tid' => 1]);

        $result = $this->repo->searchPlayers($this->buildParams(['search_name' => 'PDB TeamJoin']));

        $found = $this->findByPid($result['results'], 200100001);
        self::assertNotNull($found);
        self::assertArrayHasKey('teamname', $found);
        self::assertSame('Metros', $found['teamname']);
    }

    public function testGetPlayerByIdReturnsCorrectRow(): void
    {
        $this->insertTestPlayer(200100001, 'PDB ById');

        $row = $this->repo->getPlayerById(200100001);

        self::assertNotNull($row);
        self::assertSame(200100001, $row['pid']);
        self::assertSame('PDB ById', $row['name']);
    }

    public function testGetPlayerByIdReturnsNullForUnknown(): void
    {
        $row = $this->repo->getPlayerById(999999999);

        self::assertNull($row);
    }

    public function testResultOrderedByRetiredThenOrdinal(): void
    {
        $this->insertTestPlayer(200100001, 'PDB OrdHigh', ['ordinal' => 200, 'retired' => 0]);
        $this->insertTestPlayer(200100002, 'PDB OrdLow', ['ordinal' => 100, 'retired' => 0]);

        $result = $this->repo->searchPlayers($this->buildParams(['search_name' => 'PDB Ord']));

        self::assertGreaterThanOrEqual(2, $result['count']);
        // ordinal 100 should come before ordinal 200 (ASC)
        $positions = [];
        foreach ($result['results'] as $i => $row) {
            if ($row['pid'] === 200100001 || $row['pid'] === 200100002) {
                $positions[$row['pid']] = $i;
            }
        }
        self::assertLessThan($positions[200100001], $positions[200100002]);
    }

    public function testCollegeLikeFilter(): void
    {
        $this->insertTestPlayer(200100001, 'PDB College', ['college' => 'Duke University']);
        $this->insertTestPlayer(200100002, 'PDB NoColl', ['college' => 'Stanford']);

        $result = $this->repo->searchPlayers($this->buildParams([
            'search_name' => 'PDB ',
            'college' => 'Duke',
        ]));

        $duke = $this->findByPid($result['results'], 200100001);
        $stanford = $this->findByPid($result['results'], 200100002);
        self::assertNotNull($duke);
        self::assertNull($stanford);
    }

    public function testCountMatchesResultsArrayLength(): void
    {
        $this->insertTestPlayer(200100001, 'PDB CntTest');

        $result = $this->repo->searchPlayers($this->buildParams(['search_name' => 'PDB CntTest']));

        self::assertSame(count($result['results']), $result['count']);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function buildParams(array $overrides = []): array
    {
        $defaults = [
            'active' => 0,
            'search_name' => null,
            'pos' => null,
            'age' => null,
            'exp' => null,
            'exp_max' => null,
            'bird' => null,
            'bird_max' => null,
            'college' => null,
            'oo' => null,
            'r_drive_off' => null,
            'po' => null,
            'r_trans_off' => null,
            'od' => null,
            'dd' => null,
            'pd' => null,
            'td' => null,
            'talent' => null,
            'skill' => null,
            'intangibles' => null,
            'Clutch' => null,
            'Consistency' => null,
            'r_fga' => null,
            'r_fgp' => null,
            'r_fta' => null,
            'r_ftp' => null,
            'r_tga' => null,
            'r_tgp' => null,
            'r_orb' => null,
            'r_drb' => null,
            'r_ast' => null,
            'r_stl' => null,
            'r_blk' => null,
            'r_tvr' => null,
            'r_foul' => null,
        ];

        return array_merge($defaults, $overrides);
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
