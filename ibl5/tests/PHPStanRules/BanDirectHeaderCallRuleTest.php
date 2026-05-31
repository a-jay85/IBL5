<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanDirectHeaderCallRule;

/**
 * @extends RuleTestCase<BanDirectHeaderCallRule>
 */
final class BanDirectHeaderCallRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanDirectHeaderCallRule();
    }

    public function testFlagsHeaderInService(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/HeaderInService.php'],
            [
                [
                    'header() is banned outside Bootstrap classes, Responders, and HTMX ApiHandlers. '
                    . 'Route response headers through a Responder (composed by the Controller/ApiHandler).',
                    5,
                ],
            ],
        );
    }

    public function testAllowsHeaderInResponder(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/Allowed/FooResponder.php'],
            [],
        );
    }

    public function testAllowsHeaderInApiHandler(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/Allowed/FooApiHandler.php'],
            [],
        );
    }
}
