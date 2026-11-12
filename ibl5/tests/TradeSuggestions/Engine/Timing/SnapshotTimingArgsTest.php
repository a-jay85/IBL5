<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine\Timing;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\Engine\Timing\SnapshotTimingArgs;

final class SnapshotTimingArgsTest extends TestCase
{
    public function testDefaultsAreTidOneRepeatThree(): void
    {
        $parsed = SnapshotTimingArgs::parse([], ['DB_HOST' => 'db', 'DB_NAME' => 'league', 'DB_USER' => 'u', 'DB_PASS' => 'p']);

        self::assertSame(['tid' => 1, 'repeat' => 3, 'host' => 'db', 'db' => 'league', 'user' => 'u', 'pass' => 'p'], $parsed);
        self::assertSame(4, SnapshotTimingArgs::parse(['--tid=4', '--repeat=2'])['tid']);
    }

    public function testEqualsFormRequired(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SnapshotTimingArgs::parse(['--tid', '4']);
    }

    public function testUnknownFlagRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SnapshotTimingArgs::parse(['--bogus=1']);
    }

    public function testBogusFlagReturnsTwoAndPrintsUsage(): void
    {
        $script = dirname(__DIR__, 4) . '/scripts/trade-suggestions-timing.php';
        $process = proc_open([PHP_BINARY, $script, '--bogus=1'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        self::assertSame(2, $code, $stdout . $stderr);
        self::assertStringContainsString('Unknown or malformed flag: --bogus=1', $stderr);
        self::assertStringContainsString(SnapshotTimingArgs::USAGE, $stderr);
    }

    public function testNonPositiveTidRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SnapshotTimingArgs::parse(['--tid=0']);
    }

    public function testFlagMustStartAtTheBeginningOfTheArgument(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SnapshotTimingArgs::parse(['x--tid=4']);
    }

    public function testFlagValueMustEndTheArgument(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SnapshotTimingArgs::parse(["--tid=4\nzzz"]);
    }

    public function testNumericValueWithTrailingJunkRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SnapshotTimingArgs::parse(['--tid=5x']);
    }

    public function testNumericValueWithLeadingJunkRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SnapshotTimingArgs::parse(['--repeat=x5']);
    }
}
