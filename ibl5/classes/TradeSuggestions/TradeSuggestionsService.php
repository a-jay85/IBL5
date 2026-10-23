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
 * Load (batched) -> resolve every team's mode from the same inputs -> generate
 * -> gates and both-sides scoring -> rank and run the real trade validation ->
 * build display items. No per-candidate SQL: only the ranker's bounded
 * validation calls reach the database. Each stage's drop count is returned as
 * SuggestionDiagnostics.
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
        private readonly NeedsCardBuilder $cardBuilder,
        private readonly NeedPairMatcher $matcher,
        private readonly NeedJudge $judge,
        private readonly ReasonBuilder $reasons,
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
        $advances = $this->season->advancesContractYears();
        $rosters = $this->assetRepo->getRostersForTeams($allTids, $advances, $endingYear - 1);
        $pickRows = $this->assetRepo->getPicksForTeams($allTids, $endingYear);
        $order = $this->draftOrderService->calculateDraftOrder($endingYear);
        $slotIndex = $this->pickValuer->buildSlotIndex($order);

        /** @var array<int, TeamAssets> $assets */
        $assets = [];
        foreach ($allTids as $teamId) {
            $players = $rosters[$teamId] ?? [];
            $assets[$teamId] = new TeamAssets(
                $teamId,
                $players,
                $this->pickValuer->toPicks($pickRows[$teamId] ?? [], $slotIndex, $endingYear),
                self::rosterCount($players, $advances),
                $this->filter->capRoom($teamNames[$teamId] ?? '', $teamId),
            );
        }

        $records = [];
        foreach ($order['round1'] as $slot) {
            $records[$slot['teamId']] = ['wins' => $slot['wins'], 'losses' => $slot['losses']];
        }
        $inputs = TeamModeGuesser::inputsForLeague($assets, $records, $this->assetRepo->getRegularSeasonRecords($endingYear - 1));
        $storedSettings = $this->teamModeRepo->getAllStoredSettings();

        /** @var array<int, ResolvedTeamMode> $modes */
        $modes = [];
        foreach ($allTids as $teamId) {
            $modes[$teamId] = $this->teamModeService->resolveFromInputs(
                $storedSettings[$teamId] ?? TeamModeSetting::Auto,
                $inputs[$teamId],
            );
        }
        $userMode = $modes[$tid];

        /** @var array<int, TeamDepth> $depths */
        $depths = [];
        foreach ($allTids as $teamId) {
            $depths[$teamId] = SlotMapper::depthFor($assets[$teamId]->players);
        }
        $ranks = LeagueStarterRanks::build($depths);

        /** @var array<int, NeedsCard> $cards */
        $cards = [];
        foreach ($allTids as $teamId) {
            $cards[$teamId] = $this->cardBuilder->build(
                $assets[$teamId],
                $depths[$teamId],
                $ranks,
                $modes[$teamId]->mode,
                TeamModeGuesser::recordPct($inputs[$teamId]),
            );
        }

        $partnerCards = [];
        $partnerAssets = [];
        $partnerDepths = [];
        foreach ($allTids as $partnerTid) {
            if ($partnerTid !== $tid) {
                $partnerCards[$partnerTid] = $cards[$partnerTid];
                $partnerAssets[$partnerTid] = $assets[$partnerTid];
                $partnerDepths[$partnerTid] = $depths[$partnerTid];
            }
        }

        $userAssets = $assets[$tid];
        $userCard = $cards[$tid];
        $generated = 0;
        /** @var array<string, int> $rejections */
        $rejections = [];
        foreach (RejectReason::cases() as $reason) {
            $rejections[$reason->value] = 0;
        }
        $scored = [];
        foreach ($this->matcher->generate($userCard, $userAssets, $partnerCards, $partnerAssets, $depths[$tid], $partnerDepths, $ranks) as $paired) {
            $generated++;
            $candidate = $paired->candidate;
            $partnerTid = $candidate->partnerTid;
            $judgment = $this->judge->judge($paired, $userCard, $cards[$partnerTid], $userAssets, $assets[$partnerTid], $ranks);
            if ($judgment instanceof RejectReason) {
                $rejections[$judgment->value]++;
                continue;
            }

            $userReason = $this->reasons->forSide($paired, $judgment, true, $userCard, $userAssets, $assets[$partnerTid], $ranks);
            $partnerReason = $this->reasons->forSide($paired, $judgment, false, $cards[$partnerTid], $assets[$partnerTid], $userAssets, $ranks);
            if ($userReason === null || $partnerReason === null) {
                $rejections[RejectReason::NoMotive->value]++;
                continue;
            }

            $scored[] = new ScoredCandidate(
                $candidate,
                min($judgment->userGain, $judgment->partnerGain),
                $userReason,
                $partnerReason,
                $paired,
                $judgment,
            );
        }

        $userTeamName = $teamNames[$tid] ?? '';
        $filter = $this->filter;
        $validated = 0;
        /** @var array<string, int> $validatorRejections */
        $validatorRejections = [];
        $selected = $this->ranker->select(
            $scored,
            static function (ScoredCandidate $s) use ($filter, $userTeamName, $teamNames, $tid, &$validated, &$validatorRejections): bool {
                $c = $s->candidate;
                $tradeData = $filter->buildTradeFormData(
                    $userTeamName,
                    $teamNames[$c->partnerTid] ?? '',
                    $tid,
                    $c->partnerTid,
                    $c,
                );

                $validated++;
                $failure = $filter->rejection($tradeData, $tid, $c->partnerTid, $c);
                if ($failure !== null) {
                    $validatorRejections[$failure] = ($validatorRejections[$failure] ?? 0) + 1;
                }

                return $failure === null;
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

        return new TradeSuggestionsPanelData(
            $userMode,
            $items,
            new SuggestionDiagnostics($generated, count($scored), $rejections, $validated, $validatorRejections),
        );
    }

    /**
     * Players the Trading roster-limit check counts: everyone in season; in the
     * offseason only players still under contract (TradeFormRepository::getTeamPlayerCount()).
     *
     * @param list<SuggestionPlayer> $players
     */
    private static function rosterCount(array $players, bool $advancesContractYears): int
    {
        if (!$advancesContractYears) {
            return count($players);
        }

        return count(array_filter($players, static fn (SuggestionPlayer $p): bool => $p->countsOnRoster));
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
