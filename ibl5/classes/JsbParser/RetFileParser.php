<?php

declare(strict_types=1);

namespace JsbParser;

use JsbParser\Contracts\RetFileParserInterface;

/**
 * Parser for JSB .ret (Retired Players) text files.
 *
 * Each line contains a player name followed by a JSB player ID as the last
 * whitespace-delimited token. Padding lines (" 0") and blank lines are skipped.
 */
class RetFileParser implements RetFileParserInterface
{
    /**
     * @see RetFileParserInterface::parse()
     */
    public static function parse(string $data): array
    {
        // Normalize line endings
        $data = str_replace("\r\n", "\n", $data);
        $data = str_replace("\r", "\n", $data);
        $lines = explode("\n", $data);

        /** @var list<array{jsb_pid: int, player_name: string}> $entries */
        $entries = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || $trimmed === '0') {
                continue;
            }

            // Last whitespace token is the jsb_pid
            $parts = preg_split('/\s+/', $trimmed);
            if ($parts === false || count($parts) < 2) {
                continue;
            }

            $lastToken = array_pop($parts);
            $jsbPid = (int) $lastToken;

            // Skip if PID is 0 or negative (padding)
            if ($jsbPid <= 0) {
                continue;
            }

            $playerName = implode(' ', $parts);
            if ($playerName === '') {
                continue;
            }

            $entries[] = [
                'jsb_pid' => $jsbPid,
                'player_name' => $playerName,
            ];
        }

        return $entries;
    }

    /**
     * @see RetFileParserInterface::parseFile()
     */
    public static function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("RET file not found: {$filePath}");
        }

        $raw = file_get_contents($filePath);
        if ($raw === false) {
            throw new \RuntimeException("Failed to read RET file: {$filePath}");
        }

        return self::parse($raw);
    }
}
