<?php

declare(strict_types=1);

namespace EngineShadow;

/**
 * Decodes the native engine's Result JSON and writes it to the SHADOW box-score
 * tables for in-DB engine-vs-JSB comparison. SHADOW mode only: it never touches
 * the canonical ibl_box_scores / ibl_box_scores_teams (or any other canonical
 * table), and ignores the `events` and `injuries` streams.
 *
 * Identity keys match canonical exactly: visitor_teamid / home_teamid are the
 * ACTUAL GameResult visitor/home (the engine bundle and the .sco import both draw
 * them from ibl_schedule, where visitor can exceed home). Team rows follow the
 * canonical two-rows-per-game shape: the visitor team's row is inserted first,
 * then the home team's, each carrying its own shooting stats plus both teams'
 * quarter points.
 */
final class EngineShadowLoader
{
    public function __construct(
        private readonly EngineShadowRepository $repository,
    ) {
    }

    public function load(string $resultJson): EngineShadowLoadResult
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($resultJson, true, 512, JSON_THROW_ON_ERROR);

        $seed = $this->intVal($decoded, 'seed');
        $games = $this->arrVal($decoded, 'games');

        $pidTeamMap = $this->repository->getTeamIdsForPids($this->collectPids($games));

        $gamesLoaded = 0;
        $playerRows = 0;
        $teamRows = 0;

        foreach ($games as $game) {
            if (!is_array($game)) {
                continue;
            }
            [$pCount, $tCount] = $this->repository->transaction(
                fn (): array => $this->loadGame($game, $seed, $pidTeamMap)
            );
            $gamesLoaded++;
            $playerRows += $pCount;
            $teamRows += $tCount;
        }

