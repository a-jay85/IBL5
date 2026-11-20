<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanRawDbHandleInEntryPointRule;

/**
 * @extends RuleTestCase<BanRawDbHandleInEntryPointRule>
 */
final class BanRawDbHandleInEntryPointRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanRawDbHandleInEntryPointRule();
    }

    public function testFlagsPublicDbMethodInFactory(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/Module/Factories/RawDbFactory.php'],
            [[BanRawDbHandleInEntryPointRule::FACTORY_MESSAGE, 13]],
        );
    }

    public function testAllowsNonPublicOrNonMysqliDbMethodsInFactory(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/Module/Factories/CleanFactory.php'],
            [],
        );
    }

    public function testAllowsDbMethodOutsideFactories(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/RawDbOutsideFactories.php'],
            [],
        );
    }

    public function testFlagsRawSqlThroughDbHandleInEntryPoint(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/modules/BadModule/index.php'],
            [
                [sprintf(BanRawDbHandleInEntryPointRule::ENTRY_POINT_MESSAGE, 'prepare'), 6],
                [sprintf(BanRawDbHandleInEntryPointRule::ENTRY_POINT_MESSAGE, 'query'), 7],
            ],
        );
    }

    public function testAllowsRoleBasedServicesInEntryPoint(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/modules/GoodModule/index.php'],
            [],
        );
    }

    public function testAllowsDbCallInNonEntryPointModuleFile(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/modules/BadModule/helper.php'],
            [],
        );
    }
}
