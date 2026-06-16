<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;

/**
 * DepthChartEntry/index.php defines global functions (userinfo, main, submit,
 * tabApi, nextSimApi, api) that cannot be redeclared, and is_user() has a
 * static cache. Each test runs in a separate process.
 *
 * Skipped:
 * - op=submit (POST handler with redirect) — covered by E2E flows.
 * - op=api with JSON body via php://input — runModule() doesn't support
 *   raw-body injection. GET-style api() is tested instead.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class DepthChartEntryEntryPointTest extends ModuleEntryPointTestCase
{
    public function testDefaultOpRendersDepthChartForGm(): void
    {
        $this->authenticateAs('testgm');
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('DepthChartEntry', ['op' => ''], [], [
            'user' => $GLOBALS['user'],
        ]);

        $this->assertNotEmpty($output);
    }

    public function testOpTabApiReturnsHtml(): void
    {
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('DepthChartEntry', ['teamid' => '1', 'display' => 'ratings', 'op' => 'tab-api'], [], []);

        $this->assertNotEmpty($output);
    }

    public function testOpNextsimApiReturnsHtml(): void
    {
        $this->authenticateAs('testgm');
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('DepthChartEntry', ['teamid' => '1', 'op' => 'nextsim-api'], [], [
            'user' => $GLOBALS['user'],
        ]);

        $this->assertNotEmpty($output);
    }

    public function testOpApiWithoutAuthReturnsUnauthorizedJson(): void
    {
        $output = $this->runModule('DepthChartEntry', ['op' => 'api'], [], [
            'user' => '',
        ]);

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertSame('Unauthorized', $decoded['error']);
    }

    public function testUnknownOpFallsToMain(): void
    {
        $this->authenticateAs('testgm');
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('DepthChartEntry', ['op' => 'bogus'], [], [
            'user' => $GLOBALS['user'],
        ]);

        $this->assertNotEmpty($output);
    }
}
