<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanEchoInNonViewClassesRule;

/**
 * @extends RuleTestCase<BanEchoInNonViewClassesRule>
 */
final class BanEchoInNonViewClassesRuleTest extends RuleTestCase
{
    private const ERROR_MESSAGE = 'echo is banned outside View classes, Responders, and Bootstrap/Updater emitters. '
        . 'Move HTML/text output into a *View.php (composed by the Controller) '
        . 'or a Responder (composed by the ApiHandler).';

    protected function getRule(): Rule
    {
        return new BanEchoInNonViewClassesRule();
    }

    public function testFlagsEchoInController(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/EchoInController.php'],
            [
                [self::ERROR_MESSAGE, 5],
            ],
        );
    }

    public function testAllowsEchoInView(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/FooView.php'],
            [],
        );
    }

    public function testAllowsEchoInUpdater(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/FooUpdater.php'],
            [],
        );
    }

    public function testAllowsEchoInBulkImportRunner(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/EchoInBulkImport/BulkImportRunner.php'],
            [],
        );
    }

    public function testAllowsEchoOutsideClassesDirectory(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/OutsideClassesEcho.php'],
            [],
        );
    }

    public function testAllowsEchoInBootstrap(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/FooBootstrap.php'],
            [],
        );
    }
}
