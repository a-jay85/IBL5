<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine\Sweep;

use League\League;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TradeSuggestions\Engine\Sweep\LeagueSweepArgs;

final class LeagueSweepArgsTest extends TestCase
{
    public function testDefaultsWithEmptyEnvironment(): void
    {
        $this->assertSame(
            ['maxTeams' => League::MAX_REAL_TEAMID, 'host' => '', 'db' => '', 'user' => '', 'pass' => ''],
            LeagueSweepArgs::parse([], []),
        );
    }

    public function testEnvironmentSuppliesEachDatabaseDefault(): void
    {
        $env = ['DB_HOST' => 'h1', 'DB_NAME' => 'n2', 'DB_USER' => 'u3', 'DB_PASS' => 'p4'];

        $this->assertSame(
            ['maxTeams' => League::MAX_REAL_TEAMID, 'host' => 'h1', 'db' => 'n2', 'user' => 'u3', 'pass' => 'p4'],
            LeagueSweepArgs::parse([], $env),
        );
    }

    public function testFlagsOverrideEnvironmentAndMaxTeams(): void
    {
        $result = LeagueSweepArgs::parse(
            ['--max-teams=5', '--host=a', '--db=b', '--user=c', '--pass=d'],
            ['DB_HOST' => 'h', 'DB_NAME' => 'n', 'DB_USER' => 'u', 'DB_PASS' => 'p'],
        );

        $this->assertSame(['maxTeams' => 5, 'host' => 'a', 'db' => 'b', 'user' => 'c', 'pass' => 'd'], $result);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badFlagProvider(): array
    {
        return [
            'unknown flag name' => ['--foo=1'],
            'unknown flag with known-looking value' => ['--foo=host'],
            'prefix before dashes' => ['x--host=a'],
            'newline inside value' => ["--host=a\nb"],
            'no equals' => ['--host'],
            'zero max-teams' => ['--max-teams=0'],
            'alpha max-teams' => ['--max-teams=abc'],
            'trailing junk max-teams' => ['--max-teams=3x'],
            'leading junk max-teams' => ['--max-teams=x3'],
            'empty max-teams' => ['--max-teams='],
            'plus-signed max-teams' => ['--max-teams=+5'],
            'space-prefixed max-teams' => ['--max-teams= 5'],
        ];
    }

    #[DataProvider('badFlagProvider')]
    public function testRejectsBadFlag(string $arg): void
    {
        $this->expectException(\InvalidArgumentException::class);

        LeagueSweepArgs::parse([$arg]);
    }

    public function testMaxTeamsOfOneIsAccepted(): void
    {
        $this->assertSame(1, LeagueSweepArgs::parse(['--max-teams=1'])['maxTeams']);
    }
}
