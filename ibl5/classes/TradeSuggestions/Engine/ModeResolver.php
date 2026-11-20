<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use ProjectedDraftOrder\Contracts\ProjectedDraftOrderServiceInterface;
use Season\Season;
use Trading\Contracts\TradeFormRepositoryInterface;
use TradeSuggestions\Contracts\SuggestionAssetRepositoryInterface;
use TradeSuggestions\Contracts\TeamModeRepositoryInterface;
use TradeSuggestions\ResolvedTeamMode;
use TradeSuggestions\TeamAssets;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TeamModeGuesser;
use TradeSuggestions\TeamModeService;
use TradeSuggestions\TeamModeSetting;

/**
 * Resolves every team's mode for the v3 engine. The snapshot loader and the
 * cached snapshot provider both call this, so the modes in the cache key always
 * equal the modes in the snapshot.
 *
 * The steps copy `TradeSuggestionsService` (team list, rosters, current W/L
 * from the draft order, prior W/L, guesser inputs, stored overrides) and add
 * `allowMiddle: true`, so an Auto team near .500 resolves to Middle.
 *
 * The full results of the latest `resolveAll()` call stay readable through
 * `lastResolved()`, so the panel's mode line keeps the guess behind an
 * override without a second roster load. Nothing is memoized: every
 * `resolveAll()` call re-reads the stored settings.
 */
final class ModeResolver
{
    /** @var array<int, ResolvedTeamMode> */
    private array $lastResolved = [];

    public function __construct(
        private readonly SuggestionAssetRepositoryInterface $assetRepo,
        private readonly ProjectedDraftOrderServiceInterface $draftOrderService,
        private readonly TeamModeRepositoryInterface $teamModeRepo,
        private readonly TradeFormRepositoryInterface $formRepo,
        private readonly TeamModeService $teamModeService,
        private readonly Season $season,
    ) {
    }

    /**
     * @return array<int, TeamMode> Keyed by tid
     */
    public function resolveAll(): array
    {
        $endingYear = $this->season->endingYear;

        $allTids = [];
        foreach ($this->formRepo->getAllTeamsWithCity() as $team) {
            $allTids[] = $team['teamid'];
        }

        $rosters = $this->assetRepo->getRostersForTeams($allTids, $this->season->advancesContractYears(), $endingYear - 1);

        /** @var array<int, TeamAssets> $assets */
        $assets = [];
        foreach ($allTids as $tid) {
            $assets[$tid] = new TeamAssets($tid, $rosters[$tid] ?? [], []);
        }

        $current = [];
        foreach ($this->draftOrderService->calculateDraftOrder($endingYear)['round1'] as $slot) {
            $current[$slot['teamId']] = ['wins' => $slot['wins'], 'losses' => $slot['losses']];
        }
        $prior = $this->assetRepo->getRegularSeasonRecords($endingYear - 1);

        $inputs = TeamModeGuesser::inputsForLeague($assets, $current, $prior);
        $stored = $this->teamModeRepo->getAllStoredSettings();

        $resolved = [];
        $modes = [];
        foreach ($allTids as $tid) {
            $resolved[$tid] = $this->teamModeService->resolveFromInputs(
                $stored[$tid] ?? TeamModeSetting::Auto,
                $inputs[$tid],
                allowMiddle: true,
            );
            $modes[$tid] = $resolved[$tid]->mode;
        }
        $this->lastResolved = $resolved;

        return $modes;
    }

    /**
     * Every team's full resolution from the latest `resolveAll()` call; empty
     * before the first call.
     *
     * @return array<int, ResolvedTeamMode> Keyed by tid
     */
    public function lastResolved(): array
    {
        return $this->lastResolved;
    }
}
