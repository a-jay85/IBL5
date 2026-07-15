<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use GameBoxscore\GameBoxscoreRepository;
use PHPUnit\Framework\Attributes\Group;

#[Group('database')]
class GameBoxscoreRepositoryTest extends DatabaseTestCase
{
    private GameBoxscoreRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new GameBoxscoreRepository($this->db);
    }

    public function testGetGameInfoComputesScoresAndTeamColors(): void
    {
        $this->insertTeamBoxscoreRow('2099-03-01', 'Metros', 1, 1, 2);

        $result = $this->repo->getGameInfo('2099-03-01', 1);

        self::assertNotNull($result);
        // Away quarters: 20+22+18+25 = 85
        self::assertSame(85, $result['awayScore']);
        // Home quarters: 28+24+22+30 = 104
        self::assertSame(104, $result['homeScore']);
        self::assertSame(1, $result['awayTeamId']);
        self::assertSame(2, $result['homeTeamId']);
        self::assertArrayHasKey('awayColor1', $result);
        self::assertArrayHasKey('homeColor1', $result);
    }

    public function testGetGameInfoReturnsNullForUnknownGame(): void
    {
        $this->insertTeamBoxscoreRow('2099-03-01', 'Metros', 1, 1, 2);

        self::assertNull($this->repo->getGameInfo('2099-03-01', 7));
        self::assertNull($this->repo->getGameInfo('1901-01-01', 1));
    }

    public function testGetPlayerRowsSplitsTeamsAndFlagsAwaySide(): void
    {
        $this->insertTestPlayer(200090601, 'GB Away One');
        $this->insertTestPlayer(200090602, 'GB Home One', ['ordinal' => 2]);

        $this->insertPlayerBoxscoreRow('2099-03-01', 200090601, 'GB Away One', 'PG', 1, 2, 1);
        $this->insertPlayerBoxscoreRow('2099-03-01', 200090602, 'GB Home One', 'SG', 1, 2, 2);

        $result = $this->repo->getPlayerRows('2099-03-01', 1, 1, 2);

        self::assertCount(2, $result);

        $byTeam = [];
        foreach ($result as $row) {
            $byTeam[(int) $row['teamid']] = $row;
        }

        self::assertSame(1, $byTeam[1]['isAwayPlayer']);
        self::assertSame(0, $byTeam[2]['isAwayPlayer']);

        // Defaults from insertPlayerBoxscoreRow: pts 5*2+4+2*3=20, reb 2+6=8, fgm 5+2=7
        self::assertSame(20, $byTeam[1]['pts']);
        self::assertSame(8, $byTeam[1]['reb']);
        self::assertSame(7, $byTeam[1]['fgm']);
        self::assertSame(20, $byTeam[2]['pts']);
        self::assertSame(8, $byTeam[2]['reb']);
        self::assertSame(7, $byTeam[2]['fgm']);
    }

    public function testGetPlayerRowsExcludesOtherTeamsAndEmptyGames(): void
    {
        $this->insertTestPlayer(200090603, 'GB Away Two');
        $this->insertTestPlayer(200090604, 'GB Home Two', ['ordinal' => 2]);
        $this->insertTestPlayer(200090605, 'GB Other One', ['ordinal' => 3]);

        $this->insertPlayerBoxscoreRow('2099-03-01', 200090603, 'GB Away Two', 'PG', 1, 2, 1);
        $this->insertPlayerBoxscoreRow('2099-03-01', 200090604, 'GB Home Two', 'SG', 1, 2, 2);
        $this->insertPlayerBoxscoreRow('2099-03-01', 200090605, 'GB Other One', 'SF', 1, 2, 3);

        $result = $this->repo->getPlayerRows('2099-03-01', 1, 1, 2);

        $pids = array_map(static fn (array $row): int => (int) $row['pid'], $result);
        self::assertNotContains(200090605, $pids);
        self::assertContains(200090603, $pids);
        self::assertContains(200090604, $pids);

        self::assertSame([], $this->repo->getPlayerRows('2099-03-01', 9, 1, 2));
    }
}
