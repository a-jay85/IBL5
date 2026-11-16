<?php

declare(strict_types=1);

namespace Tests\PlrParser;

use PlrParser\PlrFieldSerializer;
use PlrParser\PlrFileWriter;

/**
 * Synthetic .plr and depth-chart rows for the JSB export tests.
 */
final class JsbExportFixtures
{
    /**
     * Build one 607-char .plr record. Pass pid 0 for an empty slot.
     */
    public static function record(int $ordinal, int $pid, int $teamid, string $name = 'Test Player', ?int $previousTeamIndex = null): string
    {
        $record = str_repeat(' ', PlrFileWriter::PLAYER_RECORD_LENGTH);
        $record = substr_replace($record, PlrFieldSerializer::formatInt($ordinal, 4), 0, 4);
        $record = substr_replace($record, PlrFieldSerializer::formatInt($pid, 6), 38, 6);
        if ($pid === 0) {
            return $record;
        }

        $record = substr_replace($record, str_pad($name, 32), 4, 32);
        $record = substr_replace($record, PlrFieldSerializer::formatInt($teamid, 2), 44, 2);
        $record = substr_replace($record, '00000', 132, 5);
        $record = substr_replace($record, '0', 137, 1);
        $record = substr_replace($record, PlrFieldSerializer::formatInt($teamid, 2), 331, 2);
        $currentIndex = $teamid === 0 ? -1 : $teamid - 1;
        $record = substr_replace($record, PlrFieldSerializer::formatInt($currentIndex, 2), 333, 2);
        $record = substr_replace($record, PlrFieldSerializer::formatInt($previousTeamIndex ?? $currentIndex, 2), 335, 2);

        return $record;
    }

    /**
     * Build file lines: header at index 0, then ordinals 1..$teams*30 with
     * the given players and empty slots elsewhere, then the trailing segment.
     *
     * @param array<int, array{pid: int, teamid: int, name?: string, prev?: int}> $players ordinal => player
     * @return list<string>
     */
    public static function lines(int $teams, array $players): array
    {
        $lines = [self::record(0, 0, 0)];
        for ($ordinal = 1; $ordinal <= $teams * 30; $ordinal++) {
            $player = $players[$ordinal] ?? null;
            $lines[] = $player === null
                ? self::record($ordinal, 0, 0)
                : self::record($ordinal, $player['pid'], $player['teamid'], $player['name'] ?? 'Player ' . $player['pid'], $player['prev'] ?? null);
        }
        $lines[] = '';

        return $lines;
    }

    /**
     * @param array<int, array{pid: int, teamid: int, name?: string, prev?: int}> $players
     */
    public static function content(int $teams, array $players): string
    {
        return implode("\r\n", self::lines($teams, $players));
    }

    /**
     * @param array<string, int> $overrides
     * @return array{pid: int, teamid: int, dc_pg_depth: int, dc_sg_depth: int, dc_sf_depth: int, dc_pf_depth: int, dc_c_depth: int, dc_can_play_in_game: int, dc_minutes: int, dc_of: int, dc_df: int, dc_oi: int, dc_di: int, dc_bh: int}
     */
    public static function chartRow(int $pid, int $teamid, array $overrides = []): array
    {
        $row = [
            'pid' => $pid,
            'teamid' => $teamid,
            'dc_pg_depth' => 0,
            'dc_sg_depth' => 0,
            'dc_sf_depth' => 0,
            'dc_pf_depth' => 0,
            'dc_c_depth' => 0,
            'dc_can_play_in_game' => 0,
            'dc_minutes' => 0,
            'dc_of' => 0,
            'dc_df' => 0,
            'dc_oi' => 0,
            'dc_di' => 0,
            'dc_bh' => 0,
        ];
        foreach ($overrides as $key => $value) {
            $row[$key] = $value;
        }

        /** @var array{pid: int, teamid: int, dc_pg_depth: int, dc_sg_depth: int, dc_sf_depth: int, dc_pf_depth: int, dc_c_depth: int, dc_can_play_in_game: int, dc_minutes: int, dc_of: int, dc_df: int, dc_oi: int, dc_di: int, dc_bh: int} $row */
        return $row;
    }

    /**
     * A .plb of 32 team lines of 360 chars with every slot set to $slot.
     */
    public static function plb(string $slot = ' 0 0 0 0 0 0'): string
    {
        $lines = array_fill(0, 32, str_repeat($slot, 30));
        $lines = array_merge($lines, array_fill(0, 33, ''));
        $lines[] = str_repeat(' ', 100);

        return implode("\r\n", $lines);
    }
}
