<?php

declare(strict_types=1);

namespace NotificationSettings\Contracts;

interface NotificationPrefsRepositoryInterface
{
    /**
     * Find a user's notification preference row by user ID.
     *
     * Returns the raw row with all six preference columns, or null when no row
     * exists yet (i.e. user has never saved preferences).
     *
     * Note: mysqli may return integer columns as strings; callers should cast to int.
     *
     * @see \NotificationSettings\NotificationSettingsRepository::findByUserId()
     *
     * @return array{notify_trade_offers: int, notify_waiver_claims: int, notify_fa_outbids: int, digest_depth_chart_reminder: int, digest_weekly_transactions: int, digest_channel_discord: int}|null
     */
    public function findByUserId(int $userId): ?array;

    /**
     * Upsert the full set of notification preferences for a user.
     *
     * Inserts a new row or updates the existing one via ON DUPLICATE KEY UPDATE.
     * All six preference columns must be present in $values, keyed by column name.
     *
     * @see \NotificationSettings\NotificationSettingsRepository::savePrefs()
     *
     * @param array<string, int> $values Map of column name => 0|1 for all six columns
     */
    public function savePrefs(int $userId, array $values): void;
}
