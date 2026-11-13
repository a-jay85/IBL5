<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use JsbParser\AswFileParser;
use JsbParser\Importers\AswImporter;
use JsbParser\JsbImportRepository;
use JsbParser\JsbImportService;
use JsbParser\PlayerIdResolver;
use PHPUnit\Framework\Attributes\Group;
use Tests\JsbParser\Importers\BuildsAswFixtures;

require_once __DIR__ . '/../JsbParser/Importers/BuildsAswFixtures.php';

/**
 * Full .asw import cycles against real MariaDB: the canonical roster table, the
 * participant awards written beside it, the updater-path stale guard, and the
 * bulk path that skips it.
 */
#[Group('database')]
class AswImportCycleTest extends DatabaseTestCase
{
    use BuildsAswFixtures;

    private const PID_ALLSTAR = 200000101;
    private const PID_ROOKIE = 200000201;
    private const PID_THREE_POINT = 200000301;
    private const PID_DUNK = 200000401;

    private AswImporter $importer;
    private JsbImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $repository = new JsbImportRepository($this->db);
        $this->importer = new AswImporter($repository);
        $this->service = new JsbImportService($repository, new PlayerIdResolver($this->db));
    }

    public function testFullImportCycleWritesRostersAndAwards(): void
    {
        $bytes = $this->seedPlayersAndBuildBytes();

        $this->importer->import($bytes, 2090);

        self::assertSame($this->parsedSlotCount($bytes), $this->countRows('ibl_jsb_allstar_rosters', 'season_year = 2090'));
        self::assertSame(
            [
                'Eastern Conference All-Star|Asw Cycle AllStar',
                'Rookie-Sophomore Challenge|Asw Cycle Rookie',
                'Slam Dunk Competition|Asw Cycle Dunker',
                'Three-Point Contest|Asw Cycle Shooter',
            ],
            $this->awardRows(2090)
        );
        self::assertSame(0, $this->countRows('ibl_awards', "year = 2090 AND (award LIKE '% - Winner' OR award LIKE '% - MVP')"));
    }

    public function testSecondImportCycleAddsNoAwardRows(): void
    {
        $bytes = $this->seedPlayersAndBuildBytes();
        $this->importer->import($bytes, 2090);
        $awardsAfterFirst = $this->countRows('ibl_awards', 'year = 2090');
        self::assertSame(4, $awardsAfterFirst);

        $second = $this->importer->import($bytes, 2090);

        self::assertSame(0, $second->errors);
        self::assertSame($awardsAfterFirst, $this->countRows('ibl_awards', 'year = 2090'));
    }

    public function testStaleUpdaterImportLeavesTablesUnchanged(): void
    {
        $bytes = $this->seedPlayersAndBuildBytes();
        $path = $this->writeTempFile($bytes);
        try {
            $this->service->processAswFile($path, 2089);

            $result = $this->service->processAswData($bytes, 2090);
        } finally {
            unlink($path);
        }

        self::assertSame(0, $this->countRows('ibl_jsb_allstar_rosters', 'season_year = 2090'));
        self::assertSame(0, $this->countRows('ibl_jsb_allstar_scores', 'season_year = 2090'));
        self::assertSame(0, $this->countRows('ibl_awards', 'year = 2090'));
        self::assertStringContainsString('season 2089', implode("\n", $result->messages));
    }

    public function testBulkImportSkipsStaleCheck(): void
    {
        $bytes = $this->seedPlayersAndBuildBytes();
        $path = $this->writeTempFile($bytes);
        try {
            $this->service->processAswFile($path, 2089);
            $this->service->processAswFile($path, 2090);
        } finally {
            unlink($path);
        }

        self::assertSame($this->parsedSlotCount($bytes), $this->countRows('ibl_jsb_allstar_rosters', 'season_year = 2090'));
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Seeds the four named players on team 1, the 2090 conference mapping, and returns
     * a .asw file whose rosters lead with those players.
     */
    private function seedPlayersAndBuildBytes(): string
    {
        $bytes = $this->buildRookieAwareFixture();
        $rosters = AswFileParser::parse($bytes)['rosters'];

        $firstPids = [
            ['allstar_1', 'Asw Cycle AllStar'],
            ['rookie_1', 'Asw Cycle Rookie'],
            ['three_point', 'Asw Cycle Shooter'],
            ['dunk_contest', 'Asw Cycle Dunker'],
        ];
        foreach ($firstPids as [$eventType, $name]) {
            $this->insertTestPlayer($rosters[$eventType][0], $name, ['teamid' => 1]);
        }

        $this->insertRow('ibl_league_config', [
            'season_ending_year' => 2090,
            'team_slot' => 1,
            'team_name' => 'Asw Cycle Team',
            'teamid' => 1,
            'conference' => 'Eastern',
            'division' => 'Atlantic',
            'playoff_qualifiers_per_conf' => 4,
            'playoff_round1_format' => 'bo7',
            'playoff_round2_format' => 'bo7',
            'playoff_round3_format' => 'bo7',
            'playoff_round4_format' => 'bo7',
            'team_count' => 1,
        ]);

        return $bytes;
    }

    /**
     * buildAswFile() leaves the rookie rosters empty, so overwrite the first Rookie 1 line.
     */
    private function buildRookieAwareFixture(): string
    {
        $bytes = $this->buildAswFile(
            allstar1: [self::PID_ALLSTAR, self::PID_ALLSTAR + 1],
            threePointPids: [self::PID_THREE_POINT, self::PID_THREE_POINT + 1],
            dunkPids: [self::PID_DUNK, self::PID_DUNK + 1],
            dunkRound1Scores: [455, 480, 470],
            threePtRound1Scores: [18, 21],
        );
        $lines = explode("\r\n", $bytes);
        $lines[AswFileParser::ROOKIE_1_START] = (string) self::PID_ROOKIE;

        return str_pad(rtrim(implode("\r\n", $lines), ' '), AswFileParser::FILE_SIZE);
    }

    private function parsedSlotCount(string $bytes): int
    {
        $total = 0;
        foreach (AswFileParser::parse($bytes)['rosters'] as $pids) {
            $total += count($pids);
        }
        return $total;
    }

    private function writeTempFile(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'asw_cycle_');
        self::assertIsString($path);
        file_put_contents($path, $bytes);
        return $path;
    }

    /**
     * @return list<string> "award|name" rows for the year, sorted
     */
    private function awardRows(int $year): array
    {
        $result = $this->db->query("SELECT award, name FROM ibl_awards WHERE year = $year ORDER BY award, name");
        self::assertInstanceOf(\mysqli_result::class, $result);
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row['award'] . '|' . $row['name'];
        }
        $result->free();
        return $rows;
    }

    private function countRows(string $table, string $where): int
    {
        $result = $this->db->query("SELECT COUNT(*) AS c FROM $table WHERE $where");
        self::assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        $result->free();
        self::assertNotNull($row);
        return (int) $row['c'];
    }
}
