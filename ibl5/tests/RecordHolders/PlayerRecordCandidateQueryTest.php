<?php

declare(strict_types=1);

namespace Tests\RecordHolders;

use PHPUnit\Framework\TestCase;
use RecordHolders\PlayerRecordRepository;
use RecordHolders\RecordStatDefinitions;

/**
 * Query-shape tests for the candidate query behind
 * PlayerRecordRepository::getTopPlayerSingleGameBatch (no database).
 * Row-level behavior is pinned by DatabaseIntegration\PlayerRecordRepositoryTest.
 */
final class PlayerRecordCandidateQueryTest extends TestCase
{
    public function testCandidateQueryBindsThresholdAsParameter(): void
    {
        $query = $this->buildCandidateQuery('bs.game_ast', 'bs.game_type = 1', 46);

        self::assertStringContainsString('bs.game_ast >= ?', $query['sql']);
        self::assertStringContainsString('FORCE INDEX (`idx_gt_ast`)', $query['sql']);
        self::assertStringNotContainsString('46', $query['sql']);
        self::assertSame([46], $query['params']);
    }

    public function testCandidateQueryOmitsThresholdWhenNull(): void
    {
        $query = $this->buildCandidateQuery('bs.game_ast', 'bs.game_type = 1', null);

        self::assertStringNotContainsString('>= ?', $query['sql']);
        self::assertStringEndsWith('LIMIT 500', $query['sql']);
        self::assertSame([], $query['params']);
    }

    public function testCandidateQueryOmitsIndexHintForUnmappedExpression(): void
    {
        $query = $this->buildCandidateQuery('bs.game_pf', 'bs.game_type = 1', null);

        self::assertStringNotContainsString('FORCE INDEX', $query['sql']);
    }

    public function testStatIndexesCoverEveryStatExpression(): void
    {
        $indexes = (new \ReflectionClassConstant(PlayerRecordRepository::class, 'STAT_INDEXES'))->getValue();
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
            (new \ReflectionClassConstant(PlayerRecordRepository::class, 'STAT_INDEXES'))->isPrivate()
        );
    }

    /**
     * @return array{sql: string, params: list<int>}
     */
    private function buildCandidateQuery(string $expression, string $dateFilter, ?int $threshold): array
    {
        $method = new \ReflectionMethod(PlayerRecordRepository::class, 'buildCandidateQuery');
        /** @var array{sql: string, params: list<int>} $query */
        $query = $method->invoke(null, $expression, $dateFilter, $threshold);

        return $query;
    }
}
