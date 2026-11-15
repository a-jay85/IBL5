<?php

declare(strict_types=1);

namespace DataRetention;

/**
 * Every row an account owns or names, in the order an account delete writes them.
 *
 * Each case carries literal SQL with exactly one `?` placeholder, so no identifier is
 * built from a string at runtime. `AuthUsers` is last: the account row goes only after
 * every row that points at it.
 */
enum AccountFootprintTarget: string
{
    case Confirmations = 'auth_users_confirmations';
    case Otps = 'auth_users_otps';
    case TwoFactor = 'auth_users_2fa';
    case Remembered = 'auth_users_remembered';
    case Resets = 'auth_users_resets';
    case AuditLogUser = 'auth_users_audit_log';
    case AuditLogAdmin = 'auth_users_audit_log.admin_id';
    case ApiKeys = 'ibl_api_keys';
    case GoogleSheetConnections = 'ibl_google_sheet_connections';
    case EventsUsername = 'ibl_events';
    case TeamGmUsername = 'ibl_team_info';
    case AuthUsers = 'auth_users';

    /**
     * `SELECT COUNT(*) AS n` over the rows this target would write.
     */
    public function countSql(): string
    {
        // gm_username exists only on the IBL team table. The CLI never sets an Olympics
        // league context, so BaseMysqliRepository::rewriteTableNames() leaves these names alone.
        return match ($this) {
            self::Confirmations => 'SELECT COUNT(*) AS n FROM `auth_users_confirmations` WHERE `user_id` = ?',
            self::Otps => 'SELECT COUNT(*) AS n FROM `auth_users_otps` WHERE `user_id` = ?',
            self::TwoFactor => 'SELECT COUNT(*) AS n FROM `auth_users_2fa` WHERE `user_id` = ?',
            self::Remembered => 'SELECT COUNT(*) AS n FROM `auth_users_remembered` WHERE `user` = ?',
            self::Resets => 'SELECT COUNT(*) AS n FROM `auth_users_resets` WHERE `user` = ?',
            self::AuditLogUser => 'SELECT COUNT(*) AS n FROM `auth_users_audit_log` WHERE `user_id` = ?',
            self::AuditLogAdmin => 'SELECT COUNT(*) AS n FROM `auth_users_audit_log` WHERE `admin_id` = ?',
            self::ApiKeys => 'SELECT COUNT(*) AS n FROM `ibl_api_keys` WHERE `user_id` = ?',
            self::GoogleSheetConnections => 'SELECT COUNT(*) AS n FROM `ibl_google_sheet_connections` WHERE `user_id` = ?',
            self::EventsUsername => 'SELECT COUNT(*) AS n FROM `ibl_events` WHERE `username` = ?',
            self::TeamGmUsername => 'SELECT COUNT(*) AS n FROM `ibl_team_info` WHERE `gm_username` = ?',
            self::AuthUsers => 'SELECT COUNT(*) AS n FROM `auth_users` WHERE `id` = ?',
        };
    }

    /**
     * The DELETE or unlinking UPDATE for this target.
     */
    public function writeSql(): string
    {
        return match ($this) {
            self::Confirmations => 'DELETE FROM `auth_users_confirmations` WHERE `user_id` = ?',
            self::Otps => 'DELETE FROM `auth_users_otps` WHERE `user_id` = ?',
            self::TwoFactor => 'DELETE FROM `auth_users_2fa` WHERE `user_id` = ?',
            self::Remembered => 'DELETE FROM `auth_users_remembered` WHERE `user` = ?',
            self::Resets => 'DELETE FROM `auth_users_resets` WHERE `user` = ?',
            self::AuditLogUser => 'DELETE FROM `auth_users_audit_log` WHERE `user_id` = ?',
            self::AuditLogAdmin => 'UPDATE `auth_users_audit_log` SET `admin_id` = NULL WHERE `admin_id` = ?',
            self::ApiKeys => 'DELETE FROM `ibl_api_keys` WHERE `user_id` = ?',
            self::GoogleSheetConnections => 'DELETE FROM `ibl_google_sheet_connections` WHERE `user_id` = ?',
            self::EventsUsername => 'UPDATE `ibl_events` SET `username` = NULL WHERE `username` = ?',
            self::TeamGmUsername => 'UPDATE `ibl_team_info` SET `gm_username` = NULL WHERE `gm_username` = ?',
            self::AuthUsers => 'DELETE FROM `auth_users` WHERE `id` = ?',
        };
    }

    /**
     * True when the placeholder takes the username; false when it takes the user id.
     */
    public function bindsUsername(): bool
    {
        return $this === self::EventsUsername || $this === self::TeamGmUsername;
    }
}
