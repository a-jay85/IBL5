<?php

declare(strict_types=1);

namespace Tests\RecordHolders;

use League\League;
use RecordHolders\PlayerRecordRepository;
use Tests\WideUnit\WideUnitTestCase;

/**
 * Pins the SQL that PlayerRecordRepository::getTopPlayerSingleGameBatch sends
 * through a mock connection. The database-group tests exercise the same code
 * against MariaDB, but the mutation run excludes that group, so these tests
 * are what catch a mutated query builder there.
 */
final class PlayerRecordRepositoryBatchQueryTest extends WideUnitTestCase
{
    private const DATE_FILTER = 'bs.game_type = 1';

    private PlayerRecordRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PlayerRecordRepository($this->mockDb);
    }

    protected function tearDown(): void
    {
        unset($this->repository);
        parent::tearDown();
    }

    public function testThresholdQueryHasOneEscapedBranchPerLabel(): void
    {
        $this->runBatch();

        $max = League::MAX_REAL_TEAMID;
        $where = "WHERE bs.game_type = 1 AND bs.visitor_teamid BETWEEN 1 AND {$max} AND bs.home_teamid BETWEEN 1 AND {$max}";
        $expected = "(SELECT 'Player''s Assists' AS stat_type, ( SELECT bs.game_ast FROM ibl_box_scores bs FORCE INDEX (idx_gt_ast) {$where} ORDER BY bs.game_ast DESC LIMIT 1 OFFSET 499 ) AS threshold)"
            . " UNION ALL (SELECT 'Fouls' AS stat_type, ( SELECT bs.game_pf FROM ibl_box_scores bs {$where} ORDER BY bs.game_pf DESC LIMIT 1 OFFSET 499 ) AS threshold)"
            . " UNION ALL (SELECT 'Blocks' AS stat_type, ( SELECT bs.game_blk FROM ibl_box_scores bs FORCE INDEX (idx_gt_blk) {$where} ORDER BY bs.game_blk DESC LIMIT 1 OFFSET 499 ) AS threshold)";

        self::assertSame($expected, $this->normalize($this->executedQueries()[0]));
    }

    public function testMainQueryWrapsEachCandidateQueryInItsOwnBranch(): void
    {
        $this->runBatch();

        $main = $this->normalize($this->executedQueries()[1]);
        $max = League::MAX_REAL_TEAMID;
        $where = "WHERE bs.game_type = 1 AND bs.visitor_teamid BETWEEN 1 AND {$max} AND bs.home_teamid BETWEEN 1 AND {$max}";
        $branches = [
            "Player''s Assists" => "FORCE INDEX (idx_gt_ast) {$where} AND bs.game_ast >= 46 ORDER BY bs.game_ast DESC",
            'Fouls' => "{$where} AND bs.game_pf >= 7 ORDER BY bs.game_pf DESC",
            'Blocks' => "FORCE INDEX (idx_gt_blk) {$where} ORDER BY bs.game_blk DESC",
        ];

        foreach ($branches as $label => $candidateTail) {
            $pattern = '/\(SELECT \'' . preg_quote($label, '/') . '\' AS stat_type, cand\.pid, p\.name, .*? cand\.value FROM \( SELECT bs\.pid, bs\.game_date, bs\.visitor_teamid, bs\.home_teamid, \(bs\.season_year\) AS season_year, \S+ AS value FROM ibl_box_scores bs '
                . preg_quote($candidateTail, '/')
                . ', bs\.game_date ASC, bs\.id ASC LIMIT 500 \) cand JOIN ibl_plr p .*? LIMIT 5\)/';
            self::assertMatchesRegularExpression($pattern, $main, 'branch for ' . $label);
        }

        self::assertSame(3, preg_match_all('/\(SELECT \'/', $main));
        self::assertSame(3, substr_count($main, 'LIMIT 500'));
        self::assertSame(2, substr_count($main, ' UNION ALL '));
        self::assertStringEndsWith('LIMIT 5)', $main);
    }

    public function testThresholdsAreBoundInLabelOrderAndNullThresholdIsSkipped(): void
    {
        $this->runBatch();

        self::assertSame([46, 7], $this->mockDb->getLastBoundParams());
        self::assertSame('ii', $this->mockDb->getLastBoundTypes());
    }

    public function testResultsAreGroupedByLabel(): void
    {
        $result = $this->runBatch();

        self::assertSame(["Player's Assists", 'Fouls', 'Blocks'], array_keys($result));
        self::assertSame(927, $result["Player's Assists"][0]['pid']);
        self::assertSame(100, $result['Fouls'][0]['pid']);
        self::assertSame([], $result['Blocks']);
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function runBatch(): array
    {
        $this->mockDb->onQuery('AS threshold', [
            ['stat_type' => "Player's Assists", 'threshold' => '46'],
            ['stat_type' => 'Fouls', 'threshold' => '7'],
            ['stat_type' => 'Blocks', 'threshold' => null],
        ]);
        $this->mockDb->onQuery('cand\.value', [
            $this->row("Player's Assists", 927),
            $this->row('Fouls', 100),
        ]);

        /** @var array<string, list<array<string, mixed>>> $result */
        $result = $this->repository->getTopPlayerSingleGameBatch(
            [
                "Player's Assists" => 'bs.game_ast',
                'Fouls' => 'bs.game_pf',
                'Blocks' => 'bs.game_blk',
            ],
            self::DATE_FILTER,
        );

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $label, int $pid): array
    {
        return [
            'stat_type' => $label,
            'pid' => $pid,
            'name' => 'Player ' . $pid,
            'teamid' => 1,
            'team_name' => 'Celtics',
            'date' => '2098-03-01',
            'box_id' => 0,
            'game_of_that_day' => 0,
            'oppTid' => 2,
            'opp_team_name' => 'Heat',
            'value' => 12,
        ];
    }

    /**
     * @return list<string>
     */
    private function executedQueries(): array
    {
        $queries = $this->mockDb->getExecutedQueries();
        self::assertCount(2, $queries);

        return $queries;
    }

    private function normalize(string $sql): string
    {
        $sql = trim((string) preg_replace('/\s+/', ' ', $sql));

        return (string) preg_replace('/^WITH _game_of_day AS .*? (?=\(SELECT \')/', '', $sql);
    }
}
