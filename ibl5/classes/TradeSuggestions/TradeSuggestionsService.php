<?php

declare(strict_types=1);

namespace TradeSuggestions;

use ProjectedDraftOrder\Contracts\ProjectedDraftOrderServiceInterface;
use Season\Season;
use Settings\Contracts\SettingsServiceInterface;
use Trading\Contracts\TradeFormRepositoryInterface;
use TradeSuggestions\Contracts\SuggestionAssetRepositoryInterface;
use TradeSuggestions\Contracts\TeamModeRepositoryInterface;
use TradeSuggestions\Contracts\TradeSuggestionsServiceInterface;

/**
 * Composes the trade suggestion engine for one GM's homepage panel.
 *
 * Load (batched) -> resolve modes -> generate -> both-sides filter -> rank and
 * run the real trade validation -> build display items. No per-candidate SQL:
 * only the ranker's bounded validation calls reach the database.
 *
 * @see TradeSuggestionsServiceInterface
 */
class TradeSuggestionsService implements TradeSuggestionsServiceInterface
{
    public function __construct(
        private readonly TeamModeService $teamModeService,
        private readonly TeamModeRepositoryInterface $teamModeRepo,
        private readonly SuggestionAssetRepositoryInterface $assetRepo,
        private readonly ProjectedDraftOrderServiceInterface $draftOrderService,
        private readonly TradeFormRepositoryInterface $formRepo,
        private readonly SettingsServiceInterface $settingsService,
        private readonly Season $season,
        private readonly PickValuer $pickValuer,
        private readonly CandidateGenerator $generator,
        private readonly TradeImpactEvaluator $evaluator,
        private readonly SuggestionValidationFilter $filter,
        private readonly SuggestionRanker $ranker,
    ) {
    }

    /**
     * @see TradeSuggestionsServiceInterface::buildForTeam()
     */
    public function buildForTeam(int $tid): ?TradeSuggestionsPanelData
    {
        if (!$this->settingsService->isTradesAllowed()) {
            return null;
        }

        // Same real-franchise list the Trading partner dropdown shows (no Free Agents).
        $teamNames = [];
        foreach ($this->formRepo->getAllTeamsWithCity() as $team) {
            $teamNames[$team['teamid']] = $team['team_name'];
        }

        $allTids = array_keys($teamNames);
        if (!isset($teamNames[$tid])) {
            $allTids[] = $tid;
        }

        $endingYear = $this->season->endingYear;
        $rosters = $this->assetRepo->getRostersForTeams($allTids);
        $pickRows = $this->assetRepo->getPicksForTeams($allTids, $endingYear);
        $order = $this->draftOrderService->calculateDraftOrder($endingYear);
        $slotIndex = $this->pickValuer->buildSlotIndex($order);

        /** @var array<int, TeamAssets> $assets */
        $assets = [];
        foreach ($allTids as $teamId) {
            $assets[$teamId] = new TeamAssets(
                $teamId,
                $rosters[$teamId] ?? [],
                $this->pickValuer->toPicks($pickRows[$teamId] ?? [], $slotIndex, $endingYear),
            );
        }

        $records = [];
        foreach ($order['round1'] as $slot) {
            $records[$slot['teamId']] = ['wins' => $slot['wins'], 'losses' => $slot['losses']];
        }

        $userMode = $this->teamModeService->resolve($tid);
        $storedSettings = $this->teamModeRepo->getAllStoredSettings();

        $partners = [];
        /** @var array<int, TeamMode> $partnerModes */
        $partnerModes = [];
        foreach ($allTids as $partnerTid) {
            if ($partnerTid === $tid) {
                continue;
            }
            $partnerAssets = $assets[$partnerTid];
            $partners[] = $partnerAssets;
            $record = $records[$partnerTid] ?? ['wins' => 0, 'losses' => 0];
            $partnerModes[$partnerTid] = $this->teamModeService->resolveFromInputs(
                $storedSettings[$partnerTid] ?? TeamModeSetting::Auto,
                $record['wins'],
                $record['losses'],
                self::averageAge($partnerAssets->players),
            )->mode;
        }

        $userAssets = $assets[$tid];
        $scored = [];
        foreach ($this->generator->generate($userAssets, $partners) as $candidate) {
            $partnerTid = $candidate->partnerTid;
            if (!isset($assets[$partnerTid], $partnerModes[$partnerTid])) {
                continue;
            }
            $result = $this->evaluator->evaluate(
                $candidate,
                $userAssets,
                $assets[$partnerTid],
                $userMode->mode,
                $partnerModes[$partnerTid],
            );
            if ($result !== null) {
                $scored[] = $result;
            }
        }

        $userTeamName = $teamNames[$tid] ?? '';
        $filter = $this->filter;
        $selected = $this->ranker->select(
            $scored,
            static function (ScoredCandidate $s) use ($filter, $userTeamName, $teamNames, $tid): bool {
                $c = $s->candidate;
                $tradeData = $filter->buildTradeFormData(
                    $userTeamName,
                    $teamNames[$c->partnerTid] ?? '',
                    $tid,
                    $c->partnerTid,
                    $c,
                );

                return $filter->passes($tradeData, $tid, $c->partnerTid, $c);
            },
        );

        $items = [];
        foreach ($selected as $s) {
            $c = $s->candidate;
            $partnerName = $teamNames[$c->partnerTid] ?? '';
            $items[] = new SuggestionItem(
                partnerTeamName: $partnerName,
                giveLabels: self::labels($userAssets, $c->userPids, $c->userPickIds, $teamNames),
                getLabels: self::labels($assets[$c->partnerTid], $c->partnerPids, $c->partnerPickIds, $teamNames),
                userReason: $s->userReason,
                partnerReason: $s->partnerReason,
                prefillUrl: SuggestionLink::forCandidate($partnerName, $c),
            );
        }

        return new TradeSuggestionsPanelData($userMode, $items);
    }

    /**
     * Mean age of a loaded roster, ignoring unknown (zero) ages.
     *
     * @param list<SuggestionPlayer> $players
     */
    private static function averageAge(array $players): float
    {
        $sum = 0;
        $count = 0;
        foreach ($players as $player) {
            if ($player->age > 0) {
                $sum += $player->age;
                $count++;
            }
        }

        return $count === 0 ? 0.0 : $sum / $count;
    }

    /**
     * Display labels for one side of a deal: player names, then pick text such
     * as "2027 Round 1 (Heat)".
     *
     * @param list<int> $pids
     * @param list<int> $pickIds
     * @param array<int, string> $teamNames
     * @return list<string>
     */
    private static function labels(TeamAssets $owner, array $pids, array $pickIds, array $teamNames): array
    {
        $labels = [];
        foreach ($owner->players as $player) {
            if (in_array($player->pid, $pids, true)) {
                $labels[] = $player->name;
            }
        }
        foreach ($owner->picks as $pick) {
            if (in_array($pick->pickId, $pickIds, true)) {
                $original = $teamNames[$pick->originalTid] ?? '';
                $labels[] = $pick->year . ' Round ' . $pick->round . ($original !== '' ? ' (' . $original . ')' : '');
            }
        }

        return $labels;
    }
}
