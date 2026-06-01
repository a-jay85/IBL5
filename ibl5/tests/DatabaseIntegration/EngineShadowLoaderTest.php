<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use EngineShadow\EngineShadowLoader;
use EngineShadow\EngineShadowRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * DB-integration tests for EngineShadowLoader against the real schema. Drives a
 * captured Result JSON fixture (no engine binary needed) and proves: shadow rows
 * are written with the correct mapping, identity keys are the ACTUAL visitor/home
 * (not lower-id swapped), and canonical tables are never touched.
 *
 * Grounding: the fixture's single game is visitor_team_id=3, home_team_id=1 —
 * visitor > home, the exact case the CI seed exercises (ibl_schedule 2026-03-10,
 * 3@1) and the case a lower-id swap would corrupt.
 */
#[Group('database')]
final class EngineShadowLoaderTest extends DatabaseTestCase
{
    private const GAME_DATE = '2026-03-10';
    private const VISITOR_TID = 3;
    private const HOME_TID = 1;
    private const SEED = 12345;

    protected function setUp(): void
    {
        parent::setUp();
        // Roster the engine's pids so teamid resolves; 999 is deliberately absent.
        $this->insertTestPlayer(901, 'Visitor Star', ['teamid' => self::VISITOR_TID]);
        $this->insertTestPlayer(902, 'Home Center', ['teamid' => self::HOME_TID]);
        $this->insertTestPlayer(903, 'Visitor Bench DNP', ['teamid' => self::VISITOR_TID]);
    }

    #[Test]
    public function writesPlayerRowsWithResolvedTeamId(): void
    {
        $this->loadFixtureGame();

        // loadOneGame is void; assert the written rows by querying the table.
        self::assertSame(4, $this->countShadowRowsForGame('ibl_box_scores_engine_shadow'));
        self::assertSame(2, $this->countShadowRowsForGame('ibl_box_scores_engine_shadow_teams'));

        $star = $this->fetchPlayerRow(901);
        self::assertSame(self::VISITOR_TID, (int) $star['teamid']);
        self::assertSame(self::VISITOR_TID, (int) $star['visitor_teamid']);
        self::assertSame(self::HOME_TID, (int) $star['home_teamid']);
        self::assertSame(8, (int) $star['game_2gm']);
        self::assertSame(36, (int) $star['game_min']);
        self::assertSame(12345, (int) $star['sim_seed']);
        self::assertSame(2, (int) $star['sim_game_type']);

        // A pid absent from ibl_plr resolves to a NULL teamid (not 0).
        $unknown = $this->fetchPlayerRow(999);
        self::assertNull($unknown['teamid']);
    }

    #[Test]
    public function teamRowsUseActualVisitorHomeAndInsertVisitorFirst(): void
    {
        $this->loadFixtureGame();

        $rows = $this->fetchTeamRowsOrdered();
        self::assertCount(2, $rows);

        // Identity keys are the ACTUAL visitor/home — NOT min/max swapped.
        foreach ($rows as $row) {
            self::assertSame(self::VISITOR_TID, (int) $row['visitor_teamid']);
            self::assertSame(self::HOME_TID, (int) $row['home_teamid']);
        }

        // Visitor team row inserted first (lower auto-increment id).
        [$visitorRow, $homeRow] = $rows;
        self::assertSame(self::VISITOR_TID, (int) $visitorRow['teamid']);
        self::assertSame(self::HOME_TID, (int) $homeRow['teamid']);

        // Each row carries its own shooting stats...
        self::assertSame(38, (int) $visitorRow['game_2gm']);
        self::assertSame(33, (int) $homeRow['game_2gm']);

        // ...and both rows carry both teams' quarter points (visitor=28.., home=30..).
        foreach ($rows as $row) {
            self::assertSame(28, (int) $row['visitor_q1_points']);
            self::assertSame(30, (int) $row['home_q1_points']);
            self::assertSame(3, (int) $row['visitor_ot_points']); // OT array [3] summed
            self::assertSame(0, (int) $row['home_ot_points']);
        }
    }

    #[Test]
    public function ignoresEventsAndInjuriesAndNeverTouchesCanonical(): void
    {
        $before = [
            'players' => $this->countRows('ibl_box_scores'),
            'teams' => $this->countRows('ibl_box_scores_teams'),
            'injuries' => $this->countRows('ibl_jsb_transactions'),
        ];

        $this->loadFixtureGame();

        self::assertSame($before['players'], $this->countRows('ibl_box_scores'), 'canonical player boxscores changed');
        self::assertSame($before['teams'], $this->countRows('ibl_box_scores_teams'), 'canonical team boxscores changed');
        self::assertSame($before['injuries'], $this->countRows('ibl_jsb_transactions'), 'canonical injuries changed');
    }

