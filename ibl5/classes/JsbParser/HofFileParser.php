<?php

declare(strict_types=1);

namespace JsbParser;

use JsbParser\Contracts\HofFileParserInterface;

/**
 * Parser for JSB .hof (Hall of Fame) fixed-size files.
 *
 * The file is exactly 7000 bytes: 14 × 500-byte year blocks.
 * Each entry within a block is 29 content bytes + CRLF (31 bytes total).
 * Format: "{pos:2} {name:variable}{pid:variable} {year:4} "
 * Parse from right: year = last 5 chars (4 digits + space), pid = preceding token.
 */
class HofFileParser implements HofFileParserInterface
{
    private const FILE_SIZE = 7000;
    private const BLOCK_SIZE = 500;

    /**
     * @see HofFileParserInterface::parse()
     */
    public static function parse(string $data): array
    {
        $dataSize = strlen($data);
        if ($dataSize !== self::FILE_SIZE) {
            throw new \RuntimeException(
                "HOF data size mismatch: expected " . self::FILE_SIZE . " bytes, got {$dataSize}"
            );
        }

        /** @var list<array{jsb_pid: int, player_name: string, pos: string, induction_year: int}> $entries */
        $entries = [];

        // Process each 500-byte block
        for ($block = 0; $block < 14; $block++) {
            $blockData = substr($data, $block * self::BLOCK_SIZE, self::BLOCK_SIZE);

            // Split block by CRLF to get individual entries
            $lines = explode("\r\n", $blockData);

            foreach ($lines as $line) {
                $trimmed = rtrim($line);
                if (trim($trimmed) === '') {
                    continue;
                }

                // Parse: "{pos:2} {name}{pid} {year:4} "
                // Use regex to extract: pos (2 chars), name, pid, year
                if (preg_match('/^([A-Z ]{2}) (.+?)\s+(\d+)\s+(\d{4})\s*$/', $trimmed, $match) === 1) {
                    $pos = trim($match[1]);
                    $playerName = trim($match[2]);
                    $jsbPid = (int) $match[3];
                    $inductionYear = (int) $match[4];

                    if ($jsbPid > 0 && $playerName !== '') {
                        $entries[] = [
                            'jsb_pid' => $jsbPid,
                            'player_name' => $playerName,
                            'pos' => $pos,
                            'induction_year' => $inductionYear,
                        ];
                    }
                }
            }
        }

        return $entries;
    }

    /**
     * @see HofFileParserInterface::parseFile()
     */
    public static function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("HOF file not found: {$filePath}");
        }

        $raw = file_get_contents($filePath);
        if ($raw === false) {
            throw new \RuntimeException("Failed to read HOF file: {$filePath}");
        }

        return self::parse($raw);
    }
}
