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

    /**
     * @return array<string, array<string, int>>
     */
    private function withArgumentType(int $value): array
    {
        return [self::FILE_KEY => ['argument.type' => $value, 'method.notFound' => 1]];
    }

    #[Test]
    public function testGrowthIntoStaleHighSnapshotFailsWhenBaseNeonLower(): void
    {
        $result = $this->checker->compare(
            $this->fixtureCounts('grown.neon'),
            $this->withArgumentType(6),
            $this->fixtureCounts('base.neon'),
            $this->withArgumentType(6),
        );

        self::assertCount(1, $result['increases']);
        self::assertStringContainsString('merge-base 3 → 4', $result['increases'][0]);
    }

    #[Test]
    public function testGrowthWithRaisedSnapshotPassesAgainstBase(): void
    {
        $result = $this->checker->compare(
            $this->fixtureCounts('grown.neon'),
            $this->withArgumentType(7),
            $this->fixtureCounts('base.neon'),
            $this->withArgumentType(6),
        );

        self::assertSame([], $result['increases']);
    }

    #[Test]
    public function testShrinkWithUntouchedSnapshotPassesAgainstBase(): void
    {
        $result = $this->checker->compare(
            $this->fixtureCounts('shrunk.neon'),
            $this->withArgumentType(3),
            $this->fixtureCounts('base.neon'),
            $this->withArgumentType(3),
        );

        self::assertSame([], $result['increases']);
    }

    #[Test]
    public function testBaseRuleNeverLoosensSnapshotRule(): void
    {
        $result = $this->checker->compare(
            $this->fixtureCounts('grown.neon'),
            $this->withArgumentType(3),
            $this->fixtureCounts('base.neon'),
            $this->withArgumentType(1),
        );

        self::assertCount(1, $result['increases']);
        self::assertStringContainsString('argument.type: 3 → 4', $result['increases'][0]);
    }

    #[Test]
    public function testIdentifierAbsentFromBaseNeonCountsAsZero(): void
    {
        $baseNeon = $this->fixtureCounts('base.neon');
        unset($baseNeon[self::FILE_KEY]['method.notFound']);

        $result = $this->checker->compare(
            $this->fixtureCounts('base.neon'),
            $this->fixtureCounts('base.neon'),
            $baseNeon,
            $this->fixtureCounts('base.neon'),
        );

        self::assertCount(1, $result['increases']);
        self::assertStringContainsString('method.notFound: merge-base 0 → 1', $result['increases'][0]);
    }

    #[Test]
    public function testNullBaseFallsBackToSnapshotRuleOnly(): void
    {
        $result = $this->checker->compare(
            $this->fixtureCounts('grown.neon'),
            $this->withArgumentType(6),
            null,
            null,
        );

        self::assertSame([], $result['increases']);
    }

    #[Test]
    public function testDeclaredStackedBaseWinsOverMaster(): void
    {
        self::assertSame('feature-parent', $this->checker->resolveBaseRef(null, 'feature-parent'));
        self::assertSame('origin/master', $this->checker->resolveBaseRef(null, ''));
        self::assertSame('abc123', $this->checker->resolveBaseRef('abc123', 'feature-parent'));
    }

    #[Test]
    public function testRaiseOnlyUpdateNeverLowers(): void
    {
        $snapshot = $this->withArgumentType(3);

        $result = $this->checker->raiseOnly(
            $this->fixtureCounts('shrunk.neon'),
            $snapshot,
            $this->fixtureCounts('base.neon'),
            $snapshot,
        );

        self::assertSame($snapshot, $result);
    }

    #[Test]
    public function testRaiseOnlyUpdateRaisesGrowthOverStaleHighSnapshot(): void
    {
        $snapshot = $this->withArgumentType(6);
        $current = $this->fixtureCounts('grown.neon');
        $baseNeon = $this->fixtureCounts('base.neon');

        $result = $this->checker->raiseOnly($current, $snapshot, $baseNeon, $snapshot);

        self::assertSame(7, $result[self::FILE_KEY]['argument.type']);
        self::assertSame([], $this->checker->compare($current, $result, $baseNeon, $snapshot)['increases']);
    }

    #[Test]
    public function testRaiseOnlyUpdateAddsNewIdentifierAndKeepsKeyOrder(): void
    {
        $snapshot = [self::FILE_KEY => ['zeta.last' => 2, 'argument.type' => 3]];
        $current = [self::FILE_KEY => ['argument.type' => 3, 'method.notFound' => 1, 'zeta.last' => 2]];

        $result = $this->checker->raiseOnly($current, $snapshot, null, null);

        self::assertSame(['zeta.last', 'argument.type', 'method.notFound'], array_keys($result[self::FILE_KEY]));
        self::assertSame(1, $result[self::FILE_KEY]['method.notFound']);
    }
}
