<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;

/**
 * ComparePlayers/index.php defines global functions (userinfo, main) that
 * cannot be redeclared, so each test runs in a separate process.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class ComparePlayersEntryPointTest extends ModuleEntryPointTestCase
{
    public function testNoPostShowsSearchForm(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('ComparePlayers');

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_plr');
    }

    public function testPostWithBothPlayersRunsComparison(): void
    {
        $this->mockDb->setMockData([
            ['pid' => 1, 'name' => 'Player One', 'pos' => 'G', 'teamid' => 1],
        ]);
        $output = $this->runModule(
            'ComparePlayers',
            [],
            ['Player1' => 'Player One', 'Player2' => 'Player Two'],
        );

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_plr');
    }

    public function testPostWithEmptyPlayersShowsNotFoundMessage(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule(
            'ComparePlayers',
            [],
            ['Player1' => '', 'Player2' => ''],
        );

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('not found', $output);
    }

    public function testPostWithOverlongPlayerNameShowsError(): void
    {
        $this->mockDb->setMockData([]);
        $longName = str_repeat('A', 101);
        $output = $this->runModule(
            'ComparePlayers',
            [],
            ['Player1' => $longName, 'Player2' => 'Test'],
        );

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('100 characters', $output);
    }
}
