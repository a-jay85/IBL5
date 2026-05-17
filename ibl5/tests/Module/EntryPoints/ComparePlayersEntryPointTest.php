<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use Tests\WideUnit\Mocks\TestDataFactory;

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
        $player1 = TestDataFactory::createPlayer([
            'pid' => 1, 'name' => 'Player One', 'pos' => 'G', 'teamid' => 1,
            'color1' => '000000', 'color2' => 'FFFFFF',
        ]);
        $player2 = TestDataFactory::createPlayer([
            'pid' => 2, 'name' => 'Player Two', 'pos' => 'F', 'teamid' => 2,
            'color1' => 'FF0000', 'color2' => '000000',
        ]);
        // MockPreparedStatement interpolates bound params into SQL, so 'Player One' appears
        // literally in the WHERE clause — onQuery routes each lookup to a distinct row.
        $this->mockDb->onQuery('Player One', [$player1]);
        $this->mockDb->onQuery('Player Two', [$player2]);
        $this->mockDb->setMockData([$player1, $player2]); // for getAllPlayerNames()

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
