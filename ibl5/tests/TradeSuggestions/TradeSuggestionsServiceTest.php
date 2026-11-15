<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\TestCase;
use ProjectedDraftOrder\Contracts\ProjectedDraftOrderServiceInterface;
use Settings\Contracts\SettingsServiceInterface;
use Tests\TradeSuggestions\Fixtures\LaughTestRows;
use Tests\TradeSuggestions\LaughTest\Fixtures\LaughTestFactory;
use Trading\Contracts\TradeFormRepositoryInterface;
use Trading\Contracts\TradeValidatorInterface;
use Trading\TradePrefillParser;
use TradeSuggestions\Contracts\SuggestionAssetRepositoryInterface;
use TradeSuggestions\Contracts\TeamModeRepositoryInterface;
use TradeSuggestions\Engine\AssetPool;
use TradeSuggestions\Engine\CandidateGenerator;
use TradeSuggestions\Engine\Contracts\LeagueSnapshotProviderInterface;
use TradeSuggestions\Engine\DealRanker;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\ModeResolver;
use TradeSuggestions\Engine\RankDiagnostics;
use TradeSuggestions\Engine\Shape\Contracts\DealShapeInterface;
use TradeSuggestions\Feedback\Contracts\FeedbackRepositoryInterface;
use TradeSuggestions\Feedback\DealKey;
use TradeSuggestions\Feedback\PanelSessionState;
use TradeSuggestions\SuggestionPlayer;
use TradeSuggestions\SuggestionValidationFilter;
use TradeSuggestions\TeamModeGuesser;
use TradeSuggestions\TeamModeService;
use TradeSuggestions\TeamModeSetting;
use TradeSuggestions\TradeCandidate;
use TradeSuggestions\TradeSuggestionsPanelData;
use TradeSuggestions\TradeSuggestionsService;
use TradeSuggestions\TradeSuggestionsView;
use TradeSuggestions\Veto\VetoSet;
use Validation\ValidationResult;
use Validation\ValidationResultWithContext;

/**
 * The v3 engine runs for real over the laugh-test league. A scripted shape
 * yields a fixed candidate list so the outcome does not hang on the real
 * shapes' search. The snapshot provider, the repositories and the trade
 * validator are fakes; the validation filter, ranker and reason writer are
 * real.
 *
 * The scripted deals: user 900013 for partner 6's 604, and user 900011 for
 * partner 25's 2504 or partner 26's 2608, each alone, with one user pick, or
 * with both. Under key sim-2 with no vetoes nine of the twelve pass the
 * ranker's fairness gates.
 */
class TradeSuggestionsServiceTest extends TestCase
{
    private const int USER_TID = LaughTestRows::USER_TID;
    private const string SIM_KEY = 'sim-2';

    /** @var list<array{int, int, int}> partner tid, user pid, partner pid */
    private const array PAIRS = [[6, 900013, 604], [25, 900011, 2504], [26, 900011, 2608]];

    /** @var list<list<int>> */
    private const array PICK_SETS = [[], [910001], [910002], [910001, 910002]];

    private static ?LeagueSnapshot $league = null;

    /** @var array<mixed> the fake session behind the panel state */
    private array $panelStore = [];

    public function testTradesDisabledReturnsNull(): void
    {
        $formRepo = self::createMock(TradeFormRepositoryInterface::class);
        $formRepo->expects($this->never())->method('getAllTeamsWithCity');

        $service = $this->service(tradesAllowed: false, formRepo: $formRepo);

        self::assertNull($service->buildForTeam(self::USER_TID));
    }

