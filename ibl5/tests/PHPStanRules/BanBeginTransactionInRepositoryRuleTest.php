<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanBeginTransactionInRepositoryRule;

/**
 * @extends RuleTestCase<BanBeginTransactionInRepositoryRule>
 */
final class BanBeginTransactionInRepositoryRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanBeginTransactionInRepositoryRule();
    }

    /**
     * @return list<string>
     */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/Fixtures/phpstan-fixtures.neon'];
    }

    public function testFlagsBeginTransactionInRepositorySubclass(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/SubclassCallsBeginTransaction.php'],
            [
                [
                    'Direct begin_transaction() calls are banned in repositories. '
                    . 'Use $this->transactional(function () { ... }) instead — '
                    . 'it handles savepoints when nested inside DatabaseTestCase or service-level transactions.',
                    9,
                ],
            ],
        );
    }

    public function testAllowsBeginTransactionInNonRepositoryClass(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/NonRepositoryCallsBeginTransaction.php'],
            [],
        );
    }
}
