<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * playedCondition() returns one of two literal predicates, which keeps every query that
 * concatenates it inert under BanSqlStringConcatenationRule. Its literal-union @param is
 * what holds that set closed, so an alias outside it must fail analysis.
 *
 * @extends RuleTestCase<Rule<MethodCall>>
 */
final class PlayedConditionLiteralUnionTest extends RuleTestCase
{
    /**
     * PHPStan's own method-call argument check. Fetched by service name because its class
     * sits outside PHPStan's backward-compatibility promise.
     *
     * @return Rule<MethodCall>
     */
    protected function getRule(): Rule
    {
        return self::getContainer()->getByType('PHPStan\Rules\Methods\CallMethodsRule');
    }

    public function testPlayedConditionRejectsAliasOutsideLiteralUnion(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/PlayedConditionLiteralUnion.php'],
            [
                ["Parameter #1 \$alias of method Database\\BaseMysqliRepository::playedCondition() expects ''|'bs', 'xx' given.", 18],
            ],
        );
    }
}
