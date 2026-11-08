<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanUnguardedArgvFlagRule;

/** @extends RuleTestCase<BanUnguardedArgvFlagRule> */
final class BanUnguardedArgvFlagRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanUnguardedArgvFlagRule();
    }

    public function testFlagsInArrayLookupWithoutGuard(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/UnguardedArgvFlagScript.php'],
            [[BanUnguardedArgvFlagRule::MESSAGE, 5]],
        );
    }

    public function testFlagsArgvLoopWithoutGuard(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/UnguardedArgvLoopScript.php'],
            [[BanUnguardedArgvFlagRule::MESSAGE, 6]],
        );
    }

    public function testFlagsServerArgvReadWithoutGuard(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/UnguardedServerArgvScript.php'],
            [[BanUnguardedArgvFlagRule::MESSAGE, 5]],
        );
    }

    public function testPassesScriptCallingCliFlagGuard(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/GuardedArgvFlagScript.php'],
            [],
        );
    }

    public function testIgnoresPositionalOnlyScriptWithNearMissLiterals(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/PositionalArgvScript.php'],
            [],
        );
    }

    public function testSkipsScriptOwnedByGetoptRule(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/GetoptArgvFlagScript.php'],
            [],
        );
    }

    public function testSkipsFilesUnderClasses(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/ArgvFlagInClass.php'],
            [],
        );
    }
}
