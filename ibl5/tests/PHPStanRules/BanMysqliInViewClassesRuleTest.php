<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanMysqliInViewClassesRule;

/**
 * @extends RuleTestCase<BanMysqliInViewClassesRule>
 */
final class BanMysqliInViewClassesRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanMysqliInViewClassesRule();
    }

    public function testFlagsMysqliPropertyInView(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/MysqliPropertyInView.php'],
            [
                [
                    'View classes must not hold a \mysqli property. '
                    . 'Inject pre-built domain objects via render parameters instead.',
                    7,
                ],
            ],
        );
    }

    public function testIgnoresCleanView(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/CleanView.php'],
            [],
        );
    }

    public function testIgnoresNonViewFiles(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/MysqliPropertyInService.php'],
            [],
        );
    }
}
