<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanProcOpenUncheckedExitRule;

/**
 * @extends RuleTestCase<BanProcOpenUncheckedExitRule>
 */
final class BanProcOpenUncheckedExitRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanProcOpenUncheckedExitRule();
    }

    public function testFlagsDiscardedProcCloseReturn(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/ProcOpenUncheckedExit.php'],
            [
                [
                    'proc_close() return value is discarded. Capture it and check the exit status — an unchecked subprocess failure is silent.',
                    7,
                ],
            ],
        );
    }

    public function testFlagsProcOpenWithNoProcClose(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/ProcOpenNoClose.php'],
            [
                [
                    'proc_open() is called but proc_close() never is. Close the process and check its exit status.',
                    6,
                ],
            ],
        );
    }

    public function testAllowsCapturedProcCloseReturn(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/ProcOpenCheckedExit.php'],
            [],
        );
    }

    public function testIgnoresFileWithoutProcOpen(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/ProcCloseWithoutProcOpen.php'],
            [],
        );
    }
}
