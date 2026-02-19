<?php

declare(strict_types=1);

namespace JsbParser;

use JsbParser\Contracts\HisFileParserInterface;

/**
 * Parser for JSB .his (Historical Results) text files.
 *
 * The .his format contains team-by-team season results organized in season blocks.
 * Each line describes a team's W-L record and optional playoff outcome for a given year.
 *
 * Line format: "TeamName (W-L) [playoff result] (Year)"
 *
 * @see /docs/JSB_FILE_FORMATS.md
 */
class HisFileParser implements HisFileParserInterface
{
    /**
     * Regex for parsing team lines.
     *
     * Captures: (1) team name, (2) wins, (3) losses, (4) playoff text (optional), (5) year
     */
    private const TEAM_LINE_PATTERN = '/^(.+?)\s+\((\d+)-(\d+)\)\s*(.*?)\s*\((\d{4})\)\s*$/';

    /**
     * @see HisFileParserInterface::parseFile()
     */
    public static function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("HIS file not found: {$filePath}");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read HIS file: {$filePath}");
        }

        // Normalize line endings (CRLF → LF)
        $content = str_replace("\r\n", "\n", $content);
        $lines = explode("\n", $content);

        /** @var array<int, list<array{name: string, wins: int, losses: int, year: int, playoff_result: string, made_playoffs: int, playoff_round_reached: string, won_championship: int}>> $seasonMap */
        $seasonMap = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $parsed = self::parseTeamLine($trimmed);
            if ($parsed === null) {
                continue;
            }

            $year = $parsed['year'];
            if (!isset($seasonMap[$year])) {
                $seasonMap[$year] = [];
            }
            $seasonMap[$year][] = $parsed;
        }

        // Sort by year and build result
        ksort($seasonMap);

        $seasons = [];
        foreach ($seasonMap as $year => $teams) {
            $seasons[] = [
                'year' => $year,
                'teams' => $teams,
            ];
        }

        return $seasons;
    }

    /**
     * @see HisFileParserInterface::parseTeamLine()
     */
    public static function parseTeamLine(string $line): ?array
    {
        if (preg_match(self::TEAM_LINE_PATTERN, $line, $matches) !== 1) {
            return null;
        }

        $teamName = trim($matches[1]);
        $wins = (int) $matches[2];
        $losses = (int) $matches[3];
        $playoffText = trim($matches[4]);
        $year = (int) $matches[5];

        $madePlayoffs = 0;
        $playoffRoundReached = '';
        $wonChampionship = 0;

        if ($playoffText !== '') {
            $madePlayoffs = 1;
            $roundInfo = self::detectPlayoffRound($playoffText);
            $playoffRoundReached = $roundInfo['round'];
            $wonChampionship = $roundInfo['won_championship'];
        }

        return [
            'name' => $teamName,
            'wins' => $wins,
            'losses' => $losses,
            'year' => $year,
            'playoff_result' => $playoffText,
            'made_playoffs' => $madePlayoffs,
            'playoff_round_reached' => $playoffRoundReached,
            'won_championship' => $wonChampionship,
        ];
    }

    /**
     * Detect the playoff round reached from the result text.
     *
     * @return array{round: string, won_championship: int}
     */
    private static function detectPlayoffRound(string $playoffText): array
    {
        $lower = strtolower($playoffText);

        // Championship winner: contains "defeat the" or "defeat  the" (double space in some files)
        if (preg_match('/defeat\s+the/', $lower) === 1) {
            // Check what round it was in
            if (str_contains($lower, 'championship')) {
                return ['round' => 'championship', 'won_championship' => 1];
            }
            // "defeat the X in the championship" - this IS the championship
            return ['round' => 'championship', 'won_championship' => 1];
        }

        // Championship loser
        if (str_contains($lower, 'championship')) {
            return ['round' => 'championship', 'won_championship' => 0];
        }

        if (str_contains($lower, 'finals') && !str_contains($lower, 'semi-finals') && !str_contains($lower, 'quarter-finals')) {
            return ['round' => 'finals', 'won_championship' => 0];
        }

        if (str_contains($lower, 'semi-finals') || str_contains($lower, 'semi finals')) {
            return ['round' => 'semi-finals', 'won_championship' => 0];
        }

        if (str_contains($lower, 'quarter-finals') || str_contains($lower, 'quarter finals')) {
            return ['round' => 'quarter-finals', 'won_championship' => 0];
        }

        // Generic round references: "2nd round", "1st round", "first round"
        if (preg_match('/(first|1st)\s+round/i', $playoffText) === 1) {
            return ['round' => 'first round', 'won_championship' => 0];
        }

        if (preg_match('/(second|2nd)\s+round/i', $playoffText) === 1) {
            return ['round' => 'second round', 'won_championship' => 0];
        }

        // "make the Playoffs" — made playoffs but no further detail
        if (str_contains($lower, 'playoff')) {
            return ['round' => 'first round', 'won_championship' => 0];
        }

        // Unknown playoff result
        return ['round' => 'playoffs', 'won_championship' => 0];
    }
}
