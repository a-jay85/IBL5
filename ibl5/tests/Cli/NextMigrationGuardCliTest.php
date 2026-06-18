<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('cli')]
final class NextMigrationGuardCliTest extends TestCase
{
    public function testRefusesFromMainCheckout(): void
    {
        $t = sys_get_temp_dir() . '/nmig-guard-' . uniqid();
        mkdir($t . '/bin/lib', 0755, true);
        mkdir($t . '/ibl5/migrations', 0755, true);

        $scriptSrc = (string) realpath(__DIR__ . '/../../../bin/next-migration');
        $libSrc    = (string) realpath(__DIR__ . '/../../../bin/lib/git-helpers.sh');

        copy($scriptSrc, $t . '/bin/next-migration');
        chmod($t . '/bin/next-migration', 0755);
        copy($libSrc, $t . '/bin/lib/git-helpers.sh');

        // Seed one migration so get_max would otherwise succeed
        file_put_contents($t . '/ibl5/migrations/001-initial.sql', '-- placeholder');

        // .git as a DIRECTORY → main checkout layout
        mkdir($t . '/.git', 0755, true);

        $output = [];
        $exit = 0;
        exec(escapeshellarg($t . '/bin/next-migration') . ' 2>&1', $output, $exit);

        exec('rm -rf ' . escapeshellarg($t));

        self::assertNotSame(0, $exit, 'guard must exit non-zero from main checkout');
        self::assertStringContainsString('bin/wt-new', implode("\n", $output), 'error message must mention bin/wt-new');
    }
}
