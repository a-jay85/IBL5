<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PlrParser\PlrParserRepository;

/**
 * Tests PlrParserRepository against real MariaDB — massive upserts
 * into ibl_plr (121 params) and ibl_hist (42 params).
 */
class PlrParserRepositoryTest extends DatabaseTestCase
{
    private PlrParserRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new PlrParserRepository($this->db);
    }

    // ── upsertPlayer ────────────────────────────────────────────

    public function testUpsertPlayerInsertsNewRow(): void
    {
        $data = $this->buildPlrData(200130001, 'PLR Parse Plyr');

        $affected = $this->repo->upsertPlayer($data);

        self::assertGreaterThanOrEqual(1, $affected);

        $stmt = $this->db->prepare('SELECT name, age, pos, tid FROM ibl_plr WHERE pid = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $pid);
        $pid = 200130001;
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame('PLR Parse Plyr', $row['name']);
        self::assertSame(25, $row['age']);
        self::assertSame('PG', $row['pos']);
        self::assertSame(1, $row['tid']);
    }

    public function testUpsertPlayerUpdatesOnDuplicateKey(): void
    {
        $data = $this->buildPlrData(200130002, 'PLR Original');
        $this->repo->upsertPlayer($data);

        // Update same pid with different name and age
        $data2 = $this->buildPlrData(200130002, 'PLR Updated', ['age' => 30]);
        $this->repo->upsertPlayer($data2);

        $stmt = $this->db->prepare('SELECT name, age FROM ibl_plr WHERE pid = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $pid);
        $pid = 200130002;
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame('PLR Updated', $row['name']);
        self::assertSame(30, $row['age']);
    }

    // ── upsertHistoricalStats ───────────────────────────────────

    public function testUpsertHistoricalStatsInsertsNewRow(): void
    {
        $this->insertTestPlayer(200130003, 'PLR Hist Plyr');
        $data = $this->buildHistData(200130003, 'PLR Hist Plyr', 2099);

        $affected = $this->repo->upsertHistoricalStats($data);

        self::assertGreaterThanOrEqual(1, $affected);

        $stmt = $this->db->prepare('SELECT pts, games, team FROM ibl_hist WHERE pid = ? AND year = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('ii', $pid, $year);
        $pid = 200130003;
        $year = 2099;
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(750, $row['pts']);
        self::assertSame(50, $row['games']);
        self::assertSame('Metros', $row['team']);
    }

    public function testUpsertHistoricalStatsUpdatesOnDuplicateKey(): void
    {
        $this->insertTestPlayer(200130004, 'PLR Hist Upd');
        $data = $this->buildHistData(200130004, 'PLR Hist Upd', 2099);
        $this->repo->upsertHistoricalStats($data);

        // Same pid+name+year, different pts
        $data2 = $this->buildHistData(200130004, 'PLR Hist Upd', 2099, ['seasonPTS' => 1200]);
        $this->repo->upsertHistoricalStats($data2);

        $stmt = $this->db->prepare('SELECT pts FROM ibl_hist WHERE pid = ? AND year = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('ii', $pid, $year);
        $pid = 200130004;
        $year = 2099;
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(1200, $row['pts']);
    }

    // ── Data builders ───────────────────────────────────────────

    /**
     * Build a complete 121-field data array for upsertPlayer().
     *
     * @param array<string, int|string> $overrides
     * @return array<string, int|string>
     */
    private function buildPlrData(int $pid, string $name, array $overrides = []): array
    {
        $defaults = [
            'ordinal' => 1, 'name' => $name, 'age' => 25, 'pid' => $pid,
            'tid' => 1, 'peak' => 27, 'pos' => 'PG',
            'ratingOO' => 50, 'ratingOD' => 50, 'ratingDO' => 50, 'ratingDD' => 50,
            'ratingPO' => 50, 'ratingPD' => 50, 'ratingTO' => 50, 'ratingTD' => 50,
            'clutch' => 50, 'consistency' => 50,
            'PGDepth' => 1, 'SGDepth' => 0, 'SFDepth' => 0, 'PFDepth' => 0, 'CDepth' => 0,
            'canPlayInGame' => 1,
            'seasonGamesStarted' => 40, 'seasonGamesPlayed' => 50,
            'seasonMIN' => 1600, 'seasonFGM' => 300, 'seasonFGA' => 600,
            'seasonFTM' => 100, 'seasonFTA' => 120, 'season3GM' => 50, 'season3GA' => 130,
            'seasonORB' => 40, 'seasonDRB' => 160, 'seasonAST' => 150,
            'seasonSTL' => 50, 'seasonTVR' => 80, 'seasonBLK' => 20, 'seasonPF' => 100,
            'talent' => 50, 'skill' => 50, 'intangibles' => 50, 'coach' => 50,
            'loyalty' => 50, 'playingTime' => 50, 'playForWinner' => 50,
            'tradition' => 50, 'security' => 50,
            'exp' => 5, 'bird' => 3, 'currentContractYear' => 1, 'totalContractYears' => 3,
            'contractYear1' => 1500, 'contractYear2' => 1600, 'contractYear3' => 1700,
            'contractYear4' => 0, 'contractYear5' => 0, 'contractYear6' => 0,
            'freeAgentSigningFlag' => 0,
            'seasonHighPTS' => 35, 'seasonHighREB' => 12, 'seasonHighAST' => 15,
            'seasonHighSTL' => 5, 'seasonHighBLK' => 3,
            'seasonHighDoubleDoubles' => 10, 'seasonHighTripleDoubles' => 2,
            'seasonPlayoffHighPTS' => 0, 'seasonPlayoffHighREB' => 0,
            'seasonPlayoffHighAST' => 0, 'seasonPlayoffHighSTL' => 0, 'seasonPlayoffHighBLK' => 0,
            'careerSeasonHighPTS' => 35, 'careerSeasonHighREB' => 12,
            'careerSeasonHighAST' => 15, 'careerSeasonHighSTL' => 5, 'careerSeasonHighBLK' => 3,
            'careerSeasonHighDoubleDoubles' => 10, 'careerSeasonHighTripleDoubles' => 2,
            'careerPlayoffHighPTS' => 0, 'careerPlayoffHighREB' => 0,
            'careerPlayoffHighAST' => 0, 'careerPlayoffHighSTL' => 0, 'careerPlayoffHighBLK' => 0,
            'careerGP' => 200, 'careerMIN' => 6000,
            'careerFGM' => 1200, 'careerFGA' => 2500,
            'careerFTM' => 400, 'careerFTA' => 500,
            'career3GM' => 200, 'career3GA' => 550,
            'careerORB' => 150, 'careerDRB' => 600, 'careerREB' => 750,
            'careerAST' => 600, 'careerSTL' => 200,
            'careerTVR' => 300, 'careerBLK' => 80, 'careerPF' => 400, 'careerPTS' => 3000,
            'rating2GA' => 50, 'rating2GP' => 50, 'ratingFTA' => 50, 'ratingFTP' => 50,
            'rating3GA' => 50, 'rating3GP' => 50,
            'ratingORB' => 50, 'ratingDRB' => 50, 'ratingAST' => 50,
            'ratingSTL' => 50, 'ratingTVR' => 50, 'ratingBLK' => 50,
            'draftRound' => 1, 'draftPickNumber' => 5, 'injuryDaysLeft' => 0,
            'heightFT' => 6, 'heightIN' => 3, 'weight' => 195, 'draftYear' => 2020,
            'ratingFOUL' => 50,
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Build a complete 42-field data array for upsertHistoricalStats().
     *
     * @param array<string, int|string> $overrides
     * @return array<string, int|string>
     */
    private function buildHistData(int $pid, string $name, int $year, array $overrides = []): array
    {
        $defaults = [
            'pid' => $pid, 'name' => $name, 'year' => $year,
            'team' => 'Metros', 'tid' => 1,
            'seasonGamesPlayed' => 50, 'seasonMIN' => 1600,
            'seasonFGM' => 300, 'seasonFGA' => 600,
            'seasonFTM' => 100, 'seasonFTA' => 120,
            'season3GM' => 50, 'season3GA' => 130,
            'seasonORB' => 40, 'seasonREB' => 200,
            'seasonAST' => 150, 'seasonSTL' => 50,
            'seasonBLK' => 20, 'seasonTVR' => 80,
            'seasonPF' => 100, 'seasonPTS' => 750,
            'rating2GA' => 50, 'rating2GP' => 50,
            'ratingFTA' => 50, 'ratingFTP' => 50,
            'rating3GA' => 50, 'rating3GP' => 50,
            'ratingORB' => 50, 'ratingDRB' => 50,
            'ratingAST' => 50, 'ratingSTL' => 50,
            'ratingBLK' => 50, 'ratingTVR' => 50,
            'ratingOO' => 50, 'ratingOD' => 50,
            'ratingDO' => 50, 'ratingDD' => 50,
            'ratingPO' => 50, 'ratingPD' => 50,
            'ratingTO' => 50, 'ratingTD' => 50,
            'currentSeasonSalary' => 1500,
        ];

        return array_merge($defaults, $overrides);
    }
}
