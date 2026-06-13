<?php

declare(strict_types=1);

namespace Notifications;

/**
 * Notification type constants stored in the `gm_notifications.type` column.
 *
 * The three TRADE_* types are wired this PR; WAIVER_CLAIM and FA_OUTBID are
 * seam-ready constants for the documented follow-up that wires the waiver/FA
 * dispatch points through NotificationService::notify().
 */
final class NotificationType
{
    public const TRADE_OFFER_RECEIVED = 'TRADE_OFFER_RECEIVED';
    public const TRADE_ACCEPTED = 'TRADE_ACCEPTED';
    public const TRADE_REJECTED = 'TRADE_REJECTED';

    // Seam-ready — not dispatched this PR (see plan "Out of Scope").
    public const WAIVER_CLAIM = 'WAIVER_CLAIM';
    public const FA_OUTBID = 'FA_OUTBID';
}
