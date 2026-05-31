<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanDirectMysqliQueryRule;

/**
 * @extends RuleTestCase<BanDirectMysqliQueryRule>
 */
final class BanDirectMysqliQueryRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanDirectMysqliQueryRule();
    }

    public function testFlagsDirectMysqliQuery(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/DirectMysqliQuery.php'],
            [
                [
                    'Direct \mysqli::query() is banned outside the DB-access boundary. '
                    . 'Use a BaseMysqliRepository helper (execute()/fetchOne()/fetchAll()) instead — '
                    . 'raw query() bypasses prepared-statement parameterization.',
                    7,
                ],
                [
                    'Direct \mysqli::query() is banned outside the DB-access boundary. '
                    . 'Use a BaseMysqliRepository helper (execute()/fetchOne()/fetchAll()) instead — '
                    . 'raw query() bypasses prepared-statement parameterization.',
                    8,
                ],
            ],
        );
    }

    public function testAllowsQueryInDbAccessBoundary(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/BaseMysqliRepository.php'],
            [],
        );
    }

    public function testIgnoresQueryOnNonMysqliReceiver(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/NonMysqliQuery.php'],
            [],
        );
    }
}
