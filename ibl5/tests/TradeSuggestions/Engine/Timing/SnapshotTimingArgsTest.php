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

    public function testNonPositiveTidRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SnapshotTimingArgs::parse(['--tid=0']);
    }
}