    #[Test]
    public function midGameInsertFailureRollsBackThatGameAndLeavesCanonicalUntouched(): void
    {
        // Repository that fails on the team-table insert — AFTER all four player
        // inserts have run within the same per-game transaction.
        $failingRepo = new class($this->db) extends EngineShadowRepository {
            protected function execute(string $query, string $types = '', mixed ...$params): int
            {
                if (str_contains($query, 'ibl_box_scores_engine_shadow_teams')) {
                    throw new \RuntimeException('forced team insert failure');
                }
                return parent::execute($query, $types, ...$params);
            }
        };

        $canonicalTeamsBefore = $this->countRows('ibl_box_scores_teams');
        $pidMap = (new EngineShadowRepository($this->db))->getAllTeamIdsByPid();

        try {
            (new EngineShadowLoader($failingRepo))->loadOneGame($this->fixtureGame(), self::SEED, $pidMap);
            self::fail('Expected the forced team-insert failure to propagate');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('forced team insert failure', $e->getMessage());
        }

        // The whole game rolled back: the four player rows that succeeded are gone.
        // Scope to this game's date so the assertion is robust to any pre-existing
        // shadow rows (CI bootstraps these tables empty; a dev DB may not be).
        self::assertSame(0, $this->countShadowRowsForGame('ibl_box_scores_engine_shadow'), 'shadow player rows not rolled back');
        self::assertSame(0, $this->countShadowRowsForGame('ibl_box_scores_engine_shadow_teams'), 'shadow team rows not rolled back');
        // Canonical is untouched throughout.
        self::assertSame($canonicalTeamsBefore, $this->countRows('ibl_box_scores_teams'));
    }

    #[Test]
    public function reRunReplacesRowsRatherThanAppending(): void
    {
        $this->loadFixtureGame();
        $this->loadFixtureGame(); // same game again

        // Count the TABLE: a broken dedupe leaves 8/4 here.
        self::assertSame(4, $this->countShadowRowsForGame('ibl_box_scores_engine_shadow'), 'player rows doubled — dedupe failed');
        self::assertSame(2, $this->countShadowRowsForGame('ibl_box_scores_engine_shadow_teams'), 'team rows doubled — dedupe failed');
    }

    #[Test]
    public function reRunDedupeLeavesUnrelatedGameIntact(): void
    {
        $repo = new EngineShadowRepository($this->db);

        // Pre-seed an unrelated game (different date AND keys) directly.
        $repo->insertShadowPlayerBox(
            '2089-05-05', 6, 5, 1,
            42, 5, 'PG',
            30, 5, 10, 4, 5, 2, 6, 2, 6, 5, 2, 2, 1, 3,
            999, 2,
        );

        // Load the fixture game twice; dedupe is scoped to the fixture's keys only.
        $loader = new EngineShadowLoader($repo);
        $pidMap = $repo->getAllTeamIdsByPid();
        $loader->loadOneGame($this->fixtureGame(), self::SEED, $pidMap);
        $loader->loadOneGame($this->fixtureGame(), self::SEED, $pidMap);

        self::assertSame(1, $this->countPlayerRowsForDate('2089-05-05'), 'unrelated game rows must be untouched by dedupe');
    }

    /**
     * Drive the fixture's single game through loadOneGame with the live pid map
     * — the streaming entry point that replaces the removed whole-blob load().
     */
    private function loadFixtureGame(): void
    {
        $repo = new EngineShadowRepository($this->db);
        (new EngineShadowLoader($repo))->loadOneGame($this->fixtureGame(), self::SEED, $repo->getAllTeamIdsByPid());
    }

