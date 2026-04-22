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
    new SchemaAssertion('ibl_plr', 'teamid'),
    new SchemaAssertion('ibl_plr', 'name'),
    new SchemaAssertion('ibl_team_info', 'teamid'),
    new SchemaAssertion('ibl_team_info', 'team_name'),
    new SchemaAssertion('ibl_team_info', 'gm_username'),
    new SchemaAssertion('ibl_settings', 'name'),
    new SchemaAssertion('ibl_settings', 'value'),

    // Migration 099: gm_username → gm_display_name (column stores display names, not usernames)
    new SchemaAssertion('ibl_gm_tenures', 'gm_display_name'),

    // Migration 113: reserved-word + r_to meaning-flip renames.
    // `to`/`do` were SQL reserved words (transition and drive offense ratings).
    // `r_to` previously meant turnover rating in live/snapshot tables but
    // transition-offense rating in ibl_hist (silently re-aliased by the view).
    // After the rename: r_trans_off = transition offense rating, r_drive_off =
    // drive offense rating, r_tvr = turnover rating — uniform across all layers.
    new SchemaAssertion('ibl_plr', 'r_trans_off'),
    new SchemaAssertion('ibl_plr', 'r_drive_off'),
    new SchemaAssertion('ibl_plr', 'r_tvr'),
    new SchemaAssertion('ibl_plr_snapshots', 'r_trans_off'),
    new SchemaAssertion('ibl_plr_snapshots', 'r_drive_off'),
    new SchemaAssertion('ibl_plr_snapshots', 'r_tvr'),
    new SchemaAssertion('ibl_olympics_plr', 'r_trans_off'),
    new SchemaAssertion('ibl_olympics_plr', 'r_drive_off'),
    new SchemaAssertion('ibl_olympics_plr', 'r_tvr'),
    new SchemaAssertion('ibl_draft_class', 'r_trans_off'),
    new SchemaAssertion('ibl_draft_class', 'r_drive_off'),
    new SchemaAssertion('ibl_hist', 'r_trans_off'),
    new SchemaAssertion('ibl_hist', 'r_drive_off'),
    new SchemaAssertion('ibl_olympics_hist', 'r_trans_off'),
    new SchemaAssertion('ibl_olympics_hist', 'r_drive_off'),
    new SchemaAssertion('ibl_sim_dates', 'start_date'),
    new SchemaAssertion('ibl_sim_dates', 'end_date'),

    // Migration 114: Tier 2 cross-table column-naming unification.
    // Turnovers (live layer): stats_to → stats_tvr
    new SchemaAssertion('ibl_plr', 'stats_tvr'),
    new SchemaAssertion('ibl_plr_snapshots', 'stats_tvr'),
    new SchemaAssertion('ibl_olympics_plr', 'stats_tvr'),
    // 3-pointer ratings: r_tga/r_tgp (live + olympics_plr) and bare tga/tgp
    // (ibl_draft_class) → r_3ga/r_3gp, matching ibl_hist canonical.
    new SchemaAssertion('ibl_plr', 'r_3ga'),
    new SchemaAssertion('ibl_plr', 'r_3gp'),
    new SchemaAssertion('ibl_plr_snapshots', 'r_3ga'),
    new SchemaAssertion('ibl_plr_snapshots', 'r_3gp'),
    new SchemaAssertion('ibl_olympics_plr', 'r_3ga'),
    new SchemaAssertion('ibl_olympics_plr', 'r_3gp'),
    new SchemaAssertion('ibl_draft_class', 'r_3ga'),
    new SchemaAssertion('ibl_draft_class', 'r_3gp'),
    // Team-id unified to lowercase `teamid` (or {prefix}_teamid for compounds).
    new SchemaAssertion('ibl_olympics_plr', 'teamid'),
    new SchemaAssertion('ibl_plr_snapshots', 'teamid'),
    new SchemaAssertion('ibl_draft', 'teamid'),
    new SchemaAssertion('ibl_fa_offers', 'teamid'),
    new SchemaAssertion('ibl_cash_considerations', 'teamid'),
    new SchemaAssertion('ibl_cash_considerations', 'counterparty_teamid'),
    new SchemaAssertion('ibl_standings', 'teamid'),
    new SchemaAssertion('ibl_olympics_standings', 'teamid'),
    new SchemaAssertion('ibl_saved_depth_charts', 'teamid'),
    new SchemaAssertion('ibl_olympics_saved_depth_charts', 'teamid'),
    new SchemaAssertion('ibl_plb_snapshots', 'teamid'),
    new SchemaAssertion('ibl_power', 'teamid'),
    new SchemaAssertion('ibl_olympics_power', 'teamid'),
    new SchemaAssertion('ibl_rcb_alltime_records', 'teamid'),
    new SchemaAssertion('ibl_rcb_season_records', 'teamid'),
    new SchemaAssertion('ibl_box_scores', 'teamid'),
    new SchemaAssertion('ibl_box_scores', 'home_teamid'),
    new SchemaAssertion('ibl_box_scores', 'visitor_teamid'),
    new SchemaAssertion('ibl_box_scores_teams', 'home_teamid'),
    new SchemaAssertion('ibl_box_scores_teams', 'visitor_teamid'),
    new SchemaAssertion('ibl_olympics_box_scores', 'teamid'),
    new SchemaAssertion('ibl_olympics_box_scores', 'home_teamid'),
    new SchemaAssertion('ibl_olympics_box_scores', 'visitor_teamid'),
    new SchemaAssertion('ibl_olympics_box_scores_teams', 'home_teamid'),
    new SchemaAssertion('ibl_olympics_box_scores_teams', 'visitor_teamid'),
    new SchemaAssertion('ibl_draft_picks', 'owner_teamid'),
    new SchemaAssertion('ibl_draft_picks', 'teampick_teamid'),
];
