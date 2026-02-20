<?php

declare(strict_types=1);

namespace JsbParser\Contracts;

/**
 * Interface for parsing JSB .trn (Transactions) binary files.
 *
 * The .trn format uses fixed-width 128-byte records for injuries, trades, and waiver actions.
 */
interface TrnFileParserInterface
{
    /**
     * Parse a complete .trn file.
     *
     * @param string $filePath Path to the .trn file
     * @return array{record_count: int, transactions: list<array{index: int, month: int, day: int, year: int, type: int, pid: int|null, team_id: int|null, games_missed: int|null, injury_description: string|null, trade_items: list<array{marker: int, from_team: int, to_team: int, player_id: int|null, draft_year: int|null}>|null}>}
     * @throws \RuntimeException If file cannot be read or has invalid structure
     */
    public static function parseFile(string $filePath): array;

    /**
     * Parse a single 128-byte transaction record.
     *
     * @param string $data Raw binary data (128 bytes)
     * @param int $index Record index in the file
     * @return array{index: int, month: int, day: int, year: int, type: int, pid: int|null, team_id: int|null, games_missed: int|null, injury_description: string|null, trade_items: list<array{marker: int, from_team: int, to_team: int, player_id: int|null, draft_year: int|null}>|null}|null Null if record is empty
     */
    public static function parseRecord(string $data, int $index): ?array;
}
