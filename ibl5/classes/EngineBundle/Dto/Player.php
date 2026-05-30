<?php

declare(strict_types=1);

namespace EngineBundle\Dto;

/**
 * One player in the engine input bundle.
 *
 * The field names in {@see Player::FIELDS} are the SINGLE SOURCE OF TRUTH for
 * the player contract: they are simultaneously (a) the `ibl_plr` column names,
 * (b) the JSON keys the Go engine decodes (see engine/internal/bundle/bundle.go),
 * and (c) the columns the repository SELECTs. They match 1:1 — no translation —
 * which is the whole point of PR2's contract design.
 *
 * Every field is an int except `name`. Narrowing in {@see Player::fromRow()}
 * guarantees ints serialize as JSON numbers (the Go struct expects numbers).
 */
final class Player
{
    /**
     * The 44 contract fields, in Go-struct order. Column name == JSON key.
     * (3 identity + 8 ODPT + 13 main ratings + 8 attributes + 12 depth.)
     *
     * @var list<string>
     */
    public const FIELDS = [
        'pid', 'name', 'teamid',
        // ODPT ratings (1-9)
        'oo', 'od', 'r_drive_off', 'dd', 'po', 'pd', 'r_trans_off', 'td',
        // Main ratings (0-99)
        'r_fga', 'r_fgp', 'r_fta', 'r_ftp', 'r_3ga', 'r_3gp',
        'r_orb', 'r_drb', 'r_ast', 'r_stl', 'r_tvr', 'r_blk', 'r_foul',
        // Attributes
        'age', 'stamina', 'clutch', 'consistency', 'talent', 'skill', 'intangibles', 'peak',
        // Depth-chart settings
        'dc_minutes', 'dc_pg_depth', 'dc_sg_depth', 'dc_sf_depth', 'dc_pf_depth', 'dc_c_depth',
        'dc_can_play_in_game', 'dc_of', 'dc_df', 'dc_oi', 'dc_di', 'dc_bh',
    ];

    /** The only non-integer field. */
    private const STRING_FIELDS = ['name'];

    /**
     * @param array<string, int|string> $fields keyed by {@see Player::FIELDS}; ints for all but `name`
     */
    public function __construct(public readonly array $fields)
    {
    }

    /**
     * Build a Player from a raw `ibl_plr` row, narrowing each contract field to
     * its proper scalar type (mixed → int/string) so the JSON contract holds.
     * Missing columns default to 0 / '' — but the SELECT lists every field, so
     * this only guards against a NULL value.
     *
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $fields = [];
        foreach (self::FIELDS as $field) {
            $value = $row[$field] ?? null;
            if (in_array($field, self::STRING_FIELDS, true)) {
                $fields[$field] = is_string($value) ? $value : '';
            } else {
                $fields[$field] = is_int($value) ? $value : 0;
            }
        }

        return new self($fields);
    }
}
