<?php

declare(strict_types=1);

namespace Tests\DepthChartEntry;

use DepthChartEntry\DepthChartEntryApiHandler;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests for DepthChartEntryApiHandler
 *
 * Validates display mode whitelist and split parameter support.
 */
class DepthChartEntryApiHandlerTest extends IntegrationTestCase
{
    public function testCanBeInstantiated(): void
    {
        $handler = new DepthChartEntryApiHandler($GLOBALS['mysqli_db']);

        $this->assertInstanceOf(DepthChartEntryApiHandler::class, $handler);
    }

    public function testValidDisplayModesIncludePlayoffs(): void
    {
        $reflection = new \ReflectionClass(DepthChartEntryApiHandler::class);
        $constant = $reflection->getReflectionConstant('VALID_DISPLAY_MODES');
        $this->assertNotFalse($constant);

        /** @var list<string> $modes */
        $modes = $constant->getValue();
        $this->assertContains('playoffs', $modes);
    }

    public function testValidDisplayModesIncludeSplit(): void
    {
        $reflection = new \ReflectionClass(DepthChartEntryApiHandler::class);
        $constant = $reflection->getReflectionConstant('VALID_DISPLAY_MODES');
        $this->assertNotFalse($constant);

        /** @var list<string> $modes */
        $modes = $constant->getValue();
        $this->assertContains('split', $modes);
    }

    public function testValidDisplayModesMatchTeamApiHandler(): void
    {
        $dceReflection = new \ReflectionClass(DepthChartEntryApiHandler::class);
        $dceConstant = $dceReflection->getReflectionConstant('VALID_DISPLAY_MODES');
        $this->assertNotFalse($dceConstant);

        $teamReflection = new \ReflectionClass(\Team\TeamApiHandler::class);
        $teamConstant = $teamReflection->getReflectionConstant('VALID_DISPLAY_MODES');
        $this->assertNotFalse($teamConstant);

        /** @var list<string> $dceModes */
        $dceModes = $dceConstant->getValue();
        /** @var list<string> $teamModes */
        $teamModes = $teamConstant->getValue();

        sort($dceModes);
        sort($teamModes);

        $this->assertSame($teamModes, $dceModes, 'DepthChartEntry and Team API handlers should support the same display modes');
    }

    public function testValidDisplayModesContainsAllExpectedModes(): void
    {
        $reflection = new \ReflectionClass(DepthChartEntryApiHandler::class);
        $constant = $reflection->getReflectionConstant('VALID_DISPLAY_MODES');
        $this->assertNotFalse($constant);

        /** @var list<string> $modes */
        $modes = $constant->getValue();

        $expected = ['ratings', 'total_s', 'avg_s', 'per36mins', 'chunk', 'playoffs', 'contracts', 'split'];
        sort($expected);
        sort($modes);

        $this->assertSame($expected, $modes);
    }
}
