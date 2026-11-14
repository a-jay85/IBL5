<?php

declare(strict_types=1);

namespace Tests\WideUnit\Mocks;

use PHPUnit\Framework\TestCase;

/**
 * MockMysqliResult::fetch_all() must honour the mode argument the way
 * mysqli_result::fetch_all() does, so a caller that forgets MYSQLI_ASSOC fails
 * in tests instead of only in production.
 */
final class MockMysqliResultFetchAllTest extends TestCase
{
    /**
     * @param list<array<string, mixed>> $rows
     */
    private static function buildResult(array $rows): MockMysqliResult
    {
        return new MockMysqliResult(new MockDatabaseResult($rows));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function seed(): array
    {
        return [
            ['tid' => 1, 'name' => 'A'],
            ['tid' => 2, 'name' => 'B'],
        ];
    }

    public function testFetchAllNumModeReturnsListRows(): void
    {
        $result = self::buildResult(self::seed());

        $this->assertSame([[1, 'A'], [2, 'B']], $result->fetch_all(MYSQLI_NUM));
        $this->assertSame([[1, 'A'], [2, 'B']], $result->fetch_all());
    }

    public function testFetchAllAssocModeReturnsAssocRows(): void
    {
        $result = self::buildResult(self::seed());

        $this->assertSame(self::seed(), $result->fetch_all(MYSQLI_ASSOC));
    }

    public function testFetchAllBothModeReturnsMergedRows(): void
    {
        $result = self::buildResult(self::seed());

        $rows = $result->fetch_all(MYSQLI_BOTH);

        $this->assertSame(['tid' => 1, 'name' => 'A', 0 => 1, 1 => 'A'], $rows[0]);
        $this->assertSame(['tid' => 2, 'name' => 'B', 0 => 2, 1 => 'B'], $rows[1]);
    }

    public function testFetchAllOnEmptyResultReturnsEmptyList(): void
    {
        $result = self::buildResult([]);

        $this->assertSame([], $result->fetch_all(MYSQLI_NUM));
        $this->assertSame([], $result->fetch_all(MYSQLI_ASSOC));
        $this->assertSame([], $result->fetch_all(MYSQLI_BOTH));
    }
}
