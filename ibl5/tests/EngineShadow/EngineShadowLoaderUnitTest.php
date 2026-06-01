<?php

declare(strict_types=1);

namespace Tests\EngineShadow;

use EngineShadow\EngineShadowLoader;
use EngineShadow\EngineShadowRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Non-DB unit tests for EngineShadowLoader's parsing/branch logic, using a
 * recording repository (real loader logic runs; writes captured in-memory). These
 * cover the malformed-input and edge branches the DB-integration suite's
 * well-formed fixture does not exercise.
 */
final class EngineShadowLoaderUnitTest extends TestCase
{
    #[Test]
    public function emptyGamesProducesZeroCounts(): void
    {
        $result = (new EngineShadowLoader($this->recordingRepo()))->load('{"seed":1,"games":[]}');

        self::assertSame(0, $result->gamesLoaded);
        self::assertSame(0, $result->playerRowsInserted);
        self::assertSame(0, $result->teamRowsInserted);
    }

    #[Test]
    public function nonArrayGameEntriesAreSkipped(): void
    {
        $json = (string) json_encode(['seed' => 1, 'games' => ['garbage', 42, null]]);

        $result = (new EngineShadowLoader($this->recordingRepo()))->load($json);

        self::assertSame(0, $result->gamesLoaded);
    }

    #[Test]
    public function nonArrayPlayerBoxesAreSkippedButValidOnesCounted(): void
    {
        $repo = $this->recordingRepo();
        $json = (string) json_encode([
            'seed' => 1,
            'games' => [[
                'date' => '2026-03-10', 'home_team_id' => 1, 'visitor_team_id' => 3,
                'game_of_that_day' => 1, 'sim_game_type' => 2,
                'player_boxes' => ['garbage', ['pid' => 901, 'pos' => 'PG'], null],
                'team_boxes' => [
                    ['team_id' => 3, 'is_home' => false, 'q1' => 1, 'q2' => 1, 'q3' => 1, 'q4' => 1, 'ot' => []],
                    ['team_id' => 1, 'is_home' => true, 'q1' => 1, 'q2' => 1, 'q3' => 1, 'q4' => 1, 'ot' => []],
                ],
            ]],
        ]);

        $result = (new EngineShadowLoader($repo))->load($json);

        self::assertSame(1, $result->gamesLoaded);
        self::assertSame(1, $result->playerRowsInserted, 'only the valid player_box should be inserted');
        self::assertSame(2, $result->teamRowsInserted);
    }

    #[Test]
    public function missingHomeTeamBoxYieldsNoTeamRows(): void
    {
        $repo = $this->recordingRepo();
        $json = (string) json_encode([
            'seed' => 1,
            'games' => [[
                'date' => '2026-03-10', 'home_team_id' => 1, 'visitor_team_id' => 3,
                'game_of_that_day' => 1, 'sim_game_type' => 2,
                'player_boxes' => [],
                'team_boxes' => [
                    ['team_id' => 3, 'is_home' => false, 'q1' => 1, 'q2' => 1, 'q3' => 1, 'q4' => 1, 'ot' => []],
                ], // only the visitor box — no home box
            ]],
        ]);

        $result = (new EngineShadowLoader($repo))->load($json);

        self::assertSame(1, $result->gamesLoaded);
        self::assertSame(0, $result->teamRowsInserted, 'a game missing one team box writes no team rows');
    }

    #[Test]
    public function nonArrayOtIsTreatedAsZero(): void
    {
        // ot as a scalar (not a list) must not fatal — sumOt returns 0, row still writes.
        // Inline anonymous repo captures the bound visitor_ot via insertShadowTeamBox
        // (the loader calls it with the summed value) so PHPStan keeps the property.
        $repo = new class (new \mysqli()) extends EngineShadowRepository {
            public int $capturedVisitorOt = -1;

            public function getTeamIdsForPids(array $pids): array
            {
                return [];
            }

            public function transaction(callable $fn): mixed
            {
                return $fn();
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
                return 1;
            }
        };
        $json = (string) json_encode([
            'seed' => 1,
            'games' => [[
                'date' => '2026-03-10', 'home_team_id' => 1, 'visitor_team_id' => 3,
                'game_of_that_day' => 1, 'sim_game_type' => 2,
                'player_boxes' => [],
                'team_boxes' => [
                    ['team_id' => 3, 'is_home' => false, 'q1' => 1, 'q2' => 1, 'q3' => 1, 'q4' => 1, 'ot' => 99],
                    ['team_id' => 1, 'is_home' => true, 'q1' => 1, 'q2' => 1, 'q3' => 1, 'q4' => 1, 'ot' => []],
                ],
            ]],
        ]);

        $result = (new EngineShadowLoader($repo))->load($json);

        self::assertSame(2, $result->teamRowsInserted);
        self::assertSame(0, $repo->capturedVisitorOt, 'non-array ot must sum to 0');
    }

    /**
     * Recording repository: the loader's real insert methods run (building SQL +
     * params), execute() is a no-op, and transaction() runs the callable directly
     * — all without a DB. Counts are read back from the loader's return value.
     */
    private function recordingRepo(): EngineShadowRepository
    {
        return new class (new \mysqli()) extends EngineShadowRepository {
            public function getTeamIdsForPids(array $pids): array
            {
                return [];
            }

            public function transaction(callable $fn): mixed
            {
                return $fn();
            }

            protected function execute(string $query, string $types = '', mixed ...$params): int
            {
                return 1;
            }
        };
    }
}
