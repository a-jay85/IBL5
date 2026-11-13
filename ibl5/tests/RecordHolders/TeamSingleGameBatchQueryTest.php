<?php

declare(strict_types=1);

namespace Tests\RecordHolders;

use RecordHolders\TeamRecordRepository;
use Tests\WideUnit\WideUnitTestCase;

/**
 * Exact SQL and bound params of TeamRecordRepository::getTopTeamSingleGameBatch
 * on a mock connection. Infection runs only non-database tests per mutant, so
 * the full query text is pinned here; the row-level behavior is pinned by
 * DatabaseIntegration\TeamSingleGameBatchCharacterizationTest.
 */
final class TeamSingleGameBatchQueryTest extends WideUnitTestCase
{
    private const WHERE = 'WHERE bs.game_type = 1 AND bs.visitor_teamid BETWEEN 1 AND 28'
        . ' AND bs.home_teamid BETWEEN 1 AND 28';

    private const HINTED_POINTS = 'FROM `ibl_box_scores_teams` bs FORCE INDEX (`idx_gt_points`)'
        . ' STRAIGHT_JOIN `ibl_team_info` t ON t.team_name = bs.name';

    private const HINTED_AST = 'FROM `ibl_box_scores_teams` bs FORCE INDEX (`idx_gt_ast`)'
        . ' STRAIGHT_JOIN `ibl_team_info` t ON t.team_name = bs.name';

    private const PLAIN = 'FROM `ibl_box_scores_teams` bs JOIN `ibl_team_info` t ON t.team_name = bs.name';

    private const COMBO = '(bs.game_ftm + bs.game_3gm)';

    public function testThresholdQueryIsExactPerStat(): void
    {
        $this->runBatch();

        $expected = implode(' UNION ALL ', [
            self::thresholdBranch('pts', 'bs.calc_points', self::HINTED_POINTS, 'DESC'),
            self::thresholdBranch("O''Neal", 'bs.game_ast', self::HINTED_AST, 'ASC'),
            self::thresholdBranch('combo', self::COMBO, self::PLAIN, 'DESC'),
        ]);

        self::assertSame($expected, $this->preparedQuery(0));
    }

    public function testRecordQueryIsExactAndBindsEachNonNullThreshold(): void
    {
        $this->runBatch();

        $expected = implode(' UNION ALL ', [
            self::recordBranch('pts', 'bs.calc_points', self::HINTED_POINTS, ' AND bs.calc_points >= ?', 'DESC'),
            self::recordBranch(
                "O''Neal",
                'bs.game_ast',
                self::HINTED_AST,
                ' AND (bs.game_ast <= ? OR bs.game_ast IS NULL)',
                'ASC',
            ),
            self::recordBranch('combo', self::COMBO, self::PLAIN, '', 'DESC'),
        ]);

        self::assertCount(2, $this->mockDb()->getPreparedQueries());
        self::assertSame($expected, $this->preparedQuery(1));
        self::assertSame([87, 60], $this->mockDb()->getLastBoundParams());
        self::assertSame('ii', $this->mockDb()->getLastBoundTypes());
    }

    public function testResultKeepsEveryLabelAndCastsRowValues(): void
    {
        $result = $this->runBatch();

        self::assertSame(['pts', "O'Neal", 'combo'], array_keys($result));
        self::assertSame([[
            'teamid' => 3,
            'team_name' => 'Team Three',
            'date' => '2098-03-04',
            'box_id' => 12,
            'game_of_that_day' => 2,
            'oppTid' => 4,
            'opp_team_name' => 'Team Four',
            'value' => 91,
        ]], $result['pts']);
        self::assertSame([], $result["O'Neal"]);
        self::assertSame([], $result['combo']);
    }

    /**
     * @return array<string, list<array<string, int|string>>>
     */
    private function runBatch(): array
    {
        $db = $this->mockDb();
        $db->onQuery('AS threshold', [
            ['stat_type' => 'pts', 'threshold' => 87],
            ['stat_type' => "O'Neal", 'threshold' => '60'],
            ['stat_type' => 'combo', 'threshold' => null],
        ]);
        $db->onQuery('AS opp_team_name', [[
            'stat_type' => 'pts',
            'teamid' => '3',
            'team_name' => 'Team Three',
            'date' => '2098-03-04',
            'box_id' => '12',
            'game_of_that_day' => '2',
            'oppTid' => '4',
            'opp_team_name' => 'Team Four',
            'value' => '91',
        ]]);

        $repo = new TeamRecordRepository($db);

        return $repo->getTopTeamSingleGameBatch([
            'pts' => ['expression' => 'bs.calc_points', 'order' => 'DESC'],
            "O'Neal" => ['expression' => 'bs.game_ast', 'order' => 'ASC'],
            'combo' => ['expression' => self::COMBO, 'order' => 'DESC'],
        ], 'bs.game_type = 1');
    }

    private static function thresholdBranch(string $label, string $expression, string $source, string $order): string
    {
        return "(SELECT '" . $label . "' AS stat_type, ( SELECT " . $expression . ' ' . $source . ' ' . self::WHERE
            . ' ORDER BY ' . $expression . ' ' . $order . ' LIMIT 1 OFFSET 4 ) AS threshold)';
    }

    private static function recordBranch(string $label, string $expression, string $source, string $cut, string $order): string
    {
        return "(SELECT '" . $label . "' AS stat_type, t.teamid AS teamid, t.team_name, bs.game_date AS `date`,"
            . ' COALESCE(sch.box_id, 0) AS box_id, COALESCE(bs.game_of_that_day, 0) AS game_of_that_day,'
            . ' CASE WHEN t.teamid = bs.visitor_teamid THEN bs.home_teamid ELSE bs.visitor_teamid END AS oppTid,'
            . ' opp.team_name AS opp_team_name, ' . $expression . ' AS value ' . $source
            . ' LEFT JOIN `ibl_schedule` sch ON sch.game_date = bs.game_date'
            . ' AND sch.visitor_teamid = bs.visitor_teamid AND sch.home_teamid = bs.home_teamid'
            . ' LEFT JOIN `ibl_team_info` opp ON opp.teamid = CASE WHEN t.teamid = bs.visitor_teamid'
            . ' THEN bs.home_teamid ELSE bs.visitor_teamid END ' . self::WHERE . $cut
            . ' ORDER BY value ' . $order . ', bs.game_date ASC, bs.id ASC LIMIT 5)';
    }

    private function preparedQuery(int $index): string
    {
        $queries = $this->mockDb()->getPreparedQueries();
        self::assertArrayHasKey($index, $queries);

        return (string) preg_replace('/\s+/', ' ', $queries[$index]);
    }

    private function mockDb(): \Tests\WideUnit\Mocks\MockDatabase
    {
        self::assertNotNull($this->mockDb);

        return $this->mockDb;
    }
}
