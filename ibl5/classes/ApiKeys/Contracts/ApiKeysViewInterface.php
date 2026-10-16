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

    /**
     * Render the static Player Export guide (moved from the retired PlayerExportGuide module).
     * Rendered below every key-state card by ApiKeysController::handle().
     */
    public function renderExportGuide(): string;

    /**
     * Render the one-shot flash message set by a POST-redirect op. Empty string for null.
     *
     * @param array{type: string, text: string}|null $flash
     */
    public function renderFlash(?array $flash): string;

    /**
     * Render the Google Sheets Sync card: not configured, not connected, active, or broken.
     *
     * @param array{status: string, broken_reason: ?string, spreadsheet_url: string, last_refresh_at: ?string, last_refresh_status: ?string, last_error: ?string}|null $summary
     */
    public function renderGoogleSheetCard(?array $summary, bool $configured): string;
}
