<?php

declare(strict_types=1);

namespace ApiKeys\Contracts;

interface ApiKeysViewInterface
{
    /**
     * Render the "no key" state with a generate button.
     */
    public function renderNoKeyState(): string;

    /**
     * Render the "key just generated" state showing the raw key once.
     *
     * @param string $rawKey The full API key (shown once, never stored)
     */
    public function renderNewKeyState(string $rawKey): string;

    /**
     * Render the "active key" state showing prefix and management options.
     *
     * @param array{key_prefix: string, permission_level: string, rate_limit_tier: string, is_active: int, created_at: string, last_used_at: ?string} $keyStatus
     */
    public function renderActiveKeyState(array $keyStatus): string;
}
