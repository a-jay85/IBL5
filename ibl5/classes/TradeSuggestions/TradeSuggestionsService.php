<?php

declare(strict_types=1);

namespace TradeSuggestions;

use Settings\Contracts\SettingsServiceInterface;
use Trading\Contracts\TradeFormRepositoryInterface;
use TradeSuggestions\Contracts\TradeSuggestionsServiceInterface;
use TradeSuggestions\Engine\CandidateGenerator;
use TradeSuggestions\Engine\Contracts\LeagueSnapshotProviderInterface;
use TradeSuggestions\Engine\DealRanker;
use TradeSuggestions\Engine\DealScorer;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\ModeResolver;
use TradeSuggestions\Engine\ProfileLines;
use TradeSuggestions\Engine\RankDiagnostics;
use TradeSuggestions\Engine\RankGateCallback;
use TradeSuggestions\Feedback\Contracts\FeedbackRepositoryInterface;
use TradeSuggestions\Feedback\DealKey;
use TradeSuggestions\Feedback\HiddenDealFilter;
use TradeSuggestions\Feedback\PanelSessionState;
use TradeSuggestions\Veto\VetoSet;

/**
 * Composes the trade suggestion engine for one GM's homepage panel.
 *
 * Snapshot (cached) -> generate -> score both sides -> the ranker's fairness,
 * exposure and veto gates -> the callback's real trade validation and reasons
 * -> display items. Only the callback's bounded validation calls reach the
 * database. Each stage's drop count is returned as SuggestionDiagnostics.
 *
 * @see TradeSuggestionsServiceInterface
 */
class TradeSuggestionsService implements TradeSuggestionsServiceInterface
{
    public function __construct(
        private readonly LeagueSnapshotProviderInterface $snapshots,
        private readonly CandidateGenerator $generator,
        private readonly VetoSet $vetoes,
        private readonly DealRanker $ranker,
        private readonly SuggestionValidationFilter $filter,
        private readonly TradeFormRepositoryInterface $formRepo,
        private readonly SettingsServiceInterface $settingsService,
        private readonly ModeResolver $modes,
        private readonly FeedbackRepositoryInterface $feedback,
        private readonly PanelSessionState $session,
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

        $s = $this->snapshots->snapshot();
        // lastKey() is null until the first snapshot() call, so read it after.
        $simKey = $this->snapshots->lastKey() ?? '';
        $userMode = $this->modes->lastResolved()[$tid]
            ?? throw new \LogicException("No resolved mode for team {$tid}");

        $g = $this->generator->generate($s, $tid, $simKey);
        $scored = DealScorer::scoreAll($s, $g, $g->shapeIdByKey);
        // Hidden ideas leave before the ranker, so they never use up a validation.
        $split = HiddenDealFilter::apply($scored, $this->feedback->hiddenKeys($tid));
        $scored = $split['kept'];
        $result = $this->ranker->select($s, $simKey, $tid, $scored, $this->vetoes, RankGateCallback::make($this->filter, $teamNames));

        $this->session->forSimKey($simKey);
        $allItems = [];
        $map = [];
        foreach ($result->eligible as $ranked) {
            $c = $ranked->deal->candidate;
            $partnerName = $teamNames[$c->partnerTid] ?? '';
            $key = DealKey::forCandidate($c);
            $item = new SuggestionItem(
                partnerTeamName: $partnerName,
                giveLabels: self::labels($s, $c->userPids, $c->userPickIds, $teamNames),
                getLabels: self::labels($s, $c->partnerPids, $c->partnerPickIds, $teamNames),
                userReason: $ranked->userReason ?? '',
                partnerReason: $ranked->partnerReason ?? '',
                prefillUrl: SuggestionLink::forCandidate($partnerName, $c),
                dealKey: $key->deal,
                coreKey: $key->core,
                profileLines: ProfileLines::forUser($ranked->deal->userDelta),
            );
            $allItems[] = $item;
            $map[$key->deal] = [
                'core' => $key->core,
                'partnerTid' => $c->partnerTid,
                'dealJson' => json_encode(
                    ['give' => $item->giveLabels, 'get' => $item->getLabels, 'keys' => DealKey::assetKeys($c)],
                    JSON_THROW_ON_ERROR
                ),
            ];
        }
        $this->session->setEligible($map);
        $offset = $this->session->offset();
        $items = array_slice($allItems, $offset, TradeSuggestionsView::MAX_ITEMS);

        $d = $result->diagnostics;

        return new TradeSuggestionsPanelData(
            $userMode,
            $items,
            new SuggestionDiagnostics($g->rawCount, count($scored), ['HIDDEN' => count($split['dropped'])] + self::rejections($d), $d->validated, $d->validatorRejections),
            count($allItems),
            $offset,
        );
    }

    /**
     * @see TradeSuggestionsServiceInterface::hiddenNote()
     *
     * @return array{deal_key: string, reason_codes: string, free_text: ?string, created_at: string}|null
     */
    public function hiddenNote(int $tid): ?array
    {
        return $this->feedback->latestForTeam($tid);
    }

    /**
     * The ranker's drop counts under flat string keys.
     *
     * @return array<string, int>
     */
    private static function rejections(RankDiagnostics $d): array
    {
        $out = [
            'NOT_BOTH_POSITIVE' => $d->notBothPositive,
            'LOPSIDED' => $d->lopsided,
            'EXPOSURE' => $d->exposure,
        ];
        foreach ($d->vetoedById as $id => $count) {
            $out['VETO:' . $id] = $count;
        }
        $out['NO_MOTIVE'] = $d->noMotive;

        return $out;
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
    private static function labels(LeagueSnapshot $s, array $pids, array $pickIds, array $teamNames): array
    {
        $labels = [];
        foreach ($pids as $pid) {
            $player = $s->players[$pid] ?? null;
            if ($player !== null) {
                $labels[] = $player->name;
            }
        }
        foreach ($pickIds as $pickId) {
            $pick = $s->picks[$pickId] ?? null;
            if ($pick !== null) {
                $original = $teamNames[$pick->originalTid] ?? '';
                $labels[] = $pick->year . ' Round ' . $pick->round . ($original !== '' ? ' (' . $original . ')' : '');
            }
        }

        return $labels;
    }
}
