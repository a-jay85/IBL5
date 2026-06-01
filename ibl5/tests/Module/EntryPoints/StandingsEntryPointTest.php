<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

class StandingsEntryPointTest extends ModuleEntryPointTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDb->onQuery('ibl_settings', [['value' => 'Regular Season']]);
        $this->mockDb->onQuery('ibl_sim_dates', []);
        $this->mockDb->onQuery('ibl_schedule', []);
        $this->mockDb->setMockData([]);
    }

    public function testRendersStandingsWithEndingYear(): void
    {
        $output = $this->runModule('Standings');

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_standings');
    }

    public function testRendersZeroTeamsGracefully(): void
    {
        $output = $this->runModule('Standings');

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_power');
    }

    public function testDispatchesToOlympicsViewWhenOlympics(): void
    {
        \League\OlympicsTeamFilter::resetCache();

        $lcStub = self::createStub(\League\LeagueContext::class);
        $lcStub->method('isOlympics')->willReturn(true);
        $lcStub->method('getCurrentLeague')->willReturn('olympics');
        $lcStub->method('getConfig')->willReturn([
            'title' => 'IBL Olympics',
            'short_name' => 'Olympics',
            'primary_color' => '#c53030',
            'logo_path' => 'images/olympics/logo.png',
            'images_path' => 'images/olympics/',
        ]);
        $lcStub->method('getTableName')->willReturnCallback(
            static fn (string $table): string => str_replace('ibl_', 'ibl_olympics_', $table),
        );
        $GLOBALS['leagueContext'] = $lcStub;

        $this->mockDb->onQuery('is_real_team', [['teamid' => 1]]);
        $this->mockDb->onQuery('FROM.*ibl_olympics_standings', [[
            'teamid' => 1,
            'team_name' => 'Eagles',
            'league_record' => '3-1',
            'pct' => '0.750',
            'conf_gb' => '0.0',
            'div_gb' => '0.0',
            'conf_record' => '0-0',
            'div_record' => '0-0',
            'home_record' => '2-0',
            'away_record' => '1-1',
            'games_unplayed' => 4,
            'conf_magic_number' => 0,
            'div_magic_number' => 0,
            'clinched_conference' => 0,
            'clinched_division' => 0,
            'clinched_playoffs' => 0,
            'clinched_league' => 0,
            'wins' => 3,
            'homeGames' => 2,
            'awayGames' => 2,
            'conference' => 'Group A',
            'division' => '',
            'color1' => '002868',
            'color2' => 'BF0A30',
        ]]);

        $output = $this->runModule('Standings');

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('Olympics Standings', $output);

        \League\OlympicsTeamFilter::resetCache();
    }
}
