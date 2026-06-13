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
     * @param mixed $user The PHP-Nuke auth cookie (authz source — never POST)
     * @param array<string, mixed> $post
     */
    public function acceptTradeOffer(mixed $user, array $post): void;

    /**
     * @param mixed $user The PHP-Nuke auth cookie (authz source — never POST)
     * @param array<string, mixed> $post
     */
    public function rejectTradeOffer(mixed $user, array $post): void;
}