    /** @return array<string, mixed> the fixture's single decoded game */
    private function fixtureGame(): array
    {
        /** @var array{games: list<array<string, mixed>>} $decoded */
        $decoded = json_decode($this->resultJson(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded['games'][0];
    }

    private function countPlayerRowsForDate(string $date): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS cnt FROM `ibl_box_scores_engine_shadow` WHERE game_date = ?"
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $date);
        $stmt->execute();
        /** @var array{cnt: int} $row */
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int) $row['cnt'];
    }

    /** @return array<string, mixed> */
    private function fetchPlayerRow(int $pid): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM `ibl_box_scores_engine_shadow` WHERE pid = ? AND game_date = ? LIMIT 1"
        );
        self::assertNotFalse($stmt);
        $date = self::GAME_DATE;
        $stmt->bind_param('is', $pid, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        $stmt->close();
        self::assertIsArray($row, "no shadow player row for pid $pid");

        return $row;
    }

    /** @return list<array<string, mixed>> ordered by insert order (id) */
    private function fetchTeamRowsOrdered(): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM `ibl_box_scores_engine_shadow_teams` WHERE game_date = ? ORDER BY id ASC"
        );
        self::assertNotFalse($stmt);
        $date = self::GAME_DATE;
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while (($row = $result->fetch_assoc()) !== null) {
            $rows[] = $row;
        }
        $stmt->close();

        return $rows;
    }

    /** Count rows in a shadow table for this test's game date only. */
    private function countShadowRowsForGame(string $table): int
    {
        $allowed = ['ibl_box_scores_engine_shadow', 'ibl_box_scores_engine_shadow_teams'];
        self::assertContains($table, $allowed, 'unexpected shadow table name');
        $stmt = $this->db->prepare("SELECT COUNT(*) AS cnt FROM `$table` WHERE game_date = ?");
        self::assertNotFalse($stmt);
        $date = self::GAME_DATE;
        $stmt->bind_param('s', $date);
        $stmt->execute();
        /** @var array{cnt: int} $row */
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int) $row['cnt'];
    }

    private function countRows(string $table): int
    {
        $result = $this->db->query("SELECT COUNT(*) AS cnt FROM `$table`");
        self::assertInstanceOf(\mysqli_result::class, $result);
        /** @var array{cnt: int} $row */
        $row = $result->fetch_assoc();
        $result->free();

        return (int) $row['cnt'];
    }

    private function resultJson(): string
    {
        return (string) json_encode([
            'seed' => 12345,
            'games' => [[
                'date' => self::GAME_DATE,
                'home_team_id' => self::HOME_TID,
                'visitor_team_id' => self::VISITOR_TID,
                'game_of_that_day' => 1,
                'sim_game_type' => 2,
                'events' => [['kind' => 'shot_make', 'period' => 1]], // ignored
                'injuries' => [['pid' => 901, 'team_id' => 3, 'games_missed' => 2]], // ignored
                'player_boxes' => [
                    $this->playerBox(901, 'PG', 36, 8, 15, 4, 5, 2, 6, 1, 5, 7, 2, 3, 0, 2),
                    $this->playerBox(902, 'C', 30, 6, 10, 2, 2, 0, 0, 4, 8, 1, 0, 2, 3, 4),
                    $this->playerBox(903, 'SF', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0), // DNP
                    $this->playerBox(999, 'SG', 12, 2, 5, 0, 0, 1, 3, 0, 2, 1, 1, 1, 0, 1), // not rostered
                ],
                'team_boxes' => [
                    // visitor first per the engine contract
                    $this->teamBox(self::VISITOR_TID, false, [28, 26, 24, 25], [3], 38, 80, 18, 24, 6, 18, 10, 30, 22, 8, 14, 4, 18),
                    $this->teamBox(self::HOME_TID, true, [30, 27, 26, 24], [], 33, 78, 20, 26, 8, 22, 9, 28, 20, 7, 12, 5, 17),
                ],
            ]],
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, int|string>
     */
    private function playerBox(
        int $pid,
        string $pos,
        int $min,
        int $g2m,
        int $g2a,
        int $ftm,
        int $fta,
        int $g3m,
        int $g3a,
        int $orb,
        int $drb,
        int $ast,
        int $stl,
        int $tov,
        int $blk,
        int $pf,
    ): array {
        return [
            'pid' => $pid, 'pos' => $pos, 'gameMIN' => $min,
            'game2GM' => $g2m, 'game2GA' => $g2a, 'gameFTM' => $ftm, 'gameFTA' => $fta,
            'game3GM' => $g3m, 'game3GA' => $g3a, 'gameORB' => $orb, 'gameDRB' => $drb,
            'gameAST' => $ast, 'gameSTL' => $stl, 'gameTOV' => $tov, 'gameBLK' => $blk, 'gamePF' => $pf,
        ];
    }

    /**
     * @param array{0: int, 1: int, 2: int, 3: int} $quarters
     * @param list<int>                             $ot
     *
     * @return array<string, mixed>
     */
    private function teamBox(
        int $teamId,
        bool $isHome,
        array $quarters,
        array $ot,
        int $g2m,
        int $g2a,
        int $ftm,
        int $fta,
        int $g3m,
        int $g3a,
        int $orb,
        int $drb,
        int $ast,
        int $stl,
        int $tov,
        int $blk,
        int $pf,
    ): array {
        return [
            'team_id' => $teamId, 'is_home' => $isHome,
            'q1' => $quarters[0], 'q2' => $quarters[1], 'q3' => $quarters[2], 'q4' => $quarters[3], 'ot' => $ot,
            'game2GM' => $g2m, 'game2GA' => $g2a, 'gameFTM' => $ftm, 'gameFTA' => $fta,
            'game3GM' => $g3m, 'game3GA' => $g3a, 'gameORB' => $orb, 'gameDRB' => $drb,
            'gameAST' => $ast, 'gameSTL' => $stl, 'gameTOV' => $tov, 'gameBLK' => $blk, 'gamePF' => $pf,
        ];
    }
}
