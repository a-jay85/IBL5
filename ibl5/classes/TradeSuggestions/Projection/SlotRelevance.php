<?php

declare(strict_types=1);

namespace TradeSuggestions\Projection;

/**
 * The ratings that matter at each lineup slot (F3).
 *
 * Ball-handling slots weigh passing, shooting, drives and perimeter defence.
 * Big slots weigh post play, rebounding, blocks and interior defence. Every
 * list has at least 11 keys so the 60% vote share has a real denominator.
 */
final class SlotRelevance
{
    /** @var array<string, list<string>> */
    public const array MAP = [
        'PG' => ['r_ast', 'r_tvr', 'r_stl', 'r_fgp', 'r_3gp', 'r_3ga', 'r_ftp', 'oo', 'od', 'r_drive_off', 'dd', 'r_trans_off', 'td'],
        'SG' => ['r_fga', 'r_fgp', 'r_3gp', 'r_3ga', 'r_ftp', 'r_stl', 'r_tvr', 'oo', 'od', 'r_drive_off', 'dd', 'r_trans_off', 'td'],
        'SF' => ['r_fga', 'r_fgp', 'r_3gp', 'r_ftp', 'r_drb', 'r_stl', 'r_blk', 'oo', 'od', 'po', 'pd', 'r_drive_off', 'dd', 'r_trans_off', 'td'],
        'PF' => ['r_fgp', 'r_fta', 'r_ftp', 'r_orb', 'r_drb', 'r_blk', 'r_foul', 'po', 'pd', 'oo', 'od', 'dd', 'td'],
        'C' => ['r_fgp', 'r_fta', 'r_orb', 'r_drb', 'r_blk', 'r_foul', 'r_tvr', 'po', 'pd', 'dd', 'td'],
    ];

    /**
     * @return list<string>
     */
    public static function keysFor(string $slot): array
    {
        if (!array_key_exists($slot, self::MAP)) {
            throw new \InvalidArgumentException("Unknown slot: {$slot}");
        }

        return self::MAP[$slot];
    }
}
