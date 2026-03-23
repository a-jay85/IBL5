<?php

declare(strict_types=1);

use Migration\SchemaAssertion;

/**
 * Schema assertions: critical columns that must exist in the database.
 *
 * Convention: When writing a migration that renames or drops a column that
 * PHP code depends on, add a SchemaAssertion here for the destination column.
 * The post-migration validator will catch silent no-ops (e.g., IF EXISTS guards
 * that swallowed a failed column rename).
 *
 * For new migrations: drop `IF EXISTS` from CHANGE COLUMN — let renames fail
 * loudly if the source column is missing. `IF NOT EXISTS` on CREATE TABLE and
 * ADD COLUMN remains fine (truly idempotent).
 *
 * @return list<SchemaAssertion>
 */
return [
    // Migration 062: dc_active → dc_canPlayInGame (caused production outage)
    new SchemaAssertion('ibl_plr', 'dc_canPlayInGame'),
    new SchemaAssertion('ibl_saved_depth_chart_players', 'dc_canPlayInGame'),
    new SchemaAssertion('ibl_olympics_saved_depth_chart_players', 'dc_canPlayInGame'),

    // Migration 059: reserved-word renames (from → trade_from, to → trade_to)
    new SchemaAssertion('ibl_trade_info', 'trade_from'),
    new SchemaAssertion('ibl_trade_info', 'trade_to'),

    // High-usage baseline columns (referenced by many repositories)
    new SchemaAssertion('ibl_plr', 'pid'),
    new SchemaAssertion('ibl_plr', 'tid'),
    new SchemaAssertion('ibl_plr', 'name'),
    new SchemaAssertion('ibl_team_info', 'teamid'),
    new SchemaAssertion('ibl_team_info', 'team_name'),
    new SchemaAssertion('ibl_team_info', 'gm_username'),
    new SchemaAssertion('ibl_settings', 'name'),
    new SchemaAssertion('ibl_settings', 'value'),
];
