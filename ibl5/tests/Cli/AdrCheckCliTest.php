<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('cli')]
final class AdrCheckCliTest extends TestCase
{
    public function testHelpFlagPrintsUsageAndExitsZero(): void
    {
        $output = [];
        $exit = 0;
        exec(escapeshellcmd(realpath(__DIR__ . '/../../../bin/adr-check')) . ' --help 2>&1', $output, $exit);
        self::assertSame(0, $exit);
        self::assertStringContainsString('Decision-trigger gate', implode("\n", $output));
    }

    public function testUnknownFlagExitsTwo(): void
    {
        exec(escapeshellcmd(realpath(__DIR__ . '/../../../bin/adr-check')) . ' --bogus 2>&1', $unused, $exit);
        self::assertSame(2, $exit);
    }
}
