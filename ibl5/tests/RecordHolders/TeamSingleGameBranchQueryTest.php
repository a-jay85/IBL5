<?php

declare(strict_types=1);

namespace Tests\RecordHolders;

use PHPUnit\Framework\TestCase;
use RecordHolders\RecordStatDefinitions;
use RecordHolders\TeamRecordRepository;

/**
 * Query-shape tests for the per-stat branch behind
 * TeamRecordRepository::getTopTeamSingleGameBatch (no database).
 * Row-level behavior is pinned by DatabaseIntegration\TeamSingleGameBatchCharacterizationTest.
 */
final class TeamSingleGameBranchQueryTest extends TestCase
{
    public function testBranchBindsDescThresholdAsParameter(): void
    {
        $branch = $this->buildBranch('pts', 'bs.calc_points', 'DESC', $this->regular(), 87);

        self::assertStringContainsString('bs.calc_points >= ?', $branch['sql']);
        self::assertStringContainsString('FORCE INDEX (`idx_gt_points`)', $branch['sql']);
        self::assertStringContainsString('STRAIGHT_JOIN', $branch['sql']);
        self::assertStringNotContainsString('87', $branch['sql']);
        self::assertSame([87], $branch['params']);
    }

    public function testBranchBindsAscThresholdWithNullGuard(): void
    {
        $branch = $this->buildBranch('pts', 'bs.calc_points', 'ASC', $this->regular(), 60);

        self::assertStringContainsString('(bs.calc_points <= ? OR bs.calc_points IS NULL)', $branch['sql']);
        self::assertStringContainsString('ORDER BY value ASC', $branch['sql']);
        self::assertSame([60], $branch['params']);
    }

    public function testBranchOmitsCutAndHintWhenThresholdNull(): void
    {
        $branch = $this->buildBranch('pts', 'bs.calc_points', 'DESC', $this->regular(), null);

        self::assertStringNotContainsString('?', $branch['sql']);
        self::assertStringNotContainsString('FORCE INDEX', $branch['sql']);
        self::assertStringNotContainsString('STRAIGHT_JOIN', $branch['sql']);
        self::assertStringContainsString('JOIN `ibl_team_info` t ON t.team_name = bs.name', $branch['sql']);
        self::assertSame([], $branch['params']);
    }

    public function testBranchOmitsIndexHintForCustomDateFilter(): void
    {
        $branch = $this->buildBranch(
            'ast',
            'bs.game_ast',
            'DESC',
            "bs.game_date BETWEEN '2098-03-01' AND '2098-03-28'",
            9,
        );

        self::assertStringContainsString('bs.game_ast >= ?', $branch['sql']);
        self::assertStringNotContainsString('FORCE INDEX', $branch['sql']);
        self::assertStringNotContainsString('STRAIGHT_JOIN', $branch['sql']);
        self::assertSame([9], $branch['params']);
    }

    public function testBranchOmitsIndexHintForUnmappedExpression(): void
    {
        $branch = $this->buildBranch(
            'combo',
            '(bs.game_2gm * 2 + bs.game_ftm + bs.game_3gm * 3)',
            'DESC',
            $this->regular(),
            100,
        );

        self::assertStringContainsString('>= ?', $branch['sql']);
        self::assertStringNotContainsString('FORCE INDEX', $branch['sql']);
    }

    public function testBranchEscapesApostropheInLabel(): void
    {
        $branch = $this->buildBranch("O'Neal", 'bs.game_ast', 'DESC', $this->regular(), null);

        self::assertStringContainsString("'O''Neal' AS stat_type", $branch['sql']);
    }

    public function testBranchCoercesUnknownOrderToDesc(): void
    {
        $branch = $this->buildBranch('x', 'bs.game_ast', 'sideways', $this->regular(), 5);

        self::assertStringContainsString('ORDER BY value DESC, bs.game_date ASC, bs.id ASC', $branch['sql']);
        self::assertStringContainsString('>= ?', $branch['sql']);
        self::assertStringEndsWith('LIMIT 5)', $branch['sql']);
    }

    public function testStatIndexesCoverEveryStatExpression(): void
    {
        $indexes = (new \ReflectionClassConstant(TeamRecordRepository::class, 'STAT_INDEXES'))->getValue();
        self::assertIsArray($indexes);

        $keys = array_keys($indexes);
        $expressions = array_column(RecordStatDefinitions::STATS, 'expression');
        sort($keys);
        sort($expressions);

        self::assertSame($expressions, $keys);
        foreach ($indexes as $index) {
            self::assertIsString($index);
            self::assertMatchesRegularExpression('/^idx_gt_[a-z0-9_]+$/', $index);
        }
    }

    public function testStatIndexesConstantIsPrivate(): void
    {
        self::assertTrue(
            (new \ReflectionClassConstant(TeamRecordRepository::class, 'STAT_INDEXES'))->isPrivate()
        );
    }

    private function regular(): string
    {
        $filters = RecordStatDefinitions::DATE_FILTERS;

        return (string) reset($filters);
    }

    /**
     * @return array{sql: string, params: list<int>}
     */
    private function buildBranch(string $label, string $expression, string $order, string $dateFilter, ?int $threshold): array
    {
        $method = new \ReflectionMethod(TeamRecordRepository::class, 'buildSingleGameBranch');
        /** @var array{sql: string, params: list<int>} $branch */
        $branch = $method->invoke(null, $label, ['expression' => $expression, 'order' => $order], $dateFilter, $threshold);

        return $branch;
    }
}
