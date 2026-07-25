<?php

declare(strict_types=1);

namespace GameBoxscore;

use GameBoxscore\Contracts\GameBoxscoreRepositoryInterface;
use GameBoxscore\Contracts\GameBoxscoreServiceInterface;

/**
 * GameBoxscoreService - Business logic for a single game's box score
 *
 * The trust boundary between raw `$_GET` input and the Repository: validates
 * the requested date and game number, normalizes/types the raw Repository
 * rows into a single view-model, splits players into away/home by the
 * `isAwayPlayer` flag, and computes each team's totals row.
 *
 * @phpstan-import-type GameBoxscoreViewModel from GameBoxscoreServiceInterface
 * @phpstan-import-type GameBoxscorePlayerRow from GameBoxscoreServiceInterface
 * @phpstan-import-type GameBoxscoreTeamHeader from GameBoxscoreServiceInterface
 * @phpstan-import-type GameBoxscoreTotals from GameBoxscoreServiceInterface
 *
 * @see GameBoxscoreServiceInterface For the interface contract
 */
class GameBoxscoreService implements GameBoxscoreServiceInterface
{
    /**
     * Numeric stat keys shared by player rows and totals rows, in view-model order.
     *
     * @var list<string>
     */
    private const NUMERIC_STAT_KEYS = [
        'min', 'fgm', 'fga', 'ftm', 'fta', 'tpm', 'tpa', 'pts',
        'orb', 'reb', 'ast', 'stl', 'blk', 'tov', 'pf',
    ];

    private GameBoxscoreRepositoryInterface $repository;

    public function __construct(GameBoxscoreRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see GameBoxscoreServiceInterface::getBoxscore()
     *
     * @return GameBoxscoreViewModel
     */
    public function getBoxscore(mixed $rawDate, mixed $rawGame): array
    {
        $date = $this->validateDate($rawDate);
        $game = $this->validateGame($rawGame);

        if ($date === null || $game === null) {
            return $this->notFound();
        }

        $gameInfo = $this->repository->getGameInfo($date, $game);
        if ($gameInfo === null) {
            return $this->notFound();
        }

        $awayTeamId = (int) ($gameInfo['awayTeamId'] ?? 0);
        $homeTeamId = (int) ($gameInfo['homeTeamId'] ?? 0);

        $rows = $this->repository->getPlayerRows($date, $game, $awayTeamId, $homeTeamId);

        /** @var list<GameBoxscorePlayerRow> $awayPlayers */
        $awayPlayers = [];
        /** @var list<GameBoxscorePlayerRow> $homePlayers */
        $homePlayers = [];

        foreach ($rows as $row) {
            $player = $this->normalizePlayer($row);
            if (((int) ($row['isAwayPlayer'] ?? 0)) === 1) {
                $awayPlayers[] = $player;
            } else {
                $homePlayers[] = $player;
            }
        }

        $awayScore = (int) ($gameInfo['awayScore'] ?? 0);
        $homeScore = (int) ($gameInfo['homeScore'] ?? 0);

        return [
            'found' => true,
            'date' => $date,
            'gameOfThatDay' => $game,
            'awayTeam' => $this->buildTeamHeader($gameInfo, 'away', $awayTeamId, $awayScore),
            'homeTeam' => $this->buildTeamHeader($gameInfo, 'home', $homeTeamId, $homeScore),
            'awayPlayers' => $awayPlayers,
            'homePlayers' => $homePlayers,
            'awayTotals' => $this->computeTotals($awayPlayers),
            'homeTotals' => $this->computeTotals($homePlayers),
        ];
    }

    private function validateDate(mixed $raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) !== 1) {
            return null;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $raw));
        return checkdate($m, $d, $y) ? $raw : null;
    }

    private function validateGame(mixed $raw): ?int
    {
        if (is_int($raw)) {
            return $raw > 0 ? $raw : null;
        }
        if (is_string($raw) && preg_match('/^\d+$/', $raw) === 1) {
            $n = (int) $raw;
            return $n > 0 ? $n : null;
        }
        return null;
    }

    /**
     * @param array<string, int|float|string|null> $row
     * @return GameBoxscorePlayerRow
     */
    private function normalizePlayer(array $row): array
    {
        $player = ['pid' => (int) ($row['pid'] ?? 0)];

        foreach (self::NUMERIC_STAT_KEYS as $key) {
            $player[$key] = (int) ($row[$key] ?? 0);
        }

        $pos = (string) ($row['pos'] ?? '');
        $player['pos'] = $pos !== '' ? $pos : 'N/A';

        $name = (string) ($row['name'] ?? '');
        $player['name'] = $name !== '' ? $name : 'Unknown';

        return $player;
    }

    /**
     * @param array<string, int|float|string|null> $gameInfo
     * @return GameBoxscoreTeamHeader
     */
    private function buildTeamHeader(array $gameInfo, string $side, int $teamId, int $score): array
    {
        $name = (string) ($gameInfo[$side . 'TeamName'] ?? '');
        $city = (string) ($gameInfo[$side . 'TeamCity'] ?? '');
        $color1 = (string) ($gameInfo[$side . 'Color1'] ?? '');
        $color2 = (string) ($gameInfo[$side . 'Color2'] ?? '');

        return [
            'teamId' => $teamId,
            'name' => $name,
            'city' => $city,
            'color1' => $color1 !== '' ? $color1 : 'FFFFFF',
            'color2' => $color2 !== '' ? $color2 : '000000',
            'score' => $score,
        ];
    }

    /**
     * @param list<GameBoxscorePlayerRow> $players
     * @return GameBoxscoreTotals
     */
    private function computeTotals(array $players): array
    {
        $totals = array_fill_keys(self::NUMERIC_STAT_KEYS, 0);

        foreach ($players as $player) {
            foreach (self::NUMERIC_STAT_KEYS as $key) {
                $totals[$key] += $player[$key];
            }
        }

        return $totals;
    }

    /**
     * @return GameBoxscoreViewModel
     */
    private function notFound(): array
    {
        /** @var GameBoxscoreTeamHeader $emptyHeader */
        $emptyHeader = [
            'teamId' => 0,
            'name' => '',
            'city' => '',
            'color1' => 'FFFFFF',
            'color2' => '000000',
            'score' => 0,
        ];

        $emptyTotals = array_fill_keys(self::NUMERIC_STAT_KEYS, 0);

        return [
            'found' => false,
            'date' => '',
            'gameOfThatDay' => 0,
            'awayTeam' => $emptyHeader,
            'homeTeam' => $emptyHeader,
            'awayPlayers' => [],
            'homePlayers' => [],
            'awayTotals' => $emptyTotals,
            'homeTotals' => $emptyTotals,
        ];
    }
}
