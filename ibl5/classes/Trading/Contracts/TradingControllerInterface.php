<?php

declare(strict_types=1);

namespace Trading\Contracts;

interface TradingControllerInterface
{
    public function handleTradeReview(mixed $user): void;

    public function handleTradeOffer(mixed $user, ?string $partner): void;

    public function handleRosterPreviewApi(mixed $user): void;

    /**
     * @param array<string, mixed> $post
     */
    public function submitTradeOffer(array $post): void;

    /**
     * @param array<string, mixed> $post
     */
    public function acceptTradeOffer(array $post): void;

    /**
     * @param array<string, mixed> $post
     */
    public function rejectTradeOffer(array $post): void;

    /**
     * Counter an incoming trade offer: auto-reject the original and PRG-redirect
     * to the make-offer form pre-filled with the same assets, authored by the
     * recipient. Takes the authenticated $user (not a form field) because the
     * IDOR check must trust the session identity.
     *
     * @param array<string, mixed> $post
     */
    public function counterTradeOffer(mixed $user, array $post): void;
}