    /**
     * Only partner 26's 2608 and the user's 900011 may be traded, so the
     * validator refuses every partner-6 and partner-25 deal.
     */
    public function testEveryItemPassedTheValidatorCallback(): void
    {
        $tradeable = [900011, 2608];
        $panel = $this->build(tradeable: $tradeable);

        self::assertNotSame([], $panel->items);
        self::assertGreaterThan(0, $panel->diagnostics?->validatorRejections[SuggestionValidationFilter::FAIL_PLAYER] ?? 0);
        foreach ($panel->items as $item) {
            self::assertSame('Team 26', $item->partnerTeamName);
            $parsed = TradePrefillParser::parse(self::prefill($item->prefillUrl), $this->pageData(26));
            self::assertNotNull($parsed, $item->prefillUrl);
            foreach (array_keys($parsed['checkedItems']) as $key) {
                [$type, $id] = explode(':', $key);
                if ($type === '1') {
                    self::assertContains((int) $id, $tradeable);
                }
            }
        }
    }

    /**
     * Dupree and one pick for Laettner. The full laugh-test deal sends two
     * picks, which is three user assets, so the generator drops it before
     * scoring; one pick keeps the side at the two-asset limit. The deal fails
     * the both-sides-positive gate before the veto gate sees it, so the drop
     * shows under NOT_BOTH_POSITIVE. Key sim-27 lets partner 2 through the
     * exposure filter.
     */
    public function testDupreeForLaettnerNeverAppearsInItems(): void
    {
        $panel = $this->build(
            candidates: [new TradeCandidate(self::USER_TID, LaughTestRows::PARTNER_TID, [LaughTestRows::DUPREE_PID], [910002], [LaughTestRows::LAETTNER_PID], [])],
            vetoes: VetoSet::standard(),
            simKey: 'sim-27',
        );

        foreach ($panel->items as $item) {
            $labels = [...$item->giveLabels, ...$item->getLabels];
            self::assertFalse(in_array('Dupree', $labels, true) && in_array('Laettner', $labels, true));
        }
        self::assertSame(1, $panel->diagnostics?->rejections['NOT_BOTH_POSITIVE'] ?? 0);
    }

    /**
     * Key sim-2 generates nine deals; an empty key generates six. So the real
     * key must reach the generator and the ranker.
     */
    public function testTheSnapshotKeyReachesTheEngine(): void
    {
        $panel = $this->build(vetoes: VetoSet::standard());

        self::assertSame(9, $panel->diagnostics->generated);
        self::assertSame(9, $panel->diagnostics->scored);
        self::assertSame(
            [
                'NOT_BOTH_POSITIVE' => 0,
                'LOPSIDED' => 0,
                'EXPOSURE' => 0,
                'VETO:YOUNGER_BETTER_CHEAPER_FOR_OLDER_WORSE' => 0,
                'VETO:WIN_NOW_WORSE_THIS_SEASON' => 3,
                'VETO:REBUILDER_OLD_LONG_MONEY_UNPAID' => 0,
                'VETO:PICK_TO_NON_BURDEN_TAKER' => 4,
                'VETO:ROSTER_OVER_MAX_AFTER' => 0,
                'VETO:STRONG_FOR_WEAK_WITHOUT_NET_GAIN' => 0,
                'NO_MOTIVE' => 0,
            ],
            $panel->diagnostics->rejections,
        );
    }

    public function testItemsCarryBothReasons(): void
    {
        $panel = $this->build(vetoes: VetoSet::standard());

        self::assertCount(2, $panel->items);
        self::assertSame('Player 604, 34, 4 seasons left: PF starter rank 22 to 12; rebounds rank 27 to 1', $panel->items[0]->userReason);
        self::assertSame('Filler 900013, 27, 2 seasons left: defensive rebounding rank 17 to 7; PF starter rank 15 to 8', $panel->items[0]->partnerReason);
        self::assertSame('Player 2608, 32, 2 seasons left: no SF bench to best bench rank 1; Player 2608 266 under fair salary', $panel->items[1]->userReason);
        self::assertSame('Filler 900011, 25, 2 seasons left: three-point attempt rate rank 26 to 3', $panel->items[1]->partnerReason);
    }

