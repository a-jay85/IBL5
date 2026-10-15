<?php

declare(strict_types=1);

namespace JsbParser;

use JsbParser\Contracts\DraFileParserInterface;
use PlrParser\PlrFieldSerializer;

/**
 * Parser for JSB .dra (Draft Results) text files.
 *
 * Parses cumulative draft files containing multiple seasons of draft results
 * with year headers, round/pick markers, and team:position player entries.
 *
 * @phpstan-type DraPick array{round: int, pick: int, team_name: string, pos: string, player_name: string}
 * @phpstan-type DraParseState array{drafts: list<array{draft_year: int, picks: list<DraPick>}>, year: int|null, picks: list<DraPick>, round: int, pick: int, expectPickLine: bool}
 */
class DraFileParser implements DraFileParserInterface
{
    /**
     * @see DraFileParserInterface::parse()
     */
    public static function parse(string $data): array
    {
        $state = self::initialState();
        foreach (self::splitLines($data) as $line) {
            $state = self::consumeLine($state, $line);
        }

        return self::flushDraft($state['drafts'], $state['year'], $state['picks']);
    }

    /**
     * Normalize CRLF, then lone CR, to LF and split into lines.
     *
     * @return list<string>
     */
    private static function splitLines(string $data): array
    {
        $data = str_replace("\r\n", "\n", $data);
        $data = str_replace("\r", "\n", $data);

        return explode("\n", $data);
    }

    /**
     * @return DraParseState
     */
    private static function initialState(): array
    {
        /** @infection-ignore-all Equivalent: lines before the first year header are skipped and a pick needs a Round header first, so round, pick and expectPickLine are always overwritten before they are read. */
        return ['drafts' => [], 'year' => null, 'picks' => [], 'round' => 0, 'pick' => 0, 'expectPickLine' => false];
    }

    /**
     * Apply one raw line to the parse state. Branch order is load-bearing.
     *
     * @param DraParseState $state
     * @return DraParseState
     */
    private static function consumeLine(array $state, string $line): array
    {
        // Strip null bytes that appear in padding
        $trimmed = trim(str_replace("\0", '', $line));

        if ($trimmed === '') {
            $state['expectPickLine'] = false;
            return $state;
        }

        $year = self::parseYearHeader($trimmed);
        if ($year !== null) {
            $state['drafts'] = self::flushDraft($state['drafts'], $state['year'], $state['picks']);
            $state['year'] = $year;
            $state['picks'] = [];
            $state['expectPickLine'] = false;
            return $state;
        }

        // Skip if no year context yet
        if ($state['year'] === null) {
            return $state;
        }

        // Check for round/pick header: "Round {R} Pick {P}"
        if (preg_match('/^Round\s+(\d+)\s+Pick\s+(\d+)$/', $trimmed, $pickMatch) === 1) {
            $state['round'] = (int) $pickMatch[1];
            $state['pick'] = (int) $pickMatch[2];
            $state['expectPickLine'] = true;
            return $state;
        }

        // Skip end-of-round/draft markers
        if (str_starts_with($trimmed, '* End of')) {
            $state['expectPickLine'] = false;
            return $state;
        }

        // Skip lottery odds lines (contain "chances"); they do not clear expectPickLine
        if (str_contains($trimmed, 'chances')) {
            return $state;
        }

        // One attempt per pick header, even when the line yields no pick
        if ($state['expectPickLine'] && $state['round'] > 0 && str_contains($trimmed, ':')) {
            $pick = self::parsePickLine($trimmed, $state['round'], $state['pick']);
            if ($pick !== null) {
                $state['picks'][] = $pick;
            }
            $state['expectPickLine'] = false;
        }

        return $state;
    }

    /**
     * Match "***** {year} rookie draft *****" and return the year.
     */
    private static function parseYearHeader(string $trimmed): ?int
    {
        if (preg_match('/\*{5}\s+(\d{4})\s+rookie\s+draft\s+\*{5}/', $trimmed, $yearMatch) !== 1) {
            return null;
        }

        return (int) $yearMatch[1];
    }

    /**
     * Parse "{Team}: {pos} {PlayerName}". Returns null when the text after the colon is under 3 bytes.
     *
     * @return DraPick|null
     */
    private static function parsePickLine(string $trimmed, int $round, int $pick): ?array
    {
        $colonPos = strpos($trimmed, ':');
        if ($colonPos === false) {
            return null;
        }

        $teamName = trim(substr($trimmed, 0, $colonPos));
        $remainder = substr($trimmed, $colonPos + 1);

        // Position is 2 chars after colon+space, e.g. " C " or "PF "
        if (strlen($remainder) < 3) {
            return null;
        }

        // Trim leading space, then extract position (up to 2 chars) and name
        $remainder = ltrim($remainder);

        return [
            'round' => $round,
            'pick' => $pick,
            'team_name' => $teamName,
            'pos' => trim(substr($remainder, 0, 2)),
            // Convert CP1252 to UTF-8
            'player_name' => PlrFieldSerializer::toUtf8(trim(substr($remainder, 2))),
        ];
    }

    /**
     * Append the current year to drafts when it has at least one pick. A year with zero picks is dropped.
     *
     * @param list<array{draft_year: int, picks: list<DraPick>}> $drafts
     * @param list<DraPick> $picks
     * @return list<array{draft_year: int, picks: list<DraPick>}>
     */
    private static function flushDraft(array $drafts, ?int $year, array $picks): array
    {
        if ($year !== null && $picks !== []) {
            $drafts[] = ['draft_year' => $year, 'picks' => $picks];
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
