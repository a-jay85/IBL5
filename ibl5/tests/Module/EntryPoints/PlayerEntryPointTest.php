<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use Tests\WideUnit\Mocks\TestDataFactory;

/**
 * Player/index.php defines global functions (showpage, negotiate, rookieoption,
 * processrookieoption) that cannot be redeclared, so each test runs in a
 * separate process.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class PlayerEntryPointTest extends ModuleEntryPointTestCase
{
    public function testMissingPaShowsNothing(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('Player');

        $this->assertSame('', $output);
    }

    public function testInvalidPaShowsNothing(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('Player', ['pa' => 'bogus']);

        $this->assertSame('', $output);
    }

    private function seedShowpageMocks(array $playerOverrides = []): void
    {
        $player = TestDataFactory::createPlayer(array_merge(
            ['pid' => 1, 'name' => 'Test Player', 'teamname' => 'Test Team', 'color1' => 'FF0000', 'color2' => '000000'],
            $playerOverrides,
        ));

        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([$player]);

        // The PlayerRepository JOIN query contains both ibl_plr and ibl_team_info,
        // so we must route it via onQuery before the team-info special handler fires.
        $this->mockDb->onQuery('FROM ibl_plr', [$player]);
        $this->mockDb->onQuery('ibl_hist', []);
        $this->mockDb->onQuery('ibl_box_scores', []);
        $this->mockDb->onQuery('ibl_sim_dates', []);
        $this->mockDb->onQuery('ibl_playoff_career_totals', []);
        $this->mockDb->onQuery('ibl_settings', [['value' => 'Regular Season']]);
        $this->mockDb->onQuery('ibl_awards', []);
        $this->mockDb->onQuery('ibl_draft', []);
        $this->mockDb->onQuery('COUNT', [['total' => 0]]);
    }

    public function testShowpageDispatchesAndQueriesPlayer(): void
    {
        $this->seedShowpageMocks();

        $output = $this->runModule(
            'Player',
            ['pa' => 'showpage', 'pid' => '1'],
        );

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_plr');
    }

    public function testShowpageWithNonNumericPidCastsToZero(): void
    {
        $this->seedShowpageMocks(['pid' => 0, 'name' => 'Unknown']);

        $output = $this->runModule(
            'Player',
            ['pa' => 'showpage', 'pid' => 'garbage'],
        );

        $this->assertQueryExecuted('ibl_plr');
    }

    public function testNegotiateRendersNegotiation(): void
    {
        $player = TestDataFactory::createPlayer(['pid' => 1]);
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([$player]);
        // PlayerRepository::loadByID JOINs ibl_team_info; route it before the
        // team-info special handler intercepts the response.
        $this->mockDb->onQuery('FROM ibl_plr', [$player]);
        $this->mockDb->onQuery('ibl_settings', [['value' => 'Regular Season']]);

        $output = $this->runModule('Player', ['pa' => 'negotiate', 'pid' => '1']);

        $this->assertNotEmpty($output);
    }

    public function testNegotiateWithAuthRendersNegotiation(): void
    {
        $this->authenticateAs('testuser');
        $player = TestDataFactory::createPlayer(['pid' => 1]);
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([$player]);
        $this->mockDb->onQuery('FROM ibl_plr', [$player]);
        $this->mockDb->onQuery('ibl_settings', [['value' => 'Regular Season']]);

        $output = $this->runModule('Player', ['pa' => 'negotiate', 'pid' => '1']);

        $this->assertNotEmpty($output);
    }
}