    public function testRejectionsKeepGateOrderVetoIdsAndDistinctCounts(): void
    {
        $diagnostics = new RankDiagnostics(20, 1, 2, 3, 9, ['VETO_A' => 4, 'VETO_B' => 5], 6, [], 7, 8);

        $rejections = new \ReflectionMethod(TradeSuggestionsService::class, 'rejections');

        self::assertSame(
            ['NOT_BOTH_POSITIVE' => 1, 'LOPSIDED' => 2, 'EXPOSURE' => 3, 'VETO:VETO_A' => 4, 'VETO:VETO_B' => 5, 'NO_MOTIVE' => 7],
            $rejections->invoke(null, $diagnostics),
        );
    }

    public function testItemsCapAtThree(): void
    {
        $panel = $this->build();

        self::assertCount(3, $panel->items);
    }

    public function testEmptyCandidateListGivesEmptyPanelNotNull(): void
    {
        $panel = $this->build(candidates: []);

        self::assertSame([], $panel->items);
        self::assertStringContainsString('Mode: ', (new TradeSuggestionsView())->render($panel));
    }

    public function testPanelModeLineUsesTheUserMode(): void
    {
        $panel = $this->build(records: [70, 12]);

        $html = (new TradeSuggestionsView())->render($panel);

        self::assertStringContainsString('Mode: Win now', $html);
        self::assertStringContainsString('(our guess)', $html);
    }

    public function testPanelModeLineKeepsTheGuessForAnOverride(): void
    {
        $panel = $this->build(records: [70, 12], stored: TeamModeSetting::Rebuild);

        $html = (new TradeSuggestionsView())->render($panel);

        self::assertStringContainsString('Mode: Rebuild', $html);
        self::assertStringContainsString('(your choice, we guessed Win now)', $html);
    }

    public function testPanelModeLineShowsMiddleForNearFiveHundredAutoTeam(): void
    {
        $panel = $this->build(records: [41, 41]);

        $html = (new TradeSuggestionsView())->render($panel);

        self::assertStringContainsString('Mode: Middle', $html);
    }

    public function testLabelsNamePlayersThenPicks(): void
    {
        $panel = $this->build(candidates: [new TradeCandidate(self::USER_TID, 26, [900011], [910002], [2608], [])]);

        self::assertCount(1, $panel->items);
        $player = self::league()->players[900011]->name;
        self::assertSame([$player, '2028 Round 2 (Team 1)'], $panel->items[0]->giveLabels);
        self::assertSame([self::league()->players[2608]->name], $panel->items[0]->getLabels);
    }

    public function testHiddenDealKeyIsDropped(): void
    {
        $this->build();
        $baseline = $this->eligibleKeys();
        self::assertCount(9, $baseline);
        $hidden = $baseline[0];

        $this->panelStore = [];
        $panel = $this->build(feedback: $this->hiding(deal: [$hidden]));

        self::assertNotContains($hidden, $this->itemKeys($panel));
        self::assertNotContains($hidden, $this->eligibleKeys());
        self::assertSame(8, $panel->eligibleCount);
    }

    public function testHiddenCoreKeyIsDropped(): void
    {
        $this->build();
        $baseline = $this->eligibleKeys();
        $session = new PanelSessionState($this->panelStore);
        $entry = $session->entry($baseline[0]);
        self::assertNotNull($entry);
        $core = $entry['core'];
        $sameCore = array_filter($baseline, static fn (string $k): bool => ($session->entry($k)['core'] ?? '') === $core);
        // The pick variants of one player pair share its core.
        self::assertGreaterThan(1, count($sameCore));

        $this->panelStore = [];
        $panel = $this->build(feedback: $this->hiding(core: [$core]));

        self::assertSame([], array_intersect($sameCore, $this->eligibleKeys()));
        self::assertSame(count($baseline) - count($sameCore), $panel->eligibleCount);
        self::assertGreaterThanOrEqual(count($sameCore), $panel->diagnostics?->rejections['HIDDEN'] ?? 0);
    }

