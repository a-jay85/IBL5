<?php

declare(strict_types=1);

namespace Tests\LeagueStarters;

use LeagueStarters\LeagueStartersApiHandler;
use Tests\Integration\IntegrationTestCase;

/**
 * @covers \LeagueStarters\LeagueStartersApiHandler
 */
class LeagueStartersApiHandlerTest extends IntegrationTestCase
{
    public function testCanBeInstantiated(): void
    {
        $handler = new LeagueStartersApiHandler($GLOBALS['mysqli_db']);

        $this->assertInstanceOf(LeagueStartersApiHandler::class, $handler);
    }

    public function testValidDisplayModesContainsAllExpectedModes(): void
    {
        $reflection = new \ReflectionClass(LeagueStartersApiHandler::class);
        $constant = $reflection->getReflectionConstant('VALID_DISPLAY_MODES');
        $this->assertNotFalse($constant);

        /** @var list<string> $modes */
        $modes = $constant->getValue();

        $expected = ['ratings', 'total_s', 'avg_s', 'per36mins'];
        sort($expected);
        sort($modes);

        $this->assertSame($expected, $modes);
    }
}
