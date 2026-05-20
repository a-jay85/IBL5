<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use Tests\WideUnit\Mocks\TestDataFactory;

class TeamEntryPointTest extends ModuleEntryPointTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDb->onQuery('ibl_settings', [['value' => 'Regular Season']]);
        $this->mockDb->onQuery('ibl_schedule', []);
        $this->mockDb->onQuery('ibl_sim_dates', []);

        $lcStub = $this->createStub(\League\LeagueContext::class);
        $lcStub->method('getConfig')->willReturn(['images_path' => 'images/']);
        $GLOBALS['leagueContext'] = $lcStub;
    }

    private function seedTeamPageMocks(): void
    {
        $team = self::fullTeamData([
            'teamid' => 1,
            'team_name' => 'Miami Heat',
            'league_record' => '10-5',
        ]);
        $this->mockDb->setMockTeamData([$team]);
        $this->mockDb->setMockData([]);
        // Power/standings query returns null so currentSeasonData is skipped
        $this->mockDb->onQuery('ibl_power', []);
        $this->mockDb->onQuery('ibl_plr', []);
        $this->mockDb->onQuery('ibl_box_scores', []);
        $this->mockDb->onQuery('ibl_hist', []);
        $this->mockDb->onQuery('ibl_awards', []);
        $this->mockDb->onQuery('ibl_draft', []);
        $this->mockDb->onQuery('COUNT', [['total' => 0]]);
        $this->mockDb->onQuery('split_stats', []);
        $this->mockDb->onQuery('ibl_playoff_career_totals', []);
    }

    public function testDefaultOpRendersMenu(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('Team', ['op' => '']);

        $this->assertNotEmpty($output);
    }

    public function testUnknownOpFallsToMenu(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('Team', ['op' => 'bogus']);

        $this->assertNotEmpty($output);
    }

    public function testOpTeamWithValidTeamidRendersTeamPage(): void
    {
        $this->seedTeamPageMocks();

        $output = $this->runModule('Team', ['op' => 'team', 'teamid' => '1']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_team_info');
    }

    public function testOpTeamWithTeamid0ShowsErrorMessage(): void
    {
        $this->mockDb->setMockTeamData([]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('Team', ['op' => 'team', 'teamid' => '0']);

        $this->assertStringContainsString('Team not found', $output);
    }

    public function testOpApiReturnsHtmlOutput(): void
    {
        $team = self::fullTeamData(['teamid' => 1, 'team_name' => 'Miami Heat']);
        $this->mockDb->setMockTeamData([$team]);
        $this->mockDb->setMockData([]);
        $this->mockDb->onQuery('ibl_plr', []);
        $this->mockDb->onQuery('ibl_box_scores', []);
        $this->mockDb->onQuery('ibl_hist', []);
        $this->mockDb->onQuery('split_stats', []);
        $this->mockDb->onQuery('ibl_playoff_career_totals', []);

        $output = $this->runModule(
            'Team',
            ['op' => 'api', 'teamid' => '1', 'display' => 'ratings'],
        );

        $this->assertNotEmpty($output);
    }
}
