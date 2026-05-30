<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

use EngineBundle\Dto\Player;
use EngineBundle\EngineBundleRepository;

/**
 * Tests EngineBundleRepository against real MariaDB — player, team, and
 * unplayed-game reads for the engine input bundle. Self-inserts fixtures
 * (transaction-rolled-back per test) so assertions don't depend on seed data.
 */
#[Group('database')]
class EngineBundleRepositoryTest extends DatabaseTestCase
{
    private EngineBundleRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new EngineBundleRepository($this->db);
    }

    /** @return Player|null */
    private function findPlayer(int $pid): ?Player
    {
        foreach ($this->repo->getPlayers() as $player) {
            if ($player->fields['pid'] === $pid) {
                return $player;
            }
        }
        return null;
    }

    // ── getPlayers ───────────────────────────────────────────────────

    public function testGetPlayersReturnsAll45ContractFieldsWithIntTeamid(): void
    {
        $this->insertTestPlayer(200000100, 'Bundle Test Player', [
            'teamid' => 4,
            'ordinal' => 50,
            'oo' => 7,
            'r_drive_off' => 6,
            'r_3ga' => 40,
            'dc_minutes' => 34,
            'dc_can_play_in_game' => 1,
            'stamina' => 88,
        ]);

        $player = $this->findPlayer(200000100);
        self::assertNotNull($player, 'inserted player should be returned by getPlayers()');

        // Every contract field present (structural invariant), no extras.
        self::assertSame(Player::FIELDS, array_keys($player->fields));

        // teamid is a native int (column is `teamid`, not `tid`).
        self::assertIsInt($player->fields['teamid']);
        self::assertSame(4, $player->fields['teamid']);
        self::assertSame('Bundle Test Player', $player->fields['name']);
        self::assertSame(7, $player->fields['oo']);
        self::assertSame(6, $player->fields['r_drive_off']);
        self::assertSame(34, $player->fields['dc_minutes']);
    }

    public function testGetPlayersExcludesHighOrdinals(): void
    {
        $this->insertTestPlayer(200000101, 'High Ordinal', ['ordinal' => 1500]);
        self::assertNull($this->findPlayer(200000101), 'ordinal > 1440 must be excluded');
    }

    public function testGetPlayersNeverIncludesPidZero(): void
    {
        foreach ($this->repo->getPlayers() as $player) {
            self::assertNotSame(0, $player->fields['pid']);
        }
    }

    // ── getUnplayedGames ─────────────────────────────────────────────

    public function testGetUnplayedGamesReturnsOnlyUnplayedForYear(): void
    {
        // Two unplayed (scores 0/0) + one played (scores > 0) in an isolated year.
        $this->insertScheduleRow(2099, '2099-01-10', 2, 0, 1, 0);
        $this->insertScheduleRow(2099, '2099-02-20', 4, 0, 3, 0);
        $this->insertScheduleRow(2099, '2099-03-30', 6, 110, 5, 99); // played → excluded

        $games = $this->repo->getUnplayedGames(2099);

        self::assertCount(2, $games);
        // Ordered by game_date; verify the home/visitor/date mapping.
        self::assertSame('2099-01-10', $games[0]->date);
        self::assertSame(1, $games[0]->homeTeamId);
        self::assertSame(2, $games[0]->visitorTeamId);
        self::assertSame(3, $games[1]->homeTeamId);
    }

    public function testGetUnplayedGamesEmptyWhenNoneUnplayed(): void
    {
        $this->insertScheduleRow(2098, '2098-01-10', 2, 101, 1, 95); // played only
        self::assertSame([], $this->repo->getUnplayedGames(2098));
    }

    public function testGetUnplayedGamesAppliesDateRange(): void
    {
        $this->insertScheduleRow(2097, '2097-01-10', 2, 0, 1, 0);
        $this->insertScheduleRow(2097, '2097-12-10', 4, 0, 3, 0);

        // Range covering only the January game.
        $games = $this->repo->getUnplayedGames(2097, '2097-01-01', '2097-06-30');
        self::assertCount(1, $games);
        self::assertSame('2097-01-10', $games[0]->date);
    }

    /**
     * Security: the date bound is a bound parameter, not interpolated SQL. An
     * injection-style endDate must NOT leak rows (an interpolated `OR '1'='1'`
     * would return both inserted games; a bound literal returns none).
     */
    public function testGetUnplayedGamesDateParamIsBoundNotInterpolated(): void
    {
        $this->insertScheduleRow(2096, '2096-01-10', 2, 0, 1, 0);
        $this->insertScheduleRow(2096, '2096-12-10', 4, 0, 3, 0);

        $games = $this->repo->getUnplayedGames(2096, null, "2096-06-01' OR '1'='1");

        // If the param were interpolated, the always-true OR would return both
        // rows. Binding treats the value as a (non-)date literal → no leak.
        self::assertLessThan(2, count($games));
    }

    // ── getTeams ─────────────────────────────────────────────────────

    public function testGetTeamsConcatenatesCityAndName(): void
    {
        $teams = $this->repo->getTeams();
        self::assertNotEmpty($teams, 'seed provides real teams 1..28');

        // Cross-check the CONCAT against a direct column read (seed-independent).
        $first = $teams[0];
        $stmt = $this->db->prepare('SELECT team_city, team_name FROM `ibl_team_info` WHERE teamid = ?');
        self::assertNotFalse($stmt);
        $teamid = $first->teamid; // bind_param binds by reference; readonly props can't be bound directly
        $stmt->bind_param('i', $teamid);
        $stmt->execute();
        /** @var array{team_city: string, team_name: string}|null $row */
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        self::assertNotNull($row);

        self::assertSame($row['team_city'] . ' ' . $row['team_name'], $first->name);

        // Real-team range only (Free Agents teamid 0 excluded).
        foreach ($teams as $team) {
            self::assertGreaterThanOrEqual(1, $team->teamid);
            self::assertLessThanOrEqual(28, $team->teamid);
        }
    }
}
