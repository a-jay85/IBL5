<?php

declare(strict_types=1);

namespace Updater;

/**
 * Parse playoff games from JSB's Schedule.htm HTML export.
 *
 * Schedule.htm contains the full season schedule including playoffs.
 * Playoff dates use "Post X YYYY" headers. Games may be played (with scores
 * and box score links) or upcoming (empty score cells).
 */
class ScheduleHtmParser
{
    /**
     * Parse all playoff games from Schedule.htm HTML content.
     *
     * @return list<array{
     *     date_label: string,
     *     visitor: string,
     *     home: string,
     *     visitor_score: int,
     *     home_score: int,
     *     box_id: int|null,
     *     played: bool
     * }>
     */
    public static function parsePlayoffGames(string $html): array
    {
        $games = [];
        $currentDate = null;

        preg_match_all('/<tr>(.*?)<\/tr>/s', $html, $rowMatches);

        foreach ($rowMatches[1] as $rowContent) {
            $trimmed = trim($rowContent);

            // Date header: single <th> with "Post X YYYY"
            if (preg_match('/^<th>(Post \d+ \d+)<\/th>$/', $trimmed, $dateMatch) === 1) {
                $currentDate = $dateMatch[1];
                continue;
            }

            // Skip everything before the first "Post" date
            if ($currentDate === null) {
                continue;
            }

            // Skip column header rows
            if (str_contains($rowContent, '<th>visitor</th>')) {
                continue;
            }

            // Parse game row: expect 4 <td> cells
            preg_match_all('/<td>(.*?)<\/td>/', $rowContent, $cellMatches);
            if (count($cellMatches[1]) !== 4) {
                continue;
            }

            $visitor = self::extractTeamName($cellMatches[1][0]);
            $home = self::extractTeamName($cellMatches[1][2]);

            if ($visitor === null || $home === null) {
                continue;
            }

            $vScore = self::extractScore($cellMatches[1][1]);
            $hScore = self::extractScore($cellMatches[1][3]);
            $boxId = self::extractBoxId($cellMatches[1][1]);
            $played = $vScore > 0 || $hScore > 0;

            $games[] = [
                'date_label' => $currentDate,
                'visitor' => $visitor,
                'home' => $home,
                'visitor_score' => $vScore,
                'home_score' => $hScore,
                'box_id' => $boxId,
                'played' => $played,
            ];
        }

        return $games;
    }

    private static function extractTeamName(string $cellContent): ?string
    {
        if (preg_match('/<a [^>]*>([^<]+)<\/a>/', $cellContent, $match) === 1) {
            return trim($match[1]);
        }
        return null;
    }

    private static function extractScore(string $cellContent): int
    {
        if ($cellContent === '') {
            return 0;
        }
        if (preg_match('/>(\d+)<\/a>/', $cellContent, $match) === 1) {
            return (int) $match[1];
        }
        return 0;
    }

    private static function extractBoxId(string $cellContent): ?int
    {
        if (preg_match('/href="box(\d+)\.htm"/', $cellContent, $match) === 1) {
            return (int) $match[1];
        }
        return null;
    }
}
