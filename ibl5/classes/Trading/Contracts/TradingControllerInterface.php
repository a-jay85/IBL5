<?php

declare(strict_types=1);

namespace Trading\Contracts;

interface TradingControllerInterface
{
    public function handleTradeReview(mixed $user): void;

    public function handleTradeOffer(mixed $user, ?string $partner): void;

    public function handleRosterPreviewApi(mixed $user): void;

    /**
     * Submit a new trade offer. Takes the authenticated $user (not a form field)
     * because the offering team must be bound to the session identity — the posted
     * offeringTeam is audit-logged but never trusted (IDOR D-03).
     *
     * @param array<string, mixed> $post
     */
    public function submitTradeOffer(mixed $user, array $post): void;

    /**
     * @param array<string, mixed> $post
     */
    public function acceptTradeOffer(array $post): void;

    /**
     * @param array<string, mixed> $post
     */
    public function rejectTradeOffer(array $post): void;
}
