<?php

declare(strict_types=1);

namespace TradeSuggestions;

use TradeSuggestions\Contracts\TeamModeRepositoryInterface;

/**
 * Resolves a team's effective mode (GM override, else the guess) and records
 * overrides. Never reads request or session state; callers pass the tid.
 */
class TeamModeService
{
    public function __construct(
        private readonly TeamModeRepositoryInterface $repo,
        private readonly TeamModeGuesser $guesser,
    ) {
    }

    /**
     * Resolve a mode from already-loaded inputs. The GM's own team and every
     * partner go through here, so a team always gets the same guess.
     *
     * $allowMiddle reaches the Auto arm only. A GM override is never Middle, so
     * the override arms keep the two-way guess they always had.
     */
    public function resolveFromInputs(TeamModeSetting $s, ModeInputs $inputs, bool $allowMiddle = false): ResolvedTeamMode
    {
        $guess = $this->guesser->guess($inputs, $allowMiddle && $s === TeamModeSetting::Auto);

        return match ($s) {
            TeamModeSetting::WinNow => new ResolvedTeamMode(TeamMode::WinNow, $guess->mode, true, $guess),
            TeamModeSetting::Rebuild => new ResolvedTeamMode(TeamMode::Rebuild, $guess->mode, true, $guess),
            TeamModeSetting::Auto => new ResolvedTeamMode($guess->mode, $guess->mode, false, $guess),
        };
    }

    public function setOverride(int $tid, TeamModeSetting $setting): void
    {
        $this->repo->saveSetting($tid, $setting);
    }
}
