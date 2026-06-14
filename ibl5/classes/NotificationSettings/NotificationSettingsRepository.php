<?php

declare(strict_types=1);

namespace NotificationSettings;

use BaseMysqliRepository;
use NotificationSettings\Contracts\NotificationPrefsRepositoryInterface;

/**
 * NotificationSettingsRepository - Database operations for GM notification preferences
 *
 * @see NotificationPrefsRepositoryInterface For method contracts
 */
class NotificationSettingsRepository extends BaseMysqliRepository implements NotificationPrefsRepositoryInterface
{
    /**
     * @see NotificationPrefsRepositoryInterface::findByUserId()
     */
    public function findByUserId(int $userId): ?array
    {
        /** @var array{notify_trade_offers: int, notify_waiver_claims: int, notify_fa_outbids: int, digest_depth_chart_reminder: int, digest_weekly_transactions: int, digest_channel_discord: int}|null $row */
        $row = $this->fetchOne(
            'SELECT notify_trade_offers, notify_waiver_claims, notify_fa_outbids,
                    digest_depth_chart_reminder, digest_weekly_transactions, digest_channel_discord
             FROM gm_notification_prefs
             WHERE user_id = ?',
            'i',
            $userId
        );

        return $row;
    }

    /**
     * @see NotificationPrefsRepositoryInterface::savePrefs()
     */
    public function savePrefs(int $userId, array $values): void
    {
        $this->execute(
            'INSERT INTO gm_notification_prefs
                 (user_id, notify_trade_offers, notify_waiver_claims, notify_fa_outbids,
                  digest_depth_chart_reminder, digest_weekly_transactions, digest_channel_discord)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 notify_trade_offers = VALUES(notify_trade_offers),
                 notify_waiver_claims = VALUES(notify_waiver_claims),
                 notify_fa_outbids = VALUES(notify_fa_outbids),
                 digest_depth_chart_reminder = VALUES(digest_depth_chart_reminder),
                 digest_weekly_transactions = VALUES(digest_weekly_transactions),
                 digest_channel_discord = VALUES(digest_channel_discord)',
            'iiiiiii',
            $userId,
            $values['notify_trade_offers'],
            $values['notify_waiver_claims'],
            $values['notify_fa_outbids'],
            $values['digest_depth_chart_reminder'],
            $values['digest_weekly_transactions'],
            $values['digest_channel_discord']
        );
    }
}
