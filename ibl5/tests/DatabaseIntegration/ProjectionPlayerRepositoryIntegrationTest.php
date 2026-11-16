<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use TradeSuggestions\Projection\ProjectionPlayerRepository;

/**
 * Runs the v3 loader's SELECT against the real schema, so a misnamed column
 * or a dropped `retired = 0` filter fails here.
 */
#[Group('database')]
final class ProjectionPlayerRepositoryIntegrationTest extends DatabaseTestCase
{
    private const int LIVE_PID = 200000301;
    private const int RETIRED_PID = 200000302;
    private const int TID = 1;

    public function testLoadsLiveRowsAndSkipsRetired(): void
    {
        $this->insertTestPlayer(self::LIVE_PID, 'Projection Live', ['teamid' => self::TID, 'r_fga' => 77]);
        $this->insertTestPlayer(self::RETIRED_PID, 'Projection Retired', ['teamid' => self::TID, 'retired' => 1]);

        $players = (new ProjectionPlayerRepository($this->db))->getLeaguePlayers(false, 8888);

        self::assertArrayHasKey(self::TID, $players);
        $byPid = [];
        foreach ($players[self::TID] as $player) {
            $byPid[$player->pid] = $player;
        }

        self::assertArrayHasKey(self::LIVE_PID, $byPid);
        self::assertArrayNotHasKey(self::RETIRED_PID, $byPid);
        self::assertSame(77.0, $byPid[self::LIVE_PID]->ratings->get('r_fga'));
    }
}