    public function testHiddenFilterUsesActingTid(): void
    {
        $feedback = self::createMock(FeedbackRepositoryInterface::class);
        $feedback->expects($this->once())->method('hiddenKeys')->with(self::USER_TID)->willReturn(['deal' => [], 'core' => []]);

        $this->build(feedback: $feedback);
    }

    public function testHiddenCountInDiagnostics(): void
    {
        $panel = $this->build();
        self::assertSame(0, $panel->diagnostics?->rejections['HIDDEN'] ?? null);
        $baseline = $this->eligibleKeys();

        $this->panelStore = [];
        $panel = $this->build(feedback: $this->hiding(deal: [$baseline[0], $baseline[1]]));

        self::assertSame(2, $panel->diagnostics?->rejections['HIDDEN'] ?? null);
    }

    public function testSessionMapWritten(): void
    {
        $panel = $this->build();
        $session = new PanelSessionState($this->panelStore);

        self::assertNotSame([], $panel->items);
        foreach ($panel->items as $item) {
            $entry = $session->entry($item->dealKey);
            self::assertNotNull($entry);
            self::assertSame($item->coreKey, $entry['core']);
            self::assertSame('Team ' . $entry['partnerTid'], $item->partnerTeamName);
            $deal = json_decode($entry['dealJson'], true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($deal);
            self::assertSame($item->giveLabels, $deal['give']);
            self::assertSame($item->getLabels, $deal['get']);
            self::assertIsArray($deal['keys']);
            self::assertNotSame([], $deal['keys']);
            foreach ($deal['keys'] as $key) {
                self::assertMatchesRegularExpression('/^[UP]:[01]:\d+$/', $key);
            }
        }
    }

    public function testOffsetWindow(): void
    {
        $session = new PanelSessionState($this->panelStore);
        $this->build(session: $session);
        $all = $this->eligibleKeys();
        $session->advance();

        $panel = $this->build(session: $session);

        self::assertSame(array_slice($all, 3, 3), $this->itemKeys($panel));
        self::assertSame(3, $panel->offset);
    }

    public function testOffsetPastEndYieldsFirstPage(): void
    {
        $session = new PanelSessionState($this->panelStore);
        $this->build(session: $session);
        $all = $this->eligibleKeys();
        $session->advance();
        $session->advance();
        self::assertSame(6, $session->offset());

        // Five hidden ideas leave four, so the stored offset of 6 is past the end.
        $panel = $this->build(session: $session, feedback: $this->hiding(deal: array_slice($all, 0, 5)));

        self::assertLessThanOrEqual(6, $panel->eligibleCount);
        self::assertSame(0, $panel->offset);
        self::assertSame(array_slice($this->eligibleKeys(), 0, 3), $this->itemKeys($panel));
    }

    public function testPanelDataCarriesCounts(): void
    {
        $panel = $this->build();

        self::assertSame(9, $panel->eligibleCount);
        self::assertSame(0, $panel->offset);
    }

    public function testItemsCarryDealAndCoreKeys(): void
    {
        $panel = $this->build();
        $byDeal = [];
        foreach (self::scripted() as $c) {
            $byDeal[DealKey::forCandidate($c)->deal] = $c;
        }

        self::assertNotSame([], $panel->items);
        foreach ($panel->items as $item) {
            self::assertArrayHasKey($item->dealKey, $byDeal);
            $candidate = $byDeal[$item->dealKey];
            self::assertSame(DealKey::forCandidate($candidate)->core, $item->coreKey);
            self::assertSame('Team ' . $candidate->partnerTid, $item->partnerTeamName);
        }
    }

    public function testNewSimKeyResetsTheOffset(): void
    {
        $session = new PanelSessionState($this->panelStore);
        $this->build(session: $session);
        $session->advance();
        self::assertSame(3, $session->offset());

        $panel = $this->build(session: $session, simKey: 'sim-3');

        self::assertSame(0, $panel->offset);
    }

    /**
     * @param list<string> $deal
     * @param list<string> $core
     */
    private function hiding(array $deal = [], array $core = []): FeedbackRepositoryInterface
    {
        $feedback = self::createStub(FeedbackRepositoryInterface::class);
        $feedback->method('hiddenKeys')->willReturn(['deal' => $deal, 'core' => $core]);
        $feedback->method('latestForTeam')->willReturn(null);

        return $feedback;
    }

    /**
     * Every idea the last render offered, in ranked order.
     *
     * @return list<string>
     */
    private function eligibleKeys(): array
    {
        return array_keys((new PanelSessionState($this->panelStore))->eligible());
    }

    /**
     * @return list<string>
     */
    private function itemKeys(TradeSuggestionsPanelData $panel): array
    {
        return array_map(static fn (\TradeSuggestions\SuggestionItem $i): string => $i->dealKey, $panel->items);
    }

    /**
     * @param list<TradeCandidate>|null $candidates null = the scripted twelve
     * @param list<int>|null $tradeable null = every player may be traded
     * @param array{int, int} $records the user's current wins and losses
     */
    private function build(
        ?array $candidates = null,
        ?VetoSet $vetoes = null,
        ?array $tradeable = null,
        array $records = [70, 12],
        ?TeamModeSetting $stored = null,
        string $simKey = self::SIM_KEY,
        ?FeedbackRepositoryInterface $feedback = null,
        ?PanelSessionState $session = null,
    ): TradeSuggestionsPanelData {
        $panel = $this->service(
            candidates: $candidates ?? self::scripted(),
            vetoes: $vetoes ?? new VetoSet([]),
            tradeable: $tradeable,
            records: $records,
            stored: $stored,
            simKey: $simKey,
            feedback: $feedback,
            session: $session,
        )->buildForTeam(self::USER_TID);
        self::assertNotNull($panel);

        return $panel;
    }

    /**
     * @param list<TradeCandidate> $candidates
     * @param list<int>|null $tradeable
     * @param array{int, int} $records
     */
    private function service(
        bool $tradesAllowed = true,
        ?TradeFormRepositoryInterface $formRepo = null,
        array $candidates = [],
        ?VetoSet $vetoes = null,
        ?array $tradeable = null,
        array $records = [70, 12],
        ?TeamModeSetting $stored = null,
        string $simKey = self::SIM_KEY,
        ?FeedbackRepositoryInterface $feedback = null,
        ?PanelSessionState $session = null,
    ): TradeSuggestionsService {
        $league = self::league();
        $formRepo ??= $this->formRepo($league);

        $settings = self::createStub(SettingsServiceInterface::class);
        $settings->method('isTradesAllowed')->willReturn($tradesAllowed);

        $season = new \Tests\WideUnit\Mocks\Season(self::createStub(\mysqli::class));
        $season->endingYear = 2027;

        $modes = $this->modeResolver($records, $stored, $season);
        $snapshots = new class ($league, $modes, $simKey) implements LeagueSnapshotProviderInterface {
            private ?string $key = null;

            public function __construct(
                private readonly LeagueSnapshot $league,
                private readonly ModeResolver $modes,
                private readonly string $simKey,
            ) {
            }

            public function snapshot(): LeagueSnapshot
            {
                $this->modes->resolveAll();
                $this->key = $this->simKey;

                return $this->league;
            }

            public function lastKey(): ?string
            {
                return $this->key;
            }
        };

        $validator = self::createStub(TradeValidatorInterface::class);
        $validator->method('validateSalaryCaps')->willReturn(ValidationResultWithContext::success(['userPostTradeCapTotal' => 0, 'partnerPostTradeCapTotal' => 0]));
        $validator->method('validateRosterLimits')->willReturn(ValidationResult::success());
        $validator->method('canPlayerBeTraded')->willReturnCallback(
            static fn (int $pid): bool => $tradeable === null || in_array($pid, $tradeable, true),
        );

        $capCalculator = self::createStub(\Trading\TradeCapCalculator::class);
        $capCalculator->method('calculateSalaryCapData')->willReturn([
            'userCurrentSeasonCapTotal' => 0,
            'partnerCurrentSeasonCapTotal' => 0,
            'userCapSentToPartner' => 0,
            'partnerCapSentToUser' => 0,
        ]);

        return new TradeSuggestionsService(
            $snapshots,
            new CandidateGenerator($candidates === [] ? [] : [self::shape($candidates)]),
            $vetoes ?? new VetoSet([]),
            new DealRanker(),
            new SuggestionValidationFilter($validator, $capCalculator, $formRepo, $season),
            $formRepo,
            $settings,
            $modes,
            $feedback ?? self::noFeedback(),
            $session ?? new PanelSessionState($this->panelStore),
        );
    }

    private static function noFeedback(): FeedbackRepositoryInterface
    {
        $feedback = self::createStub(FeedbackRepositoryInterface::class);
        $feedback->method('hiddenKeys')->willReturn(['deal' => [], 'core' => []]);
        $feedback->method('latestForTeam')->willReturn(null);

        return $feedback;
    }

    /**
     * Teams, players and picks straight from the snapshot, in tid and pid order.
     */
    private function formRepo(LeagueSnapshot $league): TradeFormRepositoryInterface
    {
        $formRepo = self::createStub(TradeFormRepositoryInterface::class);
        $rows = [];
        foreach ($league->teamNames as $tid => $name) {
            $rows[] = ['teamid' => $tid, 'team_name' => $name, 'team_city' => 'City', 'color1' => '000000', 'color2' => 'FFFFFF'];
        }
        $formRepo->method('getAllTeamsWithCity')->willReturn($rows);
        $formRepo->method('getTeamPlayersForTrading')->willReturnCallback(
            static fn (int $tid): array => array_map(
                static fn (int $pid): array => ['pid' => $pid, 'cy' => 1, 'salary_yr1' => 100],
                self::pidsOf($league, $tid),
            ),
        );
        $formRepo->method('getTeamDraftPicksForTrading')->willReturnCallback(
            static fn (int $tid): array => array_map(
                static fn (int $pickId): array => ['pickid' => $pickId],
                self::pickIdsOf($league, $tid),
            ),
        );

        return $formRepo;
    }

    /**
     * Teams 1 and 2 only; the panel reads just the user's resolution.
     *
     * @param array{int, int} $records
     */
    private function modeResolver(array $records, ?TeamModeSetting $stored, \Season\Season $season): ModeResolver
    {
        $rotation = [];
        foreach ([1, 2] as $i) {
            $rotation[] = new SuggestionPlayer(
                pid: $i,
                name: 'Player ' . $i,
                pos: 'SF',
                age: $i === 1 ? 26 : 27,
                injured: false,
                currentSalary: 1000,
                yearsRemaining: $i === 1 ? 2 : 1,
                futureSalary: 0,
                dcMinutes: 30,
            );
        }
        $assetRepo = self::createStub(SuggestionAssetRepositoryInterface::class);
        $assetRepo->method('getRostersForTeams')->willReturn([1 => $rotation, 2 => $rotation]);
        $assetRepo->method('getRegularSeasonRecords')->willReturn([]);

        $draftOrder = self::createStub(ProjectedDraftOrderServiceInterface::class);
        $draftOrder->method('calculateDraftOrder')->willReturn([
            'round1' => [self::slot(1, $records[0], $records[1]), self::slot(2, 41, 41)],
            'round2' => [],
        ]);

        $teamModeRepo = self::createStub(TeamModeRepositoryInterface::class);
        $teamModeRepo->method('getAllStoredSettings')->willReturn($stored === null ? [] : [self::USER_TID => $stored]);

        $modeFormRepo = self::createStub(TradeFormRepositoryInterface::class);
        $modeFormRepo->method('getAllTeamsWithCity')->willReturn([
            ['teamid' => 1, 'team_name' => 'Team 1', 'team_city' => 'A', 'color1' => '000000', 'color2' => 'FFFFFF'],
            ['teamid' => 2, 'team_name' => 'Team 2', 'team_city' => 'B', 'color1' => '000000', 'color2' => 'FFFFFF'],
        ]);

        return new ModeResolver(
            $assetRepo,
            $draftOrder,
            $teamModeRepo,
            $modeFormRepo,
            new TeamModeService(self::createStub(TeamModeRepositoryInterface::class), new TeamModeGuesser()),
            $season,
        );
    }

    /**
     * A shape that yields, for each partner, the given candidates aimed at it.
     *
     * @param list<TradeCandidate> $candidates
     */
    private static function shape(array $candidates): DealShapeInterface
    {
        return new class ($candidates) implements DealShapeInterface {
            /**
             * @param list<TradeCandidate> $candidates
             */
            public function __construct(private readonly array $candidates)
            {
            }

            public function id(): string
            {
                return 'SCRIPTED';
            }

            public function generate(LeagueSnapshot $s, int $userTid, int $partnerTid, AssetPool $pool): iterable
            {
                foreach ($this->candidates as $c) {
                    if ($c->partnerTid === $partnerTid) {
                        yield $c;
                    }
                }
            }
        };
    }

    /**
     * @return list<TradeCandidate>
     */
    private static function scripted(): array
    {
        $out = [];
        foreach (self::PAIRS as [$partner, $userPid, $partnerPid]) {
            foreach (self::PICK_SETS as $picks) {
                $out[] = new TradeCandidate(self::USER_TID, $partner, [$userPid], $picks, [$partnerPid], []);
            }
        }

        return $out;
    }

    private static function league(): LeagueSnapshot
    {
        return self::$league ??= LaughTestFactory::league();
    }

    /**
     * @return list<int>
     */
    private static function pidsOf(LeagueSnapshot $league, int $tid): array
    {
        $pids = array_keys(array_filter($league->teamOfPid, static fn (int $t): bool => $t === $tid));
        sort($pids);

        return $pids;
    }

    /**
     * @return list<int>
     */
    private static function pickIdsOf(LeagueSnapshot $league, int $tid): array
    {
        $ids = [];
        foreach ($league->picks as $id => $pick) {
            if ($pick->ownerTid === $tid) {
                $ids[] = $id;
            }
        }
        sort($ids);

        return $ids;
    }

    /**
     * The form page data TradePrefillParser checks keys against.
     *
     * @return array<string, mixed>
     */
    private function pageData(int $partnerTid): array
    {
        $league = self::league();
        $players = static fn (int $tid): array => array_map(static fn (int $pid): array => ['pid' => $pid], self::pidsOf($league, $tid));
        $picks = static fn (int $tid): array => array_map(static fn (int $id): array => ['pickid' => $id], self::pickIdsOf($league, $tid));

        return [
            'partnerTeamId' => $partnerTid,
            'userPlayers' => $players(self::USER_TID),
            'partnerPlayers' => $players($partnerTid),
            'userPicks' => $picks(self::USER_TID),
            'partnerPicks' => $picks($partnerTid),
        ];
    }

    private static function prefill(string $url): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $prefill = $query['prefill'] ?? '';

        return is_string($prefill) ? $prefill : '';
    }

    /**
     * @return array{pick: int, teamId: int, teamName: string, wins: int, losses: int, color1: string, color2: string, ownerId: int, ownerName: string, ownerColor1: string, ownerColor2: string, isTraded: bool, notes: string, movement: int|null, player: string}
     */
    private static function slot(int $teamId, int $wins, int $losses): array
    {
        return [
            'pick' => $teamId, 'teamId' => $teamId, 'teamName' => 'T' . $teamId, 'wins' => $wins, 'losses' => $losses,
            'color1' => '000000', 'color2' => 'FFFFFF', 'ownerId' => $teamId, 'ownerName' => 'T' . $teamId,
            'ownerColor1' => '000000', 'ownerColor2' => 'FFFFFF', 'isTraded' => false, 'notes' => '', 'movement' => null, 'player' => '',
        ];
    }
}
