<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanDirectCommonMysqliInstantiationRule;

/**
 * @extends RuleTestCase<BanDirectCommonMysqliInstantiationRule>
 */
final class BanDirectCommonMysqliInstantiationRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanDirectCommonMysqliInstantiationRule();
    }

    public function testFlagsDirectInstantiationInClassFile(): void
    {
        $this->analyse(
            [__DIR__ . '/../../phpstan-rules/Fixtures/DirectCommonMysqliInstantiation.php'],
            [
                [
                    'Direct instantiation of CommonMysqliRepository is banned in class files. '
                    . 'Inject CommonMysqliRepositoryInterface via the constructor instead.',
                    8,
                ],
            ],
        );
    }

    public function testAllowsInstantiationInTestFile(): void
    {
        $this->analyse(
            [__DIR__ . '/../DatabaseIntegration/CommonMysqliRepositoryTest.php'],
            [],
        );
    }
}
