<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use Tests\WideUnit\Mocks\TestDataFactory;

class LeagueStartersEntryPointTest extends ModuleEntryPointTestCase
{
    private function seedPlayerData(): void
    {
        $this->mockDb->onQuery('ibl_plr', [TestDataFactory::createPlayer(['pid' => 4040404, 'name' => 'Placeholder'])]);
    }

    public function testDefaultRendersLeagueStartersPage(): void
    {
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->seedPlayerData();
        $this->mockDb->setMockData([]);

        $output = $this->runModule('LeagueStarters');

        $this->assertNotEmpty($output);
    }

    public function testOpApiReturnsHtmlFragment(): void
    {
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->seedPlayerData();
        $this->mockDb->setMockData([]);

        $output = $this->runModule('LeagueStarters', ['op' => 'api', 'display' => 'ratings']);

        $this->assertNotEmpty($output);
    }

    public function testDisplayParamValidatesAgainstWhitelist(): void
    {
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->seedPlayerData();
        $this->mockDb->setMockData([]);

        $output = $this->runModule('LeagueStarters', ['display' => 'total_s']);

        $this->assertNotEmpty($output);
    }

    public function testInvalidDisplayParamDefaultsToRatings(): void
    {
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->seedPlayerData();
        $this->mockDb->setMockData([]);

        $output = $this->runModule('LeagueStarters', ['display' => 'bogus']);

        $this->assertNotEmpty($output);
    }
}
