<?php

declare(strict_types=1);

namespace Tests\Maintenance;

use Maintenance\BaselineDriftChecker;
use Maintenance\PhpstanBaselineCounter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BaselineDriftCheckerTest extends TestCase
{
    private const FILE_KEY = 'phpstan-baseline.neon';

    private BaselineDriftChecker $checker;
    private PhpstanBaselineCounter $counter;

    protected function setUp(): void
    {
        $this->checker = new BaselineDriftChecker();
        $this->counter = new PhpstanBaselineCounter();
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function fixtureCounts(string $name): array
    {
        return [
            self::FILE_KEY => $this->counter->countByIdentifier(__DIR__ . '/fixtures/baseline-drift/' . $name),
        ];
    }

    #[Test]
    public function testGrowthWithoutSnapshotRaiseFails(): void
    {
        $result = $this->checker->compare($this->fixtureCounts('grown.neon'), $this->fixtureCounts('base.neon'));

        self::assertCount(1, $result['increases']);
        self::assertStringContainsString('argument.type: 3 → 4', $result['increases'][0]);
        self::assertSame([], $result['warnings']);
    }

    #[Test]
    public function testNewIdentifierAbsentFromSnapshotFails(): void
    {
        $snapshot = $this->fixtureCounts('base.neon');
        unset($snapshot[self::FILE_KEY]['method.notFound']);

        $result = $this->checker->compare($this->fixtureCounts('base.neon'), $snapshot);

        self::assertCount(1, $result['increases']);
        self::assertStringContainsString('method.notFound: new (1 entries)', $result['increases'][0]);
    }

    #[Test]
    public function testShrinkWithUntouchedSnapshotPasses(): void
    {
        $result = $this->checker->compare($this->fixtureCounts('shrunk.neon'), $this->fixtureCounts('base.neon'));

        self::assertSame([], $result['increases']);
        self::assertSame([], $result['warnings']);
    }

    #[Test]
    public function testGrowthWithRaisedSnapshotPasses(): void
    {
        $result = $this->checker->compare($this->fixtureCounts('grown.neon'), $this->fixtureCounts('grown.neon'));

        self::assertSame([], $result['increases']);
        self::assertSame([], $result['warnings']);
    }

    #[Test]
    public function testDecreaseOverFiveWarnsButDoesNotFail(): void
    {
        $result = $this->checker->compare(
            [self::FILE_KEY => ['argument.type' => 5]],
            [self::FILE_KEY => ['argument.type' => 12]],
        );

        self::assertSame([], $result['increases']);
        self::assertCount(1, $result['warnings']);
        self::assertStringContainsString('decreased by 7', $result['warnings'][0]);

        $boundary = $this->checker->compare(
            [self::FILE_KEY => ['argument.type' => 5]],
            [self::FILE_KEY => ['argument.type' => 10]],
        );

        self::assertSame([], $boundary['increases']);
        self::assertSame([], $boundary['warnings']);
    }
}
