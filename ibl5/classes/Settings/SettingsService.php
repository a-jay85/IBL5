<?php

declare(strict_types=1);

namespace Settings;

use Settings\Contracts\SettingsServiceInterface;

use Season\Season;
/**
 * Typed boolean accessors for ibl_settings
 *
 * Wraps a Season object (which already loads settings from the database)
 * and provides boolean methods instead of raw string comparisons.
 * This removes the need for callers to know whether a setting uses
 * 'Yes'/'No' or 'On'/'Off'.
 *
 * @see SettingsServiceInterface
 */
class SettingsService implements SettingsServiceInterface
{
    private Season $season;

    public function __construct(Season $season)
    {
        $this->season = $season;
    }

    /**
     * @see SettingsServiceInterface::isTradesAllowed()
     */
    public function isTradesAllowed(): bool
    {
        return $this->season->allowTrades === SettingName::AllowTrades->enabledValue();
    }

    /**
     * @see SettingsServiceInterface::isWaiverMovesAllowed()
     */
    public function isWaiverMovesAllowed(): bool
    {
        return $this->season->allowWaivers === SettingName::AllowWaiverMoves->enabledValue();
    }

    /**
     * @see SettingsServiceInterface::isDraftLinkShown()
     */
    public function isDraftLinkShown(): bool
    {
        return $this->season->showDraftLink === SettingName::ShowDraftLink->enabledValue();
    }

    /**
     * @see SettingsServiceInterface::isFreeAgencyNotifications()
     */
    public function isFreeAgencyNotifications(): bool
    {
        return $this->season->freeAgencyNotificationsState === SettingName::FreeAgencyNotifications->enabledValue();
    }
}
