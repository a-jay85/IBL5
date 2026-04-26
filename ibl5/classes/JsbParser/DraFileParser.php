<?php

declare(strict_types=1);

namespace JsbParser;

use JsbParser\Contracts\DraFileParserInterface;

/**
 * Parser for JSB .dra (Draft Results) text files.
 *
 * Parses cumulative draft files containing multiple seasons of draft results
 * with year headers, round/pick markers, and team:position player entries.
 */
class DraFileParser implements DraFileParserInterface
{
    /**
     * @see DraFileParserInterface::parse()
     */
    public static function parse(string $data): array
    {
        // Normalize line endings
        $data = str_replace("\r\n", "\n", $data);
        $data = str_replace("\r", "\n", $data);
        $lines = explode("\n", $data);

        /** @var list<array{draft_year: int, picks: list<array{round: int, pick: int, team_name: string, pos: string, player_name: string}>}> $drafts */
        $drafts = [];

        $currentYear = null;
        /** @var list<array{round: int, pick: int, team_name: string, pos: string, player_name: string}> $currentPicks */
        $currentPicks = [];
        $currentRound = 0;
        $currentPick = 0;
        $expectPickLine = false;

        foreach ($lines as $line) {
            // Strip null bytes that appear in padding
            $line = str_replace("\0", '', $line);
            $trimmed = trim($line);

            if ($trimmed === '') {
                $expectPickLine = false;
                continue;
            }

            // Check for year header: ***** {year} rookie draft *****
            if (preg_match('/\*{5}\s+(\d{4})\s+rookie\s+draft\s+\*{5}/', $trimmed, $yearMatch) === 1) {
                // Save previous draft if any
                if ($currentYear !== null && $currentPicks !== []) {
                    $drafts[] = ['draft_year' => $currentYear, 'picks' => $currentPicks];
                }
                $currentYear = (int) $yearMatch[1];
                $currentPicks = [];
                $currentRound = 0;
                $currentPick = 0;
                $expectPickLine = false;
                continue;
            }

            // Skip if no year context yet
            if ($currentYear === null) {
                continue;
            }

            // Check for round/pick header: "Round {R} Pick {P}"
            if (preg_match('/^Round\s+(\d+)\s+Pick\s+(\d+)$/', $trimmed, $pickMatch) === 1) {
                $currentRound = (int) $pickMatch[1];
                $currentPick = (int) $pickMatch[2];
                $expectPickLine = true;
                continue;
            }

            // Skip end-of-round/draft markers
            if (str_starts_with($trimmed, '* End of')) {
                $expectPickLine = false;
                continue;
            }

            // Skip lottery odds lines (contain "chances")
            if (str_contains($trimmed, 'chances')) {
                continue;
            }

            // Parse pick line: "{Team}: {pos} {PlayerName}"
            if ($expectPickLine && $currentRound > 0 && str_contains($trimmed, ':')) {
                $colonPos = strpos($trimmed, ':');
                if ($colonPos !== false) {
                    $teamName = trim(substr($trimmed, 0, $colonPos));
                    $remainder = substr($trimmed, $colonPos + 1);

                    // Position is 2 chars after colon+space, e.g. " C " or "PF "
                    if (strlen($remainder) >= 3) {
                        // The format is ": {pos} {name}" where pos is up to 2 chars
                        // Trim leading space, then extract position
                        $remainder = ltrim($remainder);
                        $pos = trim(substr($remainder, 0, 2));
                        $playerName = trim(substr($remainder, 2));

                        // Convert CP1252 to UTF-8
                        $playerName = mb_convert_encoding($playerName, 'UTF-8', 'Windows-1252');

                        $currentPicks[] = [
                            'round' => $currentRound,
                            'pick' => $currentPick,
                            'team_name' => $teamName,
                            'pos' => $pos,
                            'player_name' => $playerName,
                        ];
                    }
                }
                $expectPickLine = false;
            }
        }

        // Save the last draft
        if ($currentYear !== null && $currentPicks !== []) {
            $drafts[] = ['draft_year' => $currentYear, 'picks' => $currentPicks];
        }

        return $drafts;
    }

    /**
     * @see DraFileParserInterface::parseFile()
     */
    public static function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("DRA file not found: {$filePath}");
        }

        $raw = file_get_contents($filePath);
        if ($raw === false) {
            throw new \RuntimeException("Failed to read DRA file: {$filePath}");
        }

        return self::parse($raw);
    }
}
