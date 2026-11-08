<?php

declare(strict_types=1);

namespace Tests\Cli;

use Cli\CliFlagGuard;
use PHPUnit\Framework\TestCase;

final class CliFlagGuardTest extends TestCase
{
    public function testKnownExactFlagPasses(): void
    {
        $this->assertSame([], CliFlagGuard::unknownFlags(['migrate', '--status'], ['--status']));
        CliFlagGuard::assertKnown(['migrate', '--status'], ['--status']);
    }

    public function testNoFlagsPasses(): void
    {
        $this->expectNotToPerformAssertions();
        CliFlagGuard::assertKnown(['migrate'], ['--status']);
    }

    public function testMisspelledFlagThrowsWithUsageMessage(): void
    {
        try {
            CliFlagGuard::assertKnown(['migrate', '--statu'], ['--status']);
            self::fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame("Unknown option(s): --statu\nAccepted options: --status", $e->getMessage());
        }
    }

    public function testValueFlagAcceptsEqualsForm(): void
    {
        $this->assertSame(
            [],
            CliFlagGuard::unknownFlags(['x', '--owner=abc', '--resume='], ['--owner=', '--resume='])
        );
    }

    public function testValueFlagRejectsSpaceForm(): void
    {
        $this->assertSame(['--owner'], CliFlagGuard::unknownFlags(['x', '--owner', 'abc'], ['--owner=']));
    }

    public function testExactFlagRejectsValueSuffix(): void
    {
        $this->assertSame(['--status=1'], CliFlagGuard::unknownFlags(['x', '--status=1'], ['--status']));
    }

    public function testDoubleDashEndsOptionScanning(): void
    {
        $this->assertSame([], CliFlagGuard::unknownFlags(['x', '--', '--bogus'], []));
    }

    public function testSingleDashAndPositionalsPass(): void
    {
        $this->assertSame([], CliFlagGuard::unknownFlags(['x', '-', 'file.txt'], []));
    }

    public function testScriptNameAtIndexZeroIgnored(): void
    {
        $this->assertSame([], CliFlagGuard::unknownFlags(['--weird-script-name', '--status'], ['--status']));
    }

    public function testMultipleUnknownFlagsAllReported(): void
    {
        try {
            CliFlagGuard::assertKnown(['x', '--a', '-b', '--status'], ['--status', '--dry-run']);
            self::fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame(
                "Unknown option(s): --a -b\nAccepted options: --status --dry-run",
                $e->getMessage()
            );
        }
    }

    public function testEmptyAllowListRejectsAnyFlag(): void
    {
        try {
            CliFlagGuard::assertKnown(['x', '--dry-run'], []);
            self::fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame("Unknown option(s): --dry-run\nAccepted options: (none)", $e->getMessage());
        }
    }
}
