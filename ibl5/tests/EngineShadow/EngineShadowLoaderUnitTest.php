<?php

declare(strict_types=1);

namespace Tests\EngineShadow;

use EngineShadow\EngineShadowLoader;
use EngineShadow\EngineShadowRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Non-DB unit tests for EngineShadowLoader's per-game parsing/branch logic, using
 * an in-line recording repository (real loader logic runs; writes captured
 * in-memory). These cover the malformed-input and edge branches the DB-integration
 * suite's well-formed fixture does not exercise.
 *
 * The recording repo is declared inline as a local variable in each test (not via
 * a typed helper) so PHPStan retains the anonymous-class type and sees the counter
 * properties. Whole-stream concerns (empty games, non-array game entries, blank
 * lines) now live in EngineRunner's streaming layer — EngineRunnerTest covers them.
 */
final class EngineShadowLoaderUnitTest extends TestCase
{
    #[Test]
    public function nonArrayPlayerBoxesAreSkippedButValidOnesCounted(): void
    {
        $repo = new class (new \mysqli()) extends EngineShadowRepository {
            public int $playerInserts = 0;
            public int $teamInserts = 0;

            public function transaction(callable $fn): mixed
            {
                return $fn();
            }

            public function deleteShadowGame(
                string $gameDate,
                int $visitorTeamId,
                int $homeTeamId,
                int $gameOfThatDay,
            ): int {
                return 0;
            }

            protected function execute(string $query, string $types = '', mixed ...$params): int
            {
                if (str_contains($query, 'engine_shadow_teams')) {
                    $this->teamInserts++;
                } elseif (str_contains($query, 'engine_shadow')) {
                    $this->playerInserts++;
                }
                return 1;
            }
        };
        $game = [
            'date' => '2026-03-10', 'home_team_id' => 1, 'visitor_team_id' => 3,
            'game_of_that_day' => 1, 'sim_game_type' => 2,
            'player_boxes' => ['garbage', ['pid' => 901, 'pos' => 'PG'], null],
            'team_boxes' => [
                ['team_id' => 3, 'is_home' => false, 'q1' => 1, 'q2' => 1, 'q3' => 1, 'q4' => 1, 'ot' => []],
                ['team_id' => 1, 'is_home' => true, 'q1' => 1, 'q2' => 1, 'q3' => 1, 'q4' => 1, 'ot' => []],
            ],
        ];

        (new EngineShadowLoader($repo))->loadOneGame($game, 1, []);

        self::assertSame(1, $repo->playerInserts, 'only the valid player_box should be inserted');
        self::assertSame(2, $repo->teamInserts);
    }

    #[Test]
    public function missingHomeTeamBoxYieldsNoTeamRows(): void
    {
        $repo = new class (new \mysqli()) extends EngineShadowRepository {
            public int $playerInserts = 0;
            public int $teamInserts = 0;

            public function transaction(callable $fn): mixed
            {
                return $fn();
            }

            public function deleteShadowGame(
                string $gameDate,
                int $visitorTeamId,
                int $homeTeamId,
                int $gameOfThatDay,
            ): int {
                return 0;
            }

            protected function execute(string $query, string $types = '', mixed ...$params): int
            {
                if (str_contains($query, 'engine_shadow_teams')) {
                    $this->teamInserts++;
                } elseif (str_contains($query, 'engine_shadow')) {
                    $this->playerInserts++;
                }
                return 1;
            }
        };
        $game = [
            'date' => '2026-03-10', 'home_team_id' => 1, 'visitor_team_id' => 3,
            'game_of_that_day' => 1, 'sim_game_type' => 2,
            'player_boxes' => [],
            'team_boxes' => [
                ['team_id' => 3, 'is_home' => false, 'q1' => 1, 'q2' => 1, 'q3' => 1, 'q4' => 1, 'ot' => []],
            ], // only the visitor box — no home box
        ];

        (new EngineShadowLoader($repo))->loadOneGame($game, 1, []);

        self::assertSame(0, $repo->teamInserts, 'a game missing one team box writes no team rows');
    }

    #[Test]
    public function nonArrayOtIsTreatedAsZero(): void
    {
        // ot as a scalar (not a list) must not fatal — sumOt returns 0, row still writes.
        // Inline anonymous repo captures the bound visitor_ot via insertShadowTeamBox
        // (the loader calls it with the summed value) so PHPStan keeps the property.
        $repo = new class (new \mysqli()) extends EngineShadowRepository {
            public int $capturedVisitorOt = -1;
            public int $teamInserts = 0;

            public function transaction(callable $fn): mixed
            {
                return $fn();
            }

            public function deleteShadowGame(
                string $gameDate,
                int $visitorTeamId,
                int $homeTeamId,
                int $gameOfThatDay,
            ): int {
                return 0;
            }

            protected function execute(string $query, string $types = '', mixed ...$params): int
            {
                return 1;
            }

            public function insertShadowTeamBox(
                string $gameDate, int $visitorTeamId, int $homeTeamId, int $gameOfThatDay, int $teamId,
                int $game2gm, int $game2ga, int $gameFtm, int $gameFta, int $game3gm, int $game3ga,
                int $gameOrb, int $gameDrb, int $gameAst, int $gameStl, int $gameTov, int $gameBlk, int $gamePf,
                int $visitorQ1, int $visitorQ2, int $visitorQ3, int $visitorQ4, int $visitorOt,
                int $homeQ1, int $homeQ2, int $homeQ3, int $homeQ4, int $homeOt,
                int $simSeed, int $simGameType,
            ): int {
                if ($this->capturedVisitorOt === -1) {
                    $this->capturedVisitorOt = $visitorOt;
                }
                $this->teamInserts++;
                return 1;
            }
        };
        $game = [
            'date' => '2026-03-10', 'home_team_id' => 1, 'visitor_team_id' => 3,
            'game_of_that_day' => 1, 'sim_game_type' => 2,
            'player_boxes' => [],
            'team_boxes' => [
                ['team_id' => 3, 'is_home' => false, 'q1' => 1, 'q2' => 1, 'q3' => 1, 'q4' => 1, 'ot' => 99],
                ['team_id' => 1, 'is_home' => true, 'q1' => 1, 'q2' => 1, 'q3' => 1, 'q4' => 1, 'ot' => []],
            ],
        ];

        (new EngineShadowLoader($repo))->loadOneGame($game, 1, []);

        self::assertSame(2, $repo->teamInserts);
        self::assertSame(0, $repo->capturedVisitorOt, 'non-array ot must sum to 0');
    }
}
