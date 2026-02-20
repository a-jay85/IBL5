<?php

declare(strict_types=1);

namespace JsbParser\Contracts;

/**
 * Interface for parsing JSB .car (Career Statistics) binary files.
 *
 * The .car format uses fixed-width 2,500-byte blocks containing player career data.
 * Block 0 is a header with player count; blocks 1-N contain individual player records.
 */
interface CarFileParserInterface
{
    /**
     * Parse a complete .car file.
     *
     * @param string $filePath Path to the .car file
     * @return array{player_count: int, players: list<array{block_index: int, jsb_id: int, name: string, season_count: int, seasons: list<array{year: int, team: string, name: string, position: string, gp: int, min: int, two_gm: int, two_ga: int, ftm: int, fta: int, three_gm: int, three_ga: int, orb: int, drb: int, ast: int, stl: int, to: int, blk: int, pf: int}>}>}
     * @throws \RuntimeException If file cannot be read or has invalid structure
     */
    public static function parseFile(string $filePath): array;

    /**
     * Parse a single 2,500-byte player block.
     *
     * @param string $data Raw binary data (2,500 bytes)
     * @param int $blockIndex Position in the file (used as player reference in .rcb)
     * @return array{block_index: int, jsb_id: int, name: string, season_count: int, seasons: list<array{year: int, team: string, name: string, position: string, gp: int, min: int, two_gm: int, two_ga: int, ftm: int, fta: int, three_gm: int, three_ga: int, orb: int, drb: int, ast: int, stl: int, to: int, blk: int, pf: int}>}|null
     */
    public static function parsePlayerBlock(string $data, int $blockIndex): ?array;

    /**
     * Parse a single 100-byte season record within a player block.
     *
     * @param string $data Raw binary data (100 bytes)
     * @return array{year: int, team: string, name: string, position: string, gp: int, min: int, two_gm: int, two_ga: int, ftm: int, fta: int, three_gm: int, three_ga: int, orb: int, drb: int, ast: int, stl: int, to: int, blk: int, pf: int}
     */
    public static function parseSeasonRecord(string $data): array;
}
