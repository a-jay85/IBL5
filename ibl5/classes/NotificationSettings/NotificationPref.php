<?php

declare(strict_types=1);

namespace NotificationSettings;

/**
 * Canonical notification preference names
 *
 * The backed string value doubles as the HTML form field name and DB column name,
 * keeping form submission, service logic, and persistence identical without translation.
 */
enum NotificationPref: string
{
    case TradeOffers = 'notify_trade_offers';
    case WaiverClaims = 'notify_waiver_claims';
    case FreeAgencyOutbids = 'notify_fa_outbids';
    case DepthChartReminder = 'digest_depth_chart_reminder';
    case WeeklyTransactionsDigest = 'digest_weekly_transactions';
    case DigestChannelDiscord = 'digest_channel_discord';

    /**
     * The gm_notification_prefs column this preference maps to.
     *
     * The column name is identical to the backed string value.
     */
    public function column(): string
    {
        return $this->value;
    }

    /**
     * Whether this preference is enabled by default when no row exists yet.
     *
     * Event-type preferences default on; digest preferences default off.
     */
    public function defaultEnabled(): bool
    {
        return match ($this) {
            self::TradeOffers,
            self::WaiverClaims,
            self::FreeAgencyOutbids => true,
            self::DepthChartReminder,
            self::WeeklyTransactionsDigest,
            self::DigestChannelDiscord => false,
        };
    }

    /**
     * Human-readable label for display in the preferences form.
     */
    public function label(): string
    {
        return match ($this) {
            self::TradeOffers => 'Trade offers',
            self::WaiverClaims => 'Waiver claims',
            self::FreeAgencyOutbids => 'Free agency outbids',
            self::DepthChartReminder => 'Depth chart reminder (before next sim)',
            self::WeeklyTransactionsDigest => 'Weekly transactions digest',
            self::DigestChannelDiscord => 'Deliver digests via Discord',
        };
    }
}
