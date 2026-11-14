<?php

declare(strict_types=1);

namespace DataRetention;

/**
 * Every table an account leaves personal data in, in write order.
 *
 * Each statement is a literal keyed by exactly one placeholder. `AuthUsers` is last,
 * so the rows keyed to the account go before the account itself.
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
    case EventsUsername = 'ibl_events';
    case TeamGmUsername = 'ibl_team_info';
    case AuthUsers = 'auth_users';

    /**
     * Count the rows this target would change.
     */
    public function countSql(): string
    {
        return match ($this) {
            self::Confirmations => 'SELECT COUNT(*) AS n FROM `auth_users_confirmations` WHERE `user_id` = ?',
            self::Otps => 'SELECT COUNT(*) AS n FROM `auth_users_otps` WHERE `user_id` = ?',
            self::TwoFactor => 'SELECT COUNT(*) AS n FROM `auth_users_2fa` WHERE `user_id` = ?',
            self::Remembered => 'SELECT COUNT(*) AS n FROM `auth_users_remembered` WHERE `user` = ?',
            self::Resets => 'SELECT COUNT(*) AS n FROM `auth_users_resets` WHERE `user` = ?',
            self::AuditLogUser => 'SELECT COUNT(*) AS n FROM `auth_users_audit_log` WHERE `user_id` = ?',
            self::AuditLogAdmin => 'SELECT COUNT(*) AS n FROM `auth_users_audit_log` WHERE `admin_id` = ?',
            self::ApiKeys => 'SELECT COUNT(*) AS n FROM `ibl_api_keys` WHERE `user_id` = ?',
            self::EventsUsername => 'SELECT COUNT(*) AS n FROM `ibl_events` WHERE `username` = ?',
            self::TeamGmUsername => 'SELECT COUNT(*) AS n FROM `ibl_team_info` WHERE `gm_username` = ?',
            self::AuthUsers => 'SELECT COUNT(*) AS n FROM `auth_users` WHERE `id` = ?',
        };
    }

    /**
     * Delete the rows, or unlink the account from rows that stay.
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
