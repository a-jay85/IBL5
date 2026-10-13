<?php

declare(strict_types=1);

namespace Tests\Team;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Team\SplitStatsRepository;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * Characterization pin for SplitStatsRepository::buildSplitCondition.
 *
 * The SQL fragments below are test-local literals on purpose: the pin must fail if a production constant changes.
 * They are never concatenated into a query.
 */
class SplitStatsConditionCharacterizationTest extends TestCase
{
    private const JOIN_SCHEDULE = 'JOIN `ibl_schedule` sch ON bs.`game_date` = sch.`game_date` AND ((sch.home_teamid = bs.home_teamid AND sch.visitor_teamid = bs.visitor_teamid) OR (sch.home_teamid = bs.visitor_teamid AND sch.visitor_teamid = bs.home_teamid))';
    private const JOIN_OPP = 'JOIN `ibl_standings` opp_s ON opp_s.teamid = CASE WHEN bs.home_teamid = ? THEN bs.visitor_teamid ELSE bs.home_teamid END';
    private const W_HOME = 'AND bs.home_teamid = ?';
    private const W_ROAD = 'AND bs.visitor_teamid = ?';
    private const W_WINS = 'AND ((sch.home_teamid = ? AND sch.home_score > sch.visitor_score) OR (sch.visitor_teamid = ? AND sch.visitor_score > sch.home_score))';
    private const W_LOSSES = 'AND ((sch.home_teamid = ? AND sch.home_score < sch.visitor_score) OR (sch.visitor_teamid = ? AND sch.visitor_score < sch.home_score))';
    private const W_PRE = 'AND bs.`game_date` < ?';
    private const W_POST = 'AND bs.`game_date` >= ?';
    private const W_MONTH = 'AND MONTH(bs.`game_date`) = ?';
    private const W_DIV = 'AND opp_s.division = ?';
    private const W_CONF = 'AND opp_s.conference = ?';
    private const W_VS = 'AND CASE WHEN bs.home_teamid = ? THEN bs.visitor_teamid ELSE bs.home_teamid END = ?';

    /**
     * @return array{joins: string, where: string, types: string, params: list<int|string>, joinTypes: string, joinParams: list<int>}
     */
    private function buildCondition(string $key, int $teamid = 5, int $season = 2026): array
    {
        $method = new \ReflectionMethod(SplitStatsRepository::class, 'buildSplitCondition');
        /** @var array{joins: string, where: string, types: string, params: list<int|string>, joinTypes: string, joinParams: list<int>} $actual */
        $actual = $method->invoke(new SplitStatsRepository(new MockDatabase()), $key, $teamid, $season);

        return $actual;
    }

