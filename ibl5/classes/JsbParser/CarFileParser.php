<?php

declare(strict_types=1);

namespace JsbParser;

use JsbParser\Contracts\CarFileParserInterface;

/**
 * Parser for JSB .car (Career Statistics) binary files.
 *
 * The .car format uses fixed-width 2,500-byte blocks. Block 0 is a header with
 * player count; blocks 1-N contain individual player career data with a 24-byte
 * player header followed by 100-byte season records (max 24 per player).
 *
 * @see /docs/JSB_FILE_FORMATS.md
 */
class CarFileParser implements CarFileParserInterface
{
    public const BLOCK_SIZE = 2500;
    public const HEADER_SIZE = 24;
    public const SEASON_RECORD_SIZE = 100;
    public const MAX_SEASONS_PER_PLAYER = 24;
    public const PLAYER_COUNT_WIDTH = 4;

    /**
     * @see CarFileParserInterface::parseFile()
     */
    public static function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("CAR file not found: {$filePath}");
        }

        $data = file_get_contents($filePath);
        if ($data === false) {
            throw new \RuntimeException("Failed to read CAR file: {$filePath}");
        }

        $fileSize = strlen($data);
        if ($fileSize < self::BLOCK_SIZE) {
            throw new \RuntimeException(
                'Invalid .car file: too small (' . $fileSize . ' bytes, minimum ' . self::BLOCK_SIZE . ')'
            );
        }

        // Block 0: header — first 4 bytes = player count (right-justified)
        $playerCount = (int) trim(substr($data, 0, self::PLAYER_COUNT_WIDTH));
        $totalBlocks = intdiv($fileSize, self::BLOCK_SIZE);

        $players = [];
        for ($i = 1; $i < $totalBlocks; $i++) {
            $blockData = substr($data, $i * self::BLOCK_SIZE, self::BLOCK_SIZE);
            $player = self::parsePlayerBlock($blockData, $i);
            if ($player !== null) {
                $players[] = $player;
            }
        }

        return [
            'player_count' => $playerCount,
            'players' => $players,
        ];
    }

    /**
     * @see CarFileParserInterface::parsePlayerBlock()
     */
    public static function parsePlayerBlock(string $data, int $blockIndex): ?array
    {
        if (strlen($data) < self::HEADER_SIZE) {
            return null;
        }

        // Check if block is empty (all spaces)
        if (trim($data) === '') {
            return null;
        }

        // Player header: 24 bytes
        $seasonCount = (int) trim(substr($data, 0, 3));
        $jsbId = (int) trim(substr($data, 3, 5));
        $name = trim(substr($data, 8, 16));

        // Skip empty blocks (no name or zero seasons)
        if ($name === '' || $seasonCount === 0) {
            return null;
        }

        $seasons = [];
        for ($s = 0; $s < $seasonCount && $s < self::MAX_SEASONS_PER_PLAYER; $s++) {
            $offset = self::HEADER_SIZE + $s * self::SEASON_RECORD_SIZE;
            if ($offset + self::SEASON_RECORD_SIZE > strlen($data)) {
                break;
            }
            $seasonData = substr($data, $offset, self::SEASON_RECORD_SIZE);
            $seasons[] = self::parseSeasonRecord($seasonData);
        }

        return [
            'block_index' => $blockIndex,
            'jsb_id' => $jsbId,
            'name' => $name,
            'season_count' => $seasonCount,
            'seasons' => $seasons,
        ];
    }

    /**
     * @see CarFileParserInterface::parseSeasonRecord()
     */
    public static function parseSeasonRecord(string $data): array
    {
        return [
            'year' => (int) trim(substr($data, 0, 4)),
            'team' => trim(substr($data, 4, 16)),
            'name' => trim(substr($data, 20, 16)),
            'position' => trim(substr($data, 36, 2)),
            'gp' => (int) trim(substr($data, 40, 2)),
            'min' => (int) trim(substr($data, 42, 4)),
            'two_gm' => (int) trim(substr($data, 46, 4)),
            'two_ga' => (int) trim(substr($data, 50, 4)),
            'ftm' => (int) trim(substr($data, 54, 4)),
            'fta' => (int) trim(substr($data, 58, 4)),
            'three_gm' => (int) trim(substr($data, 62, 4)),
            'three_ga' => (int) trim(substr($data, 66, 4)),
            'orb' => (int) trim(substr($data, 70, 4)),
            'drb' => (int) trim(substr($data, 74, 4)),
            'ast' => (int) trim(substr($data, 78, 4)),
            'stl' => (int) trim(substr($data, 82, 4)),
            'to' => (int) trim(substr($data, 86, 4)),
            'blk' => (int) trim(substr($data, 90, 4)),
            'pf' => (int) trim(substr($data, 94, 4)),
        ];
    }

    /**
     * Convert a .car season record to ibl_hist format.
     *
     * Applies the same 2GM→FGM conversion as plrParser.php:290-298:
     * - fgm = two_gm + three_gm
     * - fga = two_ga + three_ga
     * - reb = orb + drb
     * - pts = two_gm * 2 + ftm + three_gm * 3
     *
     * @param array{year: int, team: string, name: string, position: string, gp: int, min: int, two_gm: int, two_ga: int, ftm: int, fta: int, three_gm: int, three_ga: int, orb: int, drb: int, ast: int, stl: int, to: int, blk: int, pf: int} $season
     * @return array{year: int, team: string, name: string, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, blk: int, tvr: int, pf: int, pts: int}
     */
    public static function convertToHistFormat(array $season): array
    {
        $fgm = $season['two_gm'] + $season['three_gm'];
        $fga = $season['two_ga'] + $season['three_ga'];
        $reb = $season['orb'] + $season['drb'];
        $pts = $season['two_gm'] * 2 + $season['ftm'] + $season['three_gm'] * 3;

        return [
            'year' => $season['year'],
            'team' => $season['team'],
            'name' => $season['name'],
            'games' => $season['gp'],
            'minutes' => $season['min'],
            'fgm' => $fgm,
            'fga' => $fga,
            'ftm' => $season['ftm'],
            'fta' => $season['fta'],
            'tgm' => $season['three_gm'],
            'tga' => $season['three_ga'],
            'orb' => $season['orb'],
            'reb' => $reb,
            'ast' => $season['ast'],
            'stl' => $season['stl'],
            'blk' => $season['blk'],
            'tvr' => $season['to'],
            'pf' => $season['pf'],
            'pts' => $pts,
        ];
    }
}
