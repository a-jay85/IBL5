<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanServiceExtendsBaseRepositoryRule;

/**
 * @extends RuleTestCase<BanServiceExtendsBaseRepositoryRule>
 */
final class BanServiceExtendsBaseRepositoryRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanServiceExtendsBaseRepositoryRule();
    }

    public function testFlagsServiceExtendingBaseRepository(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/BadService.php'],
            [
                [
                    'Class BadService extends BaseMysqliRepository. '
                    . 'Per ADR-0001, Service classes compose Repository classes — they do not extend them. '
                    . 'Either rename to *Repository or introduce a separate Repository collaborator and inject it.',
                    5,
                ],
            ],
        );
    }

    public function testAllowsServiceWithoutBaseRepoParent(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/GoodService.php'],
            [],
        );
    }

    public function testAllowsRepositoryExtendingBase(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/RepositoryExtendsBase.php'],
            [],
        );
    }
}