    /**
     * @return iterable<string, array{string, array{joins: string, where: string, types: string, params: list<int|string>, joinTypes: string, joinParams: list<int>}}>
     */
    public static function splitConditionProvider(): iterable
    {
        yield 'home' => ['home', ['joins' => '', 'where' => self::W_HOME, 'types' => 'i', 'params' => [5], 'joinTypes' => '', 'joinParams' => []]];
        yield 'road' => ['road', ['joins' => '', 'where' => self::W_ROAD, 'types' => 'i', 'params' => [5], 'joinTypes' => '', 'joinParams' => []]];
        yield 'wins' => ['wins', ['joins' => self::JOIN_SCHEDULE, 'where' => self::W_WINS, 'types' => 'ii', 'params' => [5, 5], 'joinTypes' => '', 'joinParams' => []]];
        yield 'losses' => ['losses', ['joins' => self::JOIN_SCHEDULE, 'where' => self::W_LOSSES, 'types' => 'ii', 'params' => [5, 5], 'joinTypes' => '', 'joinParams' => []]];
        yield 'pre_allstar' => ['pre_allstar', ['joins' => '', 'where' => self::W_PRE, 'types' => 's', 'params' => ['2026-02-01'], 'joinTypes' => '', 'joinParams' => []]];
        yield 'post_allstar' => ['post_allstar', ['joins' => '', 'where' => self::W_POST, 'types' => 's', 'params' => ['2026-02-05'], 'joinTypes' => '', 'joinParams' => []]];
        yield 'month_3' => ['month_3', ['joins' => '', 'where' => self::W_MONTH, 'types' => 'i', 'params' => [3], 'joinTypes' => '', 'joinParams' => []]];
        yield 'month_11' => ['month_11', ['joins' => '', 'where' => self::W_MONTH, 'types' => 'i', 'params' => [11], 'joinTypes' => '', 'joinParams' => []]];
        yield 'month non-numeric' => ['month_x', ['joins' => '', 'where' => self::W_MONTH, 'types' => 'i', 'params' => [0], 'joinTypes' => '', 'joinParams' => []]];
        yield 'div_atlantic' => ['div_atlantic', ['joins' => self::JOIN_OPP, 'where' => self::W_DIV, 'types' => 's', 'params' => ['Atlantic'], 'joinTypes' => 'i', 'joinParams' => [5]]];
        yield 'div_pacific' => ['div_pacific', ['joins' => self::JOIN_OPP, 'where' => self::W_DIV, 'types' => 's', 'params' => ['Pacific'], 'joinTypes' => 'i', 'joinParams' => [5]]];
        yield 'div wrong case' => ['div_ATLANTIC', ['joins' => self::JOIN_OPP, 'where' => self::W_DIV, 'types' => 's', 'params' => [''], 'joinTypes' => 'i', 'joinParams' => [5]]];
        yield 'div unknown' => ['div_nowhere', ['joins' => self::JOIN_OPP, 'where' => self::W_DIV, 'types' => 's', 'params' => [''], 'joinTypes' => 'i', 'joinParams' => [5]]];
        yield 'conf_eastern' => ['conf_eastern', ['joins' => self::JOIN_OPP, 'where' => self::W_CONF, 'types' => 's', 'params' => ['Eastern'], 'joinTypes' => 'i', 'joinParams' => [5]]];
        yield 'conf_western' => ['conf_western', ['joins' => self::JOIN_OPP, 'where' => self::W_CONF, 'types' => 's', 'params' => ['Western'], 'joinTypes' => 'i', 'joinParams' => [5]]];
        yield 'conf wrong case' => ['conf_Eastern', ['joins' => self::JOIN_OPP, 'where' => self::W_CONF, 'types' => 's', 'params' => [''], 'joinTypes' => 'i', 'joinParams' => [5]]];
        yield 'vs_7' => ['vs_7', ['joins' => '', 'where' => self::W_VS, 'types' => 'ii', 'params' => [5, 7], 'joinTypes' => '', 'joinParams' => []]];
        yield 'vs non-numeric' => ['vs_abc', ['joins' => '', 'where' => self::W_VS, 'types' => 'ii', 'params' => [5, 0], 'joinTypes' => '', 'joinParams' => []]];
        yield 'unknown' => ['unknown', ['joins' => '', 'where' => '', 'types' => '', 'params' => [], 'joinTypes' => '', 'joinParams' => []]];
        yield 'hostile' => ['home OR 1=1', ['joins' => '', 'where' => '', 'types' => '', 'params' => [], 'joinTypes' => '', 'joinParams' => []]];
        yield 'bare prefix word' => ['month', ['joins' => '', 'where' => '', 'types' => '', 'params' => [], 'joinTypes' => '', 'joinParams' => []]];
        yield 'upper-case exact' => ['HOME', ['joins' => '', 'where' => '', 'types' => '', 'params' => [], 'joinTypes' => '', 'joinParams' => []]];
        yield 'exact key plus suffix' => ['pre_allstar_x', ['joins' => '', 'where' => '', 'types' => '', 'params' => [], 'joinTypes' => '', 'joinParams' => []]];
        yield 'empty string' => ['', ['joins' => '', 'where' => '', 'types' => '', 'params' => [], 'joinTypes' => '', 'joinParams' => []]];
    }

    /**
     * @param array{joins: string, where: string, types: string, params: list<int|string>, joinTypes: string, joinParams: list<int>} $expected
     */
    #[DataProvider('splitConditionProvider')]
    public function testBuildSplitConditionShapeIsPinned(string $key, array $expected): void
    {
        self::assertSame($expected, $this->buildCondition($key));
    }

