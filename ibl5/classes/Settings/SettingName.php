<?php

declare(strict_types=1);

namespace Settings;

/**
 * Canonical setting names from ibl_settings table
 *
 * Use these constants instead of raw strings to avoid typos and make
 * setting names discoverable via IDE autocomplete.
 */
enum SettingName: string
{
    case AllowTrades = 'Allow Trades';
    case AllowWaiverMoves = 'Allow Waiver Moves';
    case ShowDraftLink = 'Show Draft Link';
    case TriviaMode = 'Trivia Mode';
    case FreeAgencyNotifications = 'Free Agency Notifications';
    case ASGVoting = 'ASG Voting';
    case EOYVoting = 'EOY Voting';
    case DraftOrderFinalized = 'Draft Order Finalized';
    case CurrentSeasonPhase = 'Current Season Phase';
    case CurrentSeasonEndingYear = 'Current Season Ending Year';
    case SimLengthInDays = 'Sim Length in Days';

    /**
     * Whether this setting uses 'Yes'/'No' (true) or 'On'/'Off' (false) for boolean values
     */
    public function usesYesNo(): bool
    {
        return match ($this) {
            self::AllowTrades,
            self::AllowWaiverMoves,
            self::ASGVoting,
            self::EOYVoting,
            self::DraftOrderFinalized => true,
            self::ShowDraftLink,
            self::TriviaMode,
            self::FreeAgencyNotifications => false,
            default => true,
        };
    }

    /**
     * Get the "enabled" value for this setting
     */
    public function enabledValue(): string
    {
        return $this->usesYesNo() ? 'Yes' : 'On';
    }

    /**
     * Get the "disabled" value for this setting
     */
    public function disabledValue(): string
    {
        return $this->usesYesNo() ? 'No' : 'Off';
    }
}
