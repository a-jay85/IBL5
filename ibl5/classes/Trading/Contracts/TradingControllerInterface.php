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
     * @param mixed $user The PHP-Nuke auth marker for the isUser() gate; identity is read from the authenticated session, never POST
     * @param array<string, mixed> $post
     */
    public function acceptTradeOffer(mixed $user, array $post): void;

    /**
     * @param mixed $user The PHP-Nuke auth marker for the isUser() gate; identity is read from the authenticated session, never POST
     * @param array<string, mixed> $post
     */
    public function rejectTradeOffer(mixed $user, array $post): void;
}