    public function testBuildSplitConditionBindsCallerTeamId(): void
    {
        $division = $this->buildCondition('div_central', 9, 2026);
        self::assertSame([9], $division['joinParams']);
        self::assertSame(['Central'], $division['params']);

        $wins = $this->buildCondition('wins', 9, 2026);
        self::assertSame([9, 9], $wins['params']);

        $post = $this->buildCondition('post_allstar', 5, 2031);
        self::assertSame(['2031-02-05'], $post['params']);
    }

    /**
     * @return iterable<string, array{string, string, list<int|string>}>
     */
    public static function unpinnedKeyProvider(): iterable
    {
        yield 'road' => ['road', self::W_ROAD, [2026, 5, 5, 5, 5]];
        yield 'losses' => ['losses', self::W_LOSSES, [2026, 5, 5, 5, 5, 5]];
        yield 'pre_allstar' => ['pre_allstar', self::W_PRE, [2026, 5, 5, 5, '2026-02-01']];
        yield 'post_allstar' => ['post_allstar', self::W_POST, [2026, 5, 5, 5, '2026-02-05']];
        yield 'month_3' => ['month_3', self::W_MONTH, [2026, 5, 5, 5, 3]];
        yield 'div_ATLANTIC' => ['div_ATLANTIC', self::W_DIV, [5, 2026, 5, 5, 5, '']];
    }

    /**
     * @param list<int|string> $expectedParams
     */
    #[DataProvider('unpinnedKeyProvider')]
    public function testGetSplitStatsQueryShapeForUnpinnedKeys(string $key, string $expectedWhere, array $expectedParams): void
    {
        $db = new MockDatabase();
        (new SplitStatsRepository($db))->getSplitStats(5, 2026, $key);

        $queries = $db->getPreparedQueries();
        self::assertCount(1, $queries);
        $sql = $queries[0];
        $normalized = (string) preg_replace('/\s+/', ' ', trim($sql));

        self::assertSame(substr_count($sql, '?'), count($db->getLastBoundParams()));
        self::assertStringContainsString($expectedWhere, $normalized);
        self::assertSame($expectedParams, $db->getLastBoundParams());

        if ($key === 'losses') {
            self::assertStringContainsString(self::JOIN_SCHEDULE, $normalized);
        } else {
            self::assertStringNotContainsString('JOIN `ibl_schedule`', $sql);
        }

        if ($key === 'div_ATLANTIC') {
            self::assertStringContainsString(self::JOIN_OPP, $normalized);
        } else {
            self::assertStringNotContainsString('JOIN `ibl_standings`', $sql);
        }
    }

    public function testHostileKeysBindNothingExtra(): void
    {
        $baselineDb = new MockDatabase();
        (new SplitStatsRepository($baselineDb))->getSplitStats(5, 2026, 'unknown');
        $baselineSql = (string) preg_replace('/\s+/', ' ', trim($baselineDb->getPreparedQueries()[0]));

        foreach (['home OR 1=1', "home'; DROP TABLE ibl_plr; --"] as $key) {
            $db = new MockDatabase();
            (new SplitStatsRepository($db))->getSplitStats(5, 2026, $key);
            $queries = $db->getPreparedQueries();
            self::assertCount(1, $queries);
            self::assertSame($baselineSql, (string) preg_replace('/\s+/', ' ', trim($queries[0])));
            self::assertSame([2026, 5, 5, 5], $db->getLastBoundParams());
            self::assertStringNotContainsString('1=1', $queries[0]);
            self::assertStringNotContainsString('DROP', $queries[0]);
        }

        $prefixCases = [
            'vs_7 OR 1=1' => [2026, 5, 5, 5, 5, 7],
            'month_3; --' => [2026, 5, 5, 5, 3],
        ];
        foreach ($prefixCases as $key => $expectedParams) {
            $db = new MockDatabase();
            (new SplitStatsRepository($db))->getSplitStats(5, 2026, $key);
            $queries = $db->getPreparedQueries();
            self::assertCount(1, $queries);
            self::assertSame($expectedParams, $db->getLastBoundParams());
            self::assertSame(substr_count($queries[0], '?'), count($expectedParams));
            self::assertStringNotContainsString('1=1', $queries[0]);
            self::assertStringNotContainsString('DROP', $queries[0]);
        }
    }
}
