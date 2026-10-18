<?php

declare(strict_types=1);

namespace RecordHolders;

use RecordHolders\Contracts\RecordHoldersRepositoryInterface;

/**
 * Pure streak/season-start computation extracted from RecordHoldersRepository.
 * No database dependency: operates on already-fetched game rows.
 * @phpstan-import-type StreakRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type SeasonStartRecord from RecordHoldersRepositoryInterface
 */
final class StreakCalculator
{
    /**
     * @param list<array{game_date: string, visitor_teamid: int, home_teamid: int, visitorScore: int, homeScore: int}> $games
     * @param callable(int): string $resolveTeamName
     * @return list<StreakRecord>
     */
    public static function longestStreak(array $games, string $type, callable $resolveTeamName): array
    {
        // Track streaks per team
        /** @var array<int, array{current: int, start: string, team: int}> $streaks */
        $streaks = [];
        /** @var array<int, array{streak: int, start: string, end: string}> $bestStreaks */
        $bestStreaks = [];

        foreach ($games as $row) {
            /** @var array{game_date: string, visitor_teamid: int, home_teamid: int, visitorScore: int, homeScore: int} $row */
            $date = $row['game_date'];
            $visitorTid = $row['visitor_teamid'];
            $homeTid = $row['home_teamid'];

            foreach ([$visitorTid, $homeTid] as $teamid) {
                $teamWon = self::teamResult($row, $teamid);
                $isStreakType = ($type === 'winning') ? $teamWon : !$teamWon;

                if (!isset($streaks[$teamid])) {
                    $streaks[$teamid] = ['current' => 0, 'start' => '', 'team' => $teamid];
                    $bestStreaks[$teamid] = ['streak' => 0, 'start' => '', 'end' => ''];
                }

                if ($isStreakType) {
                    if ($streaks[$teamid]['current'] === 0) {
                        $streaks[$teamid]['start'] = $date;
                    }
                    $streaks[$teamid]['current']++;
                    if ($streaks[$teamid]['current'] > $bestStreaks[$teamid]['streak']) {
                        $bestStreaks[$teamid] = [
                            'streak' => $streaks[$teamid]['current'],
                            'start' => $streaks[$teamid]['start'],
                            'end' => $date,
                        ];
                    }
                } else {
                    $streaks[$teamid]['current'] = 0;
                }
            }
        }

        /** @var list<StreakRecord> $records */
        $records = [];
        $holders = self::collectMaxHolders($bestStreaks, static fn (array $data): int => $data['streak']);
        foreach ($holders as $teamid => $data) {
            $records[] = [
                'team_name' => $resolveTeamName($teamid),
                'streak' => $data['streak'],
                'start_date' => $data['start'],
                'end_date' => $data['end'],
                'start_year' => IblSeasonDateHelper::dateToSeasonEndingYear($data['start']),
                'end_year' => IblSeasonDateHelper::dateToSeasonEndingYear($data['end']),
            ];
        }

        return $records;
    }

    /**
     * @param list<array{game_date: string, visitor_teamid: int, home_teamid: int, visitorScore: int, homeScore: int}> $games
     * @param callable(int): string $resolveTeamName
     * @return list<SeasonStartRecord>
     */
    public static function bestWorstSeasonStart(array $games, string $type, callable $resolveTeamName): array
    {
        // Track season starts per team per season; 'count' is wins for 'best', losses otherwise
        /** @var array<string, array{teamid: int, year: int, count: int, streakBroken: bool}> $seasonStarts */
        $seasonStarts = [];

        foreach ($games as $row) {
            /** @var array{game_date: string, visitor_teamid: int, home_teamid: int, visitorScore: int, homeScore: int} $row */
            $date = $row['game_date'];
            $visitorTid = $row['visitor_teamid'];
            $homeTid = $row['home_teamid'];
            $seasonYear = IblSeasonDateHelper::dateToSeasonEndingYear($date);

            foreach ([$visitorTid, $homeTid] as $teamid) {
                $key = $teamid . '-' . $seasonYear;
                if (!isset($seasonStarts[$key])) {
                    $seasonStarts[$key] = ['teamid' => $teamid, 'year' => $seasonYear, 'count' => 0, 'streakBroken' => false];
                }

                if ($seasonStarts[$key]['streakBroken']) {
                    continue;
                }

                $teamWon = self::teamResult($row, $teamid);
                $extendsStart = ($type === 'best') ? $teamWon : !$teamWon;

                if ($extendsStart) {
                    $seasonStarts[$key]['count']++;
                } else {
                    $seasonStarts[$key]['streakBroken'] = true;
                }
            }
        }

        /** @var list<SeasonStartRecord> $records */
        $records = [];
        $holders = self::collectMaxHolders($seasonStarts, static fn (array $start): int => $start['count']);
        foreach ($holders as $start) {
            $records[] = self::seasonStartRecord($type, $resolveTeamName($start['teamid']), $start['year'], $start['count']);
        }

        return $records;
    }

    /**
     * Whether $teamid won this game. A tied score counts as a home win. When both
     * team ids are equal (degenerate self-play row) the team takes the visitor's result.
     *
     * @param array{game_date: string, visitor_teamid: int, home_teamid: int, visitorScore: int, homeScore: int} $row
     */
    private static function teamResult(array $row, int $teamid): bool
    {
        $visitorWon = $row['visitorScore'] > $row['homeScore'];

        return ($teamid === $row['visitor_teamid']) ? $visitorWon : !$visitorWon;
    }

    /**
     * Entries whose value equals the maximum, with keys and insertion order preserved.
     * Returns [] when $byKey is empty or the maximum is not positive.
     *
     * @template K of array-key
     * @template T
     * @param array<K, T> $byKey
     * @param callable(T): int $valueOf
     * @return array<K, T>
     */
    private static function collectMaxHolders(array $byKey, callable $valueOf): array
    {
        if ($byKey === []) {
            return [];
        }

        $values = array_map($valueOf, $byKey);
        $max = max($values);
        if ($max <= 0) {
            return [];
        }

        return array_intersect_key($byKey, array_filter($values, static fn (int $value): bool => $value === $max));
    }

    /**
     * @return SeasonStartRecord
     */
    private static function seasonStartRecord(string $type, string $teamName, int $year, int $count): array
    {
        $isBest = $type === 'best';

        return [
            'team_name' => $teamName,
            'year' => $year,
            'wins' => $isBest ? $count : 0,
            'losses' => $isBest ? 0 : $count,
        ];
    }
}
