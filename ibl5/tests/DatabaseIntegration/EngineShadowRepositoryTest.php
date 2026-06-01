<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use EngineShadow\EngineShadowRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Security: proves the shadow inserts are parameterized. A quote-bearing `pos`
 * value round-trips intact rather than corrupting the statement — a string-built
 * query would either error or store something different.
 */
#[Group('database')]
final class EngineShadowRepositoryTest extends DatabaseTestCase
{
    #[Test]
    public function quoteBearingPosRoundTripsProvingParameterization(): void
    {
        $repo = new EngineShadowRepository($this->db);

        $injection = "a'\"b"; // 4 chars: fits VARCHAR(5); both quote types + would break a built query
        $repo->insertShadowPlayerBox(
            '2026-03-10', 3, 1, 1,
            42, 3, $injection,
            30, 5, 10, 4, 5, 2, 6, 2, 6, 5, 2, 2, 1, 3,
            12345, 2,
        );

        $stmt = $this->db->prepare(
            "SELECT pos FROM `ibl_box_scores_engine_shadow` WHERE pid = 42 LIMIT 1"
        );
        self::assertNotFalse($stmt);
        $stmt->execute();
        /** @var array{pos: string}|null $row */
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertIsArray($row);
        self::assertSame($injection, $row['pos'], 'pos was altered — insert is not parameterized');
    }

    #[Test]
    public function getAllTeamIdsByPidMapsRosteredPids(): void
    {
        $this->insertTestPlayer(701, 'Known Player', ['teamid' => 5]);
        $this->insertTestPlayer(702, 'Other Player', ['teamid' => 9]);
        $repo = new EngineShadowRepository($this->db);

        $map = $repo->getAllTeamIdsByPid();

        self::assertSame(5, $map[701] ?? null);
        self::assertSame(9, $map[702] ?? null);
    }

    #[Test]
    public function getAllTeamIdsByPidExcludesUnrosteredPids(): void
    {
        // ibl_plr.teamid is NOT NULL, so the map only ever contains rostered pids;
        // a pid absent from ibl_plr is absent from the map. This is exactly the
        // omission that drives the loader's NULL-stamp for an unmapped engine pid
        // (boundary mirror of EngineShadowLoaderTest's pid-999 NULL-teamid case).
        $this->insertTestPlayer(704, 'Rostered Player', ['teamid' => 6]);
        $repo = new EngineShadowRepository($this->db);

        $map = $repo->getAllTeamIdsByPid();

        self::assertSame(6, $map[704] ?? null, 'a rostered pid maps to its teamid');
        self::assertArrayNotHasKey(88888888, $map, 'a pid absent from ibl_plr must not appear in the map');
    }

    #[Test]
    public function deleteShadowGameRemovesMatchingRowsFromBothTablesOnly(): void
    {
        $repo = new EngineShadowRepository($this->db);

        // Target game (date 2090-01-10, visitor 3 @ home 1, game_of_that_day 1):
        // one player row + one team row in each shadow table.
        $repo->insertShadowPlayerBox(
            '2090-01-10', 3, 1, 1,
            42, 3, 'PG',
            30, 5, 10, 4, 5, 2, 6, 2, 6, 5, 2, 2, 1, 3,
            111, 2,
        );
        $repo->insertShadowTeamBox(
            '2090-01-10', 3, 1, 1, 3,
            38, 80, 18, 24, 6, 18, 10, 30, 22, 8, 14, 4, 18,
            28, 26, 24, 25, 0, 30, 27, 26, 24, 0,
            111, 2,
        );

        // Unrelated game on the same date but a different game_of_that_day (2):
        // must survive the targeted delete.
        $repo->insertShadowPlayerBox(
            '2090-01-10', 6, 5, 2,
            77, 5, 'SG',
            25, 4, 9, 1, 2, 0, 1, 1, 4, 3, 1, 2, 0, 2,
            111, 2,
        );

        $deleted = $repo->deleteShadowGame('2090-01-10', 3, 1, 1);

        // Two rows removed (one per table) for the matching game.
        self::assertSame(2, $deleted);
        self::assertSame(0, $this->countGameRows('ibl_box_scores_engine_shadow', '2090-01-10', 3, 1, 1));
        self::assertSame(0, $this->countGameRows('ibl_box_scores_engine_shadow_teams', '2090-01-10', 3, 1, 1));

        // Negative/boundary: the other game's row is untouched.
        self::assertSame(1, $this->countGameRows('ibl_box_scores_engine_shadow', '2090-01-10', 6, 5, 2));
    }

    private function countGameRows(string $table, string $date, int $visitorTid, int $homeTid, int $gameOfThatDay): int
    {
        $allowed = ['ibl_box_scores_engine_shadow', 'ibl_box_scores_engine_shadow_teams'];
        self::assertContains($table, $allowed, 'unexpected shadow table name');
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS cnt FROM `$table`
             WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ? AND game_of_that_day = ?"
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('siii', $date, $visitorTid, $homeTid, $gameOfThatDay);
        $stmt->execute();
        /** @var array{cnt: int} $row */
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int) $row['cnt'];
    }
}
