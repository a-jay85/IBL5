<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

class GameBoxscoreEntryPointTest extends ModuleEntryPointTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDb->onQuery('ibl_settings', [['value' => 'Regular Season']]);
        $this->mockDb->onQuery('ibl_sim_dates', []);
        $this->mockDb->onQuery('ibl_schedule', []);
        $this->mockDb->setMockData([]);
    }

    public function testRendersBoxscoreForValidGame(): void
    {
        $this->mockDb->onQuery('ibl_box_scores_teams', [[
            'awayTeamId' => 1,
            'homeTeamId' => 2,
            'awayScore' => 105,
            'homeScore' => 98,
            'awayTeamName' => 'Metros',
            'homeTeamName' => 'Stars',
            'awayTeamCity' => 'Metro',
            'homeTeamCity' => 'Star',
            'awayColor1' => '112233',
            'homeColor1' => '445566',
            'awayColor2' => '000000',
            'homeColor2' => 'FFFFFF',
            'game_date' => '2026-02-20',
            'game_of_that_day' => 1,
        ]]);
        $this->mockDb->onQuery('isAwayPlayer', [[
            'isAwayPlayer' => 0,
            'pid' => 10,
            'pos' => 'PG',
            'name' => 'Home Guy',
            'pts' => 25,
            'min' => 30,
            'fgm' => 9,
            'fga' => 15,
            'ftm' => 5,
            'fta' => 6,
            'tpm' => 2,
            'tpa' => 4,
            'orb' => 1,
            'reb' => 5,
            'ast' => 7,
            'stl' => 2,
            'blk' => 0,
            'tov' => 3,
            'pf' => 2,
        ]]);

        $output = $this->runModule('GameBoxscore', get: ['date' => '2026-02-20', 'game' => '1']);

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('Game 1', $output);
        $this->assertStringContainsString('game-boxscore__table', $output);
        $this->assertQueryExecuted('ibl_box_scores_teams');
    }

    public function testInvalidDateRendersNotFoundAndSkipsSql(): void
    {
        $output = $this->runModule('GameBoxscore', get: ['date' => 'not-a-date', 'game' => '1']);

        $this->assertStringContainsString('Game Not Found', $output);
        $this->assertQueryNotExecuted('ibl_box_scores_teams');
    }

    public function testMissingParamsRendersNotFound(): void
    {
        $output = $this->runModule('GameBoxscore');

        $this->assertStringContainsString('Game Not Found', $output);
        $this->assertQueryNotExecuted('ibl_box_scores_teams');
    }

    public function testMaliciousParamsAreRejectedBeforeSql(): void
    {
        $output = $this->runModule('GameBoxscore', get: [
            'date' => "2026-02-20' OR '1'='1",
            'game' => '1 OR 1=1',
        ]);

        $this->assertStringContainsString('Game Not Found', $output);
        $this->assertQueryNotExecuted('ibl_box_scores_teams');
    }

    public function testValidGameNoPlayersRendersEmptyState(): void
    {
        $this->mockDb->onQuery('ibl_box_scores_teams', [[
            'awayTeamId' => 1,
            'homeTeamId' => 2,
            'awayScore' => 105,
            'homeScore' => 98,
            'awayTeamName' => 'Metros',
            'homeTeamName' => 'Stars',
            'awayTeamCity' => 'Metro',
            'homeTeamCity' => 'Star',
            'awayColor1' => '112233',
            'homeColor1' => '445566',
            'awayColor2' => '000000',
            'homeColor2' => 'FFFFFF',
            'game_date' => '2026-02-20',
            'game_of_that_day' => 1,
        ]]);
        $this->mockDb->onQuery('isAwayPlayer', []);

        $output = $this->runModule('GameBoxscore', get: ['date' => '2026-02-20', 'game' => '1']);

        $this->assertStringContainsString('No player stats recorded', $output);
    }
}
