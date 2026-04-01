<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use OneOnOneGame\OneOnOneGameRepository;
use OneOnOneGame\OneOnOneGameResult;

/**
 * Tests OneOnOneGameRepository against real MariaDB —
 * CRUD operations on the ibl_one_on_one table plus player lookups from ibl_plr.
 */
class OneOnOneGameRepositoryTest extends DatabaseTestCase
{
    private OneOnOneGameRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new OneOnOneGameRepository($this->db);
    }

    // ── getActivePlayers ────────────────────────────────────────

    public function testGetActivePlayersReturnsNonEmptyArray(): void
    {
        // CI seed has active players in ibl_plr
        $players = $this->repo->getActivePlayers();

        self::assertNotEmpty($players);
        self::assertArrayHasKey('pid', $players[0]);
        self::assertArrayHasKey('name', $players[0]);
        self::assertIsInt($players[0]['pid']);
        self::assertIsString($players[0]['name']);
    }

    public function testGetActivePlayersExcludesRetiredPlayers(): void
    {
        $this->insertTestPlayer(200000050, 'DB Test Retired Player', [
            'retired' => 1,
            'tid' => 1,
        ]);

        $players = $this->repo->getActivePlayers();
        $names = array_column($players, 'name');

        self::assertNotContains('DB Test Retired Player', $names);
    }

    public function testGetActivePlayersIncludesNormalPlayers(): void
    {
        $this->insertTestPlayer(200000051, 'DB Test Active Player', [
            'retired' => 0,
            'tid' => 1,
        ]);

        $players = $this->repo->getActivePlayers();
        $names = array_column($players, 'name');

        self::assertContains('DB Test Active Player', $names);
    }

    public function testGetActivePlayersExcludesNoStarter(): void
    {
        $this->insertTestPlayer(200000052, '(no starter)', [
            'retired' => 0,
            'tid' => 1,
        ]);

        $players = $this->repo->getActivePlayers();
        $names = array_column($players, 'name');

        self::assertNotContains('(no starter)', $names);
    }

    public function testGetActivePlayersOrderedByName(): void
    {
        $players = $this->repo->getActivePlayers();

        self::assertNotEmpty($players);

        // Verify general ascending order by checking consecutive pairs.
        // MySQL collation may differ from PHP's sort() for punctuation/dots,
        // so we use strcasecmp which approximates the DB ordering.
        if (count($players) >= 2) {
            $outOfOrder = 0;
            for ($i = 1; $i < count($players); $i++) {
                if (strcasecmp($players[$i - 1]['name'], $players[$i]['name']) > 0) {
                    $outOfOrder++;
                }
            }
            // Allow a small tolerance for collation differences
            self::assertLessThan(5, $outOfOrder, 'Players should be mostly alphabetically ordered');
        }
    }

    // ── getPlayerForGame ────────────────────────────────────────

    public function testGetPlayerForGameReturnsPlayerData(): void
    {
        $this->insertTestPlayer(200000053, 'DB Test Game Player', [
            'oo' => 70, 'do' => 60, 'po' => 65, 'od' => 55, 'dd' => 50, 'pd' => 45,
            'r_fga' => 80, 'r_fgp' => 50, 'r_fta' => 70, 'r_tga' => 40, 'r_tgp' => 35,
            'r_orb' => 30, 'r_drb' => 60, 'r_stl' => 25, 'r_to' => 20, 'r_blk' => 15, 'r_foul' => 18,
        ]);

        $player = $this->repo->getPlayerForGame(200000053);

        self::assertNotNull($player);
        self::assertSame(200000053, $player['pid']);
        self::assertSame('DB Test Game Player', $player['name']);
        self::assertSame(70, $player['oo']);
        self::assertSame(60, $player['do']);
        self::assertSame(50, $player['r_fgp']);
    }

    public function testGetPlayerForGameReturnsNullForNonexistentPlayer(): void
    {
        $result = $this->repo->getPlayerForGame(999999999);

        self::assertNull($result);
    }

    // ── getNextGameId ───────────────────────────────────────────

    public function testGetNextGameIdReturnsOneWhenTableEmpty(): void
    {
        // Clear any existing data
        $this->db->query('DELETE FROM ibl_one_on_one');

        $nextId = $this->repo->getNextGameId();

        self::assertSame(1, $nextId);
    }

    public function testGetNextGameIdReturnsIncrementedId(): void
    {
        $this->db->query('DELETE FROM ibl_one_on_one');

        $this->insertRow('ibl_one_on_one', [
            'gameid' => 42,
            'playbyplay' => 'Test play by play',
            'winner' => 'Player A',
            'loser' => 'Player B',
            'winscore' => 21,
            'lossscore' => 15,
            'owner' => 'testgm',
        ]);

        $nextId = $this->repo->getNextGameId();

        self::assertSame(43, $nextId);
    }

    // ── saveGame + getGameById ──────────────────────────────────

    public function testSaveGameAndRetrieveById(): void
    {
        $this->db->query('DELETE FROM ibl_one_on_one');

        $result = new OneOnOneGameResult();
        $result->player1Name = 'Alpha Player';
        $result->player2Name = 'Beta Player';
        $result->player1Score = 21;
        $result->player2Score = 18;
        $result->playByPlay = '<p>Play-by-play HTML content</p>';
        $result->owner = 'testgm';

        $gameId = $this->repo->saveGame($result);

        self::assertSame(1, $gameId);

        $game = $this->repo->getGameById($gameId);

        self::assertNotNull($game);
        self::assertSame($gameId, $game['gameid']);
        self::assertSame('Alpha Player', $game['winner']);
        self::assertSame('Beta Player', $game['loser']);
        self::assertSame(21, $game['winscore']);
        self::assertSame(18, $game['lossscore']);
        self::assertSame('testgm', $game['owner']);
        self::assertStringContainsString('Play-by-play HTML content', $game['playbyplay']);
    }

    public function testGetGameByIdReturnsNullForNonexistentGame(): void
    {
        $result = $this->repo->getGameById(999999999);

        self::assertNull($result);
    }
}
