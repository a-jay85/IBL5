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
            $visitorScore = $row['visitorScore'];
            $homeScore = $row['homeScore'];

            $visitorWon = $visitorScore > $homeScore;

            foreach ([$visitorTid, $homeTid] as $teamid) {
                $teamWon = ($teamid === $visitorTid) ? $visitorWon : !$visitorWon;
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

        // Find the overall best
        $maxStreak = 0;
        foreach ($bestStreaks as $data) {
            if ($data['streak'] > $maxStreak) {
                $maxStreak = $data['streak'];
            }
        }

        // Collect all teams matching the max streak
        /** @var list<StreakRecord> $records */
        $records = [];
        foreach ($bestStreaks as $teamid => $data) {
            if ($data['streak'] === $maxStreak && $maxStreak > 0) {
                $startYear = IblSeasonDateHelper::dateToSeasonEndingYear($data['start']);
                $endYear = IblSeasonDateHelper::dateToSeasonEndingYear($data['end']);
                $records[] = [
                    'team_name' => $resolveTeamName($teamid),
                    'streak' => $data['streak'],
                    'start_date' => $data['start'],
                    'end_date' => $data['end'],
                    'start_year' => $startYear,
                    'end_year' => $endYear,
                ];
            }
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
        // Track season starts per team per season
        /** @var array<string, array{wins: int, losses: int, streakBroken: bool}> $seasonStarts */
        $seasonStarts = [];

        foreach ($games as $row) {
            /** @var array{game_date: string, visitor_teamid: int, home_teamid: int, visitorScore: int, homeScore: int} $row */
            $date = $row['game_date'];
            $visitorTid = $row['visitor_teamid'];
            $homeTid = $row['home_teamid'];
            $visitorScore = $row['visitorScore'];
            $homeScore = $row['homeScore'];
            $visitorWon = $visitorScore > $homeScore;
            $seasonYear = IblSeasonDateHelper::dateToSeasonEndingYear($date);

            foreach ([$visitorTid, $homeTid] as $teamid) {
                $key = $teamid . '-' . $seasonYear;
                if (!isset($seasonStarts[$key])) {
                    $seasonStarts[$key] = ['wins' => 0, 'losses' => 0, 'streakBroken' => false];
                }

                if ($seasonStarts[$key]['streakBroken']) {
                    continue;
                }

                $teamWon = ($teamid === $visitorTid) ? $visitorWon : !$visitorWon;

                if ($type === 'best') {
                    if ($teamWon) {
                        $seasonStarts[$key]['wins']++;
                    } else {
                        $seasonStarts[$key]['streakBroken'] = true;
                    }
                } else {
                    if (!$teamWon) {
                        $seasonStarts[$key]['losses']++;
                    } else {
                        $seasonStarts[$key]['streakBroken'] = true;
                    }
                }
            }
        }

        // Find the record-holding start
        $maxValue = 0;
        foreach ($seasonStarts as $data) {
            $value = $type === 'best' ? $data['wins'] : $data['losses'];
            if ($value > $maxValue) {
                $maxValue = $value;
            }
        }

        /** @var list<SeasonStartRecord> $records */
        $records = [];
        foreach ($seasonStarts as $key => $data) {
            $value = $type === 'best' ? $data['wins'] : $data['losses'];
            if ($value === $maxValue && $maxValue > 0) {
                [$tidStr, $yearStr] = explode('-', $key);
                $teamid = (int) $tidStr;
                $year = (int) $yearStr;
                if ($type === 'best') {
                    $records[] = [
                        'team_name' => $resolveTeamName($teamid),
                        'year' => $year,
                        'wins' => $data['wins'],
                        'losses' => 0,
                    ];
                } else {
                    $records[] = [
                        'team_name' => $resolveTeamName($teamid),
                        'year' => $year,
                        'wins' => 0,
                        'losses' => $data['losses'],
                    ];
                }
            }
        }

        return $records;
    }
}
