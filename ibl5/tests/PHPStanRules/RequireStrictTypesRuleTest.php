<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\RequireStrictTypesRule;

/**
 * @extends RuleTestCase<RequireStrictTypesRule>
 */
final class RequireStrictTypesRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new RequireStrictTypesRule();
    }

    public function testFlagsFileMissingStrictTypesDeclarationInsideClassesDirectory(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/MissingStrictTypesFixture.php'],
            [
                [
                    'Missing declare(strict_types=1) at the top of the file.',
                    3,
                ],
            ],
        );
    }

    public function testAllowsFileWithStrictTypesDeclarationInsideClassesDirectory(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/HasStrictTypesFixture.php'],
            [],
        );
    }

    public function testAllowsFileOutsideClassesDirectoryEvenWithoutStrictTypes(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/NoStrictTypesOutsideClassesFixture.php'],
            [],
        );
    }
}
