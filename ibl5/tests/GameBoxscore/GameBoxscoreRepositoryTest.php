<?php

declare(strict_types=1);

namespace Tests\GameBoxscore;

use Tests\WideUnit\WideUnitTestCase;

/**
 * @covers \GameBoxscore\GameBoxscoreRepository
 */
class GameBoxscoreRepositoryTest extends WideUnitTestCase
{
    private function repo(): \GameBoxscore\GameBoxscoreRepository
    {
        $db = $this->mockDb;
        self::assertNotNull($db);
        return new \GameBoxscore\GameBoxscoreRepository($db);
    }

    public function testGetGameInfoReturnsRowWhenGameExists(): void
    {
        $this->mockDb->onQuery('ibl_box_scores_teams', [[
            'game_date' => '2026-02-20',
            'awayTeamId' => 1,
            'homeTeamId' => 2,
            'game_of_that_day' => 1,
            'awayScore' => 105,
            'homeScore' => 98,
            'awayTeamName' => 'Metros',
            'awayTeamCity' => 'New York',
            'awayColor1' => '003DA5',
            'awayColor2' => 'FF5733',
            'homeTeamName' => 'Stars',
            'homeTeamCity' => 'Los Angeles',
            'homeColor1' => '552583',
            'homeColor2' => 'FDB927',
        ]]);

        $result = $this->repo()->getGameInfo('2026-02-20', 1);

        self::assertNotNull($result);
        self::assertSame(1, $result['awayTeamId']);
        self::assertSame(2, $result['homeTeamId']);
        self::assertSame(105, $result['awayScore']);
        self::assertSame(98, $result['homeScore']);
    }

    public function testGetGameInfoReturnsNullWhenNoGame(): void
    {
        $this->mockDb->onQuery('ibl_box_scores_teams', []);

        $result = $this->repo()->getGameInfo('1900-01-01', 9);

        self::assertNull($result);
    }

    public function testGetPlayerRowsReturnsRows(): void
    {
        $this->mockDb->onQuery('isAwayPlayer', [
            [
                'game_date' => '2026-02-20',
                'name' => 'Away Player',
                'pos' => 'PG',
                'pid' => 1,
                'teamid' => 1,
                'isAwayPlayer' => 1,
                'min' => 35,
                'fgm' => 7,
                'fga' => 16,
                'ftm' => 4,
                'fta' => 5,
                'tpm' => 2,
                'tpa' => 6,
                'orb' => 2,
                'drb' => 6,
                'ast' => 5,
                'stl' => 2,
                'tov' => 2,
                'blk' => 1,
                'pf' => 3,
                'reb' => 8,
                'pts' => 20,
            ],
            [
                'game_date' => '2026-02-20',
                'name' => 'Home Player',
                'pos' => 'SG',
                'pid' => 2,
                'teamid' => 2,
                'isAwayPlayer' => 0,
                'min' => 30,
                'fgm' => 5,
                'fga' => 12,
                'ftm' => 2,
                'fta' => 3,
                'tpm' => 1,
                'tpa' => 4,
                'orb' => 1,
                'drb' => 4,
                'ast' => 3,
                'stl' => 1,
                'tov' => 1,
                'blk' => 0,
                'pf' => 2,
                'reb' => 5,
                'pts' => 13,
            ],
        ]);

        $result = $this->repo()->getPlayerRows('2026-02-20', 1, 1, 2);

        self::assertCount(2, $result);
    }

    public function testGetPlayerRowsReturnsEmptyListWhenNoRows(): void
    {
        $this->mockDb->onQuery('isAwayPlayer', []);

        $result = $this->repo()->getPlayerRows('2026-02-20', 1, 1, 2);

        self::assertSame([], $result);
    }
}
