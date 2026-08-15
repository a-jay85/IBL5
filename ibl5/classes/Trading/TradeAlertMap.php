<?php

declare(strict_types=1);

namespace Trading;

final class TradeAlertMap
{
    /**
     * Result-code → alert config map shared by the trade-offer form and the
     * trade-review page (both render the same set of trade outcome banners).
     *
     * @var array<string, array{class: string, message: string}>
     */
    public const MAP = [
        'offer_sent' => ['class' => 'ibl-alert--success', 'message' => 'Trade offer sent!'],
        'trade_accepted' => ['class' => 'ibl-alert--success', 'message' => 'Trade accepted!'],
        'trade_rejected' => ['class' => 'ibl-alert--info', 'message' => 'Trade offer rejected.'],
        'accept_error' => ['class' => 'ibl-alert--error', 'message' => 'Error processing trade.'],
        'already_processed' => ['class' => 'ibl-alert--warning', 'message' => 'This trade has already been accepted, declined, or withdrawn.'],
    ];
}
