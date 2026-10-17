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

    public function resolve(int $tid): ResolvedTeamMode
    {
        $inputs = $this->repo->getModeInputs($tid);

        return $this->resolveFromInputs(
            $this->repo->getStoredSetting($tid),
            $inputs['wins'],
            $inputs['losses'],
            $inputs['avgAge'],
        );
    }

    /**
     * Resolve a mode from already-loaded inputs, so batch callers skip the
     * per-team reads. resolve() delegates here; the mapping lives only here.
     */
    public function resolveFromInputs(TeamModeSetting $s, int $wins, int $losses, float $avgAge): ResolvedTeamMode
    {
        $guessed = $this->guesser->guess($wins, $losses, $avgAge);

        return match ($s) {
            TeamModeSetting::WinNow => new ResolvedTeamMode(TeamMode::WinNow, $guessed, true),
            TeamModeSetting::Rebuild => new ResolvedTeamMode(TeamMode::Rebuild, $guessed, true),
            TeamModeSetting::Auto => new ResolvedTeamMode($guessed, $guessed, false),
        };
    }

    public function setOverride(int $tid, TeamModeSetting $setting): void
    {
        $this->repo->saveSetting($tid, $setting);
    }
}