        return new EngineShadowLoadResult($gamesLoaded, $playerRows, $teamRows);
    }

    /**
     * @param array<array-key, mixed> $game
     * @param array<int, int>         $pidTeamMap
     *
     * @return array{0: int, 1: int} [playerRowsInserted, teamRowsInserted]
     */
    private function loadGame(array $game, int $seed, array $pidTeamMap): array
    {
        $date = $this->strVal($game, 'date');
        $visitorTeamId = $this->intVal($game, 'visitor_team_id');
        $homeTeamId = $this->intVal($game, 'home_team_id');
        $gameOfThatDay = $this->intVal($game, 'game_of_that_day');
        $simGameType = $this->intVal($game, 'sim_game_type');

        // Re-runs replace, not append: clear any prior shadow rows for this game
        // first. Runs inside the per-game transaction() wrapper from load(), so a
        // mid-game failure rolls the delete back too (atomic delete+insert).
        $this->repository->deleteShadowGame($date, $visitorTeamId, $homeTeamId, $gameOfThatDay);

        $playerRows = 0;
        foreach ($this->arrVal($game, 'player_boxes') as $pb) {
            if (!is_array($pb)) {
                continue;
            }
            $pid = $this->intVal($pb, 'pid');
            $this->repository->insertShadowPlayerBox(
                $date,
                $visitorTeamId,
                $homeTeamId,
                $gameOfThatDay,
                $pid,
                $pidTeamMap[$pid] ?? null,
                $this->strValOrNull($pb, 'pos'),
                $this->intVal($pb, 'gameMIN'),
                $this->intVal($pb, 'game2GM'),
                $this->intVal($pb, 'game2GA'),
                $this->intVal($pb, 'gameFTM'),
                $this->intVal($pb, 'gameFTA'),
                $this->intVal($pb, 'game3GM'),
                $this->intVal($pb, 'game3GA'),
                $this->intVal($pb, 'gameORB'),
                $this->intVal($pb, 'gameDRB'),
                $this->intVal($pb, 'gameAST'),
                $this->intVal($pb, 'gameSTL'),
                $this->intVal($pb, 'gameTOV'),
                $this->intVal($pb, 'gameBLK'),
                $this->intVal($pb, 'gamePF'),
                $seed,
                $simGameType,
            );
            $playerRows++;
        }

        $teamRows = $this->loadTeamBoxes(
            $this->arrVal($game, 'team_boxes'),
            $date,
            $visitorTeamId,
            $homeTeamId,
            $gameOfThatDay,
            $seed,
            $simGameType,
        );

        return [$playerRows, $teamRows];
    }

    /**
     * Writes the two team rows (visitor first, then home) sharing identity keys
     * and quarter points. Returns the number of team rows inserted.
     *
     * @param list<mixed> $teamBoxes
     */
    private function loadTeamBoxes(
        array $teamBoxes,
        string $date,
        int $visitorTeamId,
        int $homeTeamId,
        int $gameOfThatDay,
        int $seed,
        int $simGameType,
    ): int {
        $visitorBox = null;
        $homeBox = null;
        foreach ($teamBoxes as $tb) {
            if (!is_array($tb)) {
                continue;
            }
            if ($this->boolVal($tb, 'is_home')) {
                $homeBox = $tb;
            } else {
                $visitorBox = $tb;
            }
        }

        if ($visitorBox === null || $homeBox === null) {
            return 0;
        }

        $visitorQ = [
            $this->intVal($visitorBox, 'q1'),
            $this->intVal($visitorBox, 'q2'),
            $this->intVal($visitorBox, 'q3'),
            $this->intVal($visitorBox, 'q4'),
            $this->sumOt($visitorBox),
        ];
        $homeQ = [
            $this->intVal($homeBox, 'q1'),
            $this->intVal($homeBox, 'q2'),
            $this->intVal($homeBox, 'q3'),
            $this->intVal($homeBox, 'q4'),
            $this->sumOt($homeBox),
        ];

        // Visitor row first (lower auto-increment id), then home — matching the
        // canonical insert order so row-ordered comparisons line up.
        $this->insertTeamRow($visitorBox, $date, $visitorTeamId, $homeTeamId, $gameOfThatDay, $visitorQ, $homeQ, $seed, $simGameType);
        $this->insertTeamRow($homeBox, $date, $visitorTeamId, $homeTeamId, $gameOfThatDay, $visitorQ, $homeQ, $seed, $simGameType);

        return 2;
    }

    /**
     * @param array<array-key, mixed> $box
     * @param array{0: int, 1: int, 2: int, 3: int, 4: int} $visitorQ
     * @param array{0: int, 1: int, 2: int, 3: int, 4: int} $homeQ
     */
    private function insertTeamRow(
        array $box,
        string $date,
        int $visitorTeamId,
        int $homeTeamId,
        int $gameOfThatDay,
        array $visitorQ,
        array $homeQ,
        int $seed,
        int $simGameType,
    ): void {
        $this->repository->insertShadowTeamBox(
            $date,
            $visitorTeamId,
            $homeTeamId,
            $gameOfThatDay,
            $this->intVal($box, 'team_id'),
            $this->intVal($box, 'game2GM'),
            $this->intVal($box, 'game2GA'),
            $this->intVal($box, 'gameFTM'),
            $this->intVal($box, 'gameFTA'),
            $this->intVal($box, 'game3GM'),
            $this->intVal($box, 'game3GA'),
            $this->intVal($box, 'gameORB'),
            $this->intVal($box, 'gameDRB'),
            $this->intVal($box, 'gameAST'),
            $this->intVal($box, 'gameSTL'),
            $this->intVal($box, 'gameTOV'),
            $this->intVal($box, 'gameBLK'),
            $this->intVal($box, 'gamePF'),
            $visitorQ[0],
            $visitorQ[1],
            $visitorQ[2],
            $visitorQ[3],
            $visitorQ[4],
            $homeQ[0],
            $homeQ[1],
            $homeQ[2],
            $homeQ[3],
            $homeQ[4],
            $seed,
            $simGameType,
        );
    }

    /**
     * @param list<mixed> $games
     *
     * @return list<int>
     */
    private function collectPids(array $games): array
    {
        $pids = [];
        foreach ($games as $game) {
            if (!is_array($game)) {
                continue;
            }
            foreach ($this->arrVal($game, 'player_boxes') as $pb) {
                if (is_array($pb)) {
                    $pids[] = $this->intVal($pb, 'pid');
                }
            }
        }

        return $pids;
    }

    /**
     * @param array<array-key, mixed> $box
     */
    private function sumOt(array $box): int
    {
        $ot = $box['ot'] ?? [];
        if (!is_array($ot)) {
            return 0;
        }
        $sum = 0;
        foreach ($ot as $period) {
            $sum += is_int($period) ? $period : 0;
        }

        return $sum;
    }

    /**
     * @param array<array-key, mixed> $arr
     */
    private function intVal(array $arr, string $key): int
    {
        $value = $arr[$key] ?? 0;

        return is_int($value) ? $value : 0;
    }

    /**
     * @param array<array-key, mixed> $arr
     */
    private function strVal(array $arr, string $key): string
    {
        $value = $arr[$key] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @param array<array-key, mixed> $arr
     */
    private function strValOrNull(array $arr, string $key): ?string
    {
        if (!array_key_exists($key, $arr) || $arr[$key] === null) {
            return null;
        }
        $value = $arr[$key];

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @param array<array-key, mixed> $arr
     */
    private function boolVal(array $arr, string $key): bool
    {
        return (bool) ($arr[$key] ?? false);
    }

    /**
     * @param array<array-key, mixed> $arr
     *
     * @return list<mixed>
     */
    private function arrVal(array $arr, string $key): array
    {
        $value = $arr[$key] ?? [];

        return is_array($value) ? array_values($value) : [];
    }
}
