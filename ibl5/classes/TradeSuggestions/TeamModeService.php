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
     */
    public function resolveFromInputs(TeamModeSetting $s, ModeInputs $inputs): ResolvedTeamMode
    {
        $guess = $this->guesser->guess($inputs);

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
