<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use ProjectedDraftOrder\Contracts\ProjectedDraftOrderServiceInterface;
use Season\Season;
use Trading\Contracts\TradeFormRepositoryInterface;
use TradeSuggestions\Contracts\SuggestionAssetRepositoryInterface;
use TradeSuggestions\Contracts\TeamModeRepositoryInterface;
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
 */
final class ModeResolver
{
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

        $modes = [];
        foreach ($allTids as $tid) {
            $modes[$tid] = $this->teamModeService->resolveFromInputs(
                $stored[$tid] ?? TeamModeSetting::Auto,
                $inputs[$tid],
                allowMiddle: true,
            )->mode;
        }

        return $modes;
    }
}
