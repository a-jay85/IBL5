<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use Tests\WideUnit\Mocks\TestDataFactory;

class LeagueStartersEntryPointTest extends ModuleEntryPointTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->onQuery('ibl_plr', [TestDataFactory::createPlayer([
            'pid' => 4040404,
            'name' => 'Placeholder',
            'teamname' => 'Test Team',
            'color1' => 'FFFFFF',
            'color2' => '000000',
        ])]);
        $this->mockDb->setMockData([]);
    }

    public function testDefaultRendersLeagueStartersPage(): void
    {
        $output = $this->runModule('LeagueStarters');

        $this->assertNotEmpty($output);
    }

    public function testOpApiReturnsHtmlFragment(): void
    {
        $output = $this->runModule('LeagueStarters', ['op' => 'api', 'display' => 'ratings']);

        $this->assertNotEmpty($output);
    }

    public function testDisplayParamValidatesAgainstWhitelist(): void
    {
        $output = $this->runModule('LeagueStarters', ['display' => 'total_s']);

        $this->assertNotEmpty($output);
    }

    public function testInvalidDisplayParamDefaultsToRatings(): void
    {
        $output = $this->runModule('LeagueStarters', ['display' => 'bogus']);

        $this->assertNotEmpty($output);
    }
}
