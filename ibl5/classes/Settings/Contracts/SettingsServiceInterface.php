<?php

declare(strict_types=1);

namespace Settings\Contracts;

/**
 * Typed accessors for ibl_settings boolean values
 *
 * Encapsulates the Yes/No vs On/Off distinction so callers
 * don't need to know which convention each setting uses.
 */
interface SettingsServiceInterface
{
    public function isTradesAllowed(): bool;

    public function isWaiverMovesAllowed(): bool;

    public function isDraftLinkShown(): bool;

    public function isFreeAgencyNotifications(): bool;
}
