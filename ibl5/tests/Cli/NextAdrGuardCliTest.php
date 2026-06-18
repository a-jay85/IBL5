<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('cli')]
final class NextAdrGuardCliTest extends TestCase
{
    public function testRefusesFromMainCheckoutAndStrandsNoFile(): void
    {
        $t = sys_get_temp_dir() . '/nadr-guard-' . uniqid();
        mkdir($t . '/bin/lib', 0755, true);
        mkdir($t . '/ibl5/docs/decisions', 0755, true);

        $scriptSrc = (string) realpath(__DIR__ . '/../../../bin/next-adr');
        $libSrc    = (string) realpath(__DIR__ . '/../../../bin/lib/git-helpers.sh');
        $tplSrc    = (string) realpath(__DIR__ . '/../../../ibl5/docs/decisions/0000-template.md');

        copy($scriptSrc, $t . '/bin/next-adr');
        chmod($t . '/bin/next-adr', 0755);
        copy($libSrc, $t . '/bin/lib/git-helpers.sh');
        copy($tplSrc, $t . '/ibl5/docs/decisions/0000-template.md');

        // .git as a DIRECTORY → main checkout layout
        mkdir($t . '/.git', 0755, true);

        $output = [];
        $exit = 0;
        exec(escapeshellarg($t . '/bin/next-adr') . ' guard-test 2>&1', $output, $exit);

        $outputStr = implode("\n", $output);

        exec('rm -rf ' . escapeshellarg($t));

        self::assertNotSame(0, $exit, 'guard must exit non-zero from main checkout');
        self::assertStringContainsString('bin/wt-new', $outputStr, 'error message must mention bin/wt-new');
        self::assertEmpty(
            glob($t . '/ibl5/docs/decisions/[0-9][0-9][0-9][0-9]-guard-test.md'),
            'guard must not strand an ADR template file on the main checkout',
        );
    }
}
