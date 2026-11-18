<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Tuning;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use Trading\Contracts\TradeFormRepositoryInterface;
use Trading\Contracts\TradeValidatorInterface;
use TradeSuggestions\Engine\AssetPool;
use TradeSuggestions\Engine\CandidateGenerator;
use TradeSuggestions\Engine\Contracts\LeagueSnapshotProviderInterface;
use TradeSuggestions\Engine\DealRanker;
use TradeSuggestions\Veto\DealView;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\RankOutcome;
use TradeSuggestions\Engine\Shape\Contracts\DealShapeInterface;
use TradeSuggestions\SuggestionValidationFilter;
use TradeSuggestions\TradeCandidate;
use TradeSuggestions\Tuning\Contracts\TuningFeedbackRepositoryInterface;
use TradeSuggestions\Tuning\TuningAssembler;
use TradeSuggestions\Tuning\TuningBudget;
use TradeSuggestions\Tuning\TuningFeedback;
use TradeSuggestions\Tuning\TuningFilter;
use TradeSuggestions\Tuning\TuningReport;
use TradeSuggestions\Tuning\TuningRow;
use TradeSuggestions\Tuning\TuningStatus;
use TradeSuggestions\Veto\Contracts\VetoInterface;
use TradeSuggestions\Veto\VetoSet;
use Validation\ValidationResult;
use Validation\ValidationResultWithContext;

/**
 * The real generator loop, scorer, hide filter and ranker over the 28-team
 * fixture league; only the deal shape, the trade validator and the feedback
 * store are fakes.
 */
final class TuningAssemblerTest extends TestCase
{
    public const string SIM_KEY = 'tuning-test-sim';

    /** A fixture team whose one-for-one swaps fill all three selected slots. */
    private const int TEAM = 3;

    private static ?LeagueSnapshot $league = null;

    /** @var list<int> user tids the shape was asked for, one per generate call */
    private array $shapeCalls = [];

    private int $snapshotReads = 0;

    /** @var list<list<int>> */
    private array $feedbackCalls = [];

    public function testOneTeamFilterRunsOnlyThatTeam(): void
    {
        $report = $this->assembler()->build(self::filter(7));

        self::assertSame([7], self::sectionTids($report));
        self::assertSame([7], array_values(array_unique($this->shapeCalls)));
    }

    public function testAllTeamsRunsEveryRealFranchiseOnceInTidOrder(): void
    {
        $report = $this->assembler()->build(self::filter(null));

        self::assertSame(range(1, 28), self::sectionTids($report));
        self::assertFalse($report->truncated);
    }

    public function testSnapshotIsReadOnceForTheWholeRun(): void
    {
        $this->assembler()->build(self::filter(null));

        self::assertSame(1, $this->snapshotReads);
    }

    public function testEveryScoredDealBecomesExactlyOneRow(): void
    {
        $report = $this->assembler(perPartner: 36)->build(self::filter(self::TEAM));
        $section = $report->sections[0];

        self::assertNotSame([], $section->rows);
        self::assertSame(count($section->generation->candidates), count($section->rows) + $section->omittedRows);
        $keys = array_map(static fn (TuningRow $r): string => $r->dealKey, $section->rows);
        self::assertSame($keys, array_values(array_unique($keys)));
    }

    public function testSelectedRowsComeFirstWithRanks(): void
    {
        $rows = $this->assembler(perPartner: 36)->build(self::filter(self::TEAM))->sections[0]->rows;

        $selected = array_values(array_filter($rows, static fn (TuningRow $r): bool => $r->outcome === RankOutcome::Selected));
        self::assertNotSame([], $selected);
        self::assertSame($selected, array_slice($rows, 0, count($selected)));
        self::assertSame(range(1, count($selected)), array_map(static fn (TuningRow $r): ?int => $r->rank, $selected));
    }

    public function testVetoedRowListsFiredIds(): void
    {
        // Vetoed rows sort after the not-both-positive ones, so read them through the filter.
        $rows = $this->assembler(perPartner: 36, vetoes: new VetoSet([self::alwaysVeto()]))
            ->build(self::filter(self::TEAM, TuningStatus::Vetoed))->sections[0]->rows;

        $vetoed = array_values(array_filter($rows, static fn (TuningRow $r): bool => $r->outcome === RankOutcome::Vetoed));
        self::assertNotSame([], $vetoed);
        foreach ($vetoed as $row) {
            self::assertSame(['ALWAYS'], $row->firedVetoIds);
        }
    }

    public function testStatusShownKeepsOnlySelected(): void
    {
        $rows = $this->assembler(perPartner: 36)->build(self::filter(self::TEAM, TuningStatus::Shown))->sections[0]->rows;

        self::assertNotSame([], $rows);
        self::assertSame([RankOutcome::Selected], self::outcomes($rows));
    }

    public function testStatusVetoedKeepsOnlyVetoed(): void
    {
        $rows = $this->assembler(perPartner: 36, vetoes: new VetoSet([self::alwaysVeto()]))
            ->build(self::filter(self::TEAM, TuningStatus::Vetoed))->sections[0]->rows;

        self::assertNotSame([], $rows);
        self::assertSame([RankOutcome::Vetoed], self::outcomes($rows));
    }

    public function testTeamWithZeroCandidatesYieldsEmptySection(): void
    {
        $report = $this->assembler(perPartner: 0)->build(self::filter(1));

        self::assertCount(1, $report->sections);
        self::assertSame([], $report->sections[0]->rows);
        self::assertSame(0, $report->sections[0]->omittedRows);
    }

    public function testRowsPerTeamCapAtSixtyAndOmittedIsCounted(): void
    {
        $over = $this->assembler(perPartner: 36, total: 100)->build(self::filter(1))->sections[0];
        self::assertCount(100, $over->generation->candidates);
        self::assertCount(TuningBudget::MAX_ROWS_PER_TEAM, $over->rows);
        self::assertSame(40, $over->omittedRows);

        $exact = $this->assembler(perPartner: 36, total: 60)->build(self::filter(1))->sections[0];
        self::assertCount(60, $exact->generation->candidates);
        self::assertCount(60, $exact->rows);
        self::assertSame(0, $exact->omittedRows);
    }

    public function testTimeBudgetStopsBetweenTeamsAndNamesTheRest(): void
    {
        // Call 1 is the start; calls 2 and 3 come before teams 2 and 3; call 4,
        // before team 4, is past the budget.
        $calls = 0;
        $clock = static function () use (&$calls): float {
            $calls++;

            return $calls >= 4 ? 21.0 : 0.0;
        };
        $report = $this->assembler(clock: $clock)->build(self::filter(null));

        self::assertTrue($report->truncated);
        self::assertSame([1, 2, 3], self::sectionTids($report));
        self::assertSame(range(4, 28), $report->teamsNotRun);
    }

    public function testFirstTeamAlwaysCompletesEvenWhenOverBudget(): void
    {
        $calls = 0;
        $clock = static function () use (&$calls): float {
            $calls++;

            return $calls === 1 ? 0.0 : 30.0;
        };
        $report = $this->assembler(clock: $clock)->build(self::filter(null));

        self::assertSame([1], self::sectionTids($report));
        self::assertTrue($report->truncated);
        self::assertSame(range(2, 28), $report->teamsNotRun);
    }

    public function testConfigCarriesSeasonWeightsExposureKAndVetoIds(): void
    {
        $config = $this->assembler()->build(self::filter(1))->config;

        self::assertSame(VetoSet::standard()->ids(), $config['veto_ids']);
        self::assertSame(\TradeSuggestions\Valuation\GainWeights::EXPOSURE_K, $config['exposure_k']);
        self::assertSame(\TradeSuggestions\Valuation\GainWeights::SEASON_WEIGHTS['win_now'], self::arrayAt($config, 'season_weights')['win_now']);
        self::assertArrayHasKey('candidate_budget', $config);
        self::assertArrayHasKey('ranker', $config);
        self::assertArrayHasKey('lopsided_ratio', $config);
        self::assertSame(TuningBudget::toArray(), $config['tuning_budget']);
    }

    public function testFeedbackIsFetchedOnceForAllTeams(): void
    {
        $this->assembler()->build(self::filter(null));

        self::assertSame([range(1, 28)], $this->feedbackCalls);
    }

    public function testHiddenRowCarriesTheGmFeedback(): void
    {
        $plain = $this->assembler(perPartner: 36)->build(self::filter(self::TEAM))->sections[0]->rows;
        $target = $plain[0];

        $hides = [self::hide(self::TEAM, $target->dealKey, 'no-such-core')];
        $hidden = $this->assembler(perPartner: 36, hides: $hides)
            ->build(self::filter(self::TEAM, TuningStatus::Hidden))->sections[0]->rows;
        $all = $this->assembler(perPartner: 36, hides: $hides)
            ->build(self::filter(self::TEAM))->sections[0]->rows;

        self::assertCount(1, $hidden);
        self::assertSame($target->dealKey, $hidden[0]->dealKey);
        self::assertSame('too pricey', $hidden[0]->feedback?->freeText);
        self::assertSame([], array_values(array_filter($all, static fn (TuningRow $r): bool => $r->feedback !== null)));
    }

    public function testHiddenDealsAreDroppedBeforeRankingAndMarkedHidden(): void
    {
        $plain = $this->assembler(perPartner: 36)->build(self::filter(self::TEAM))->sections[0]->rows;
        $top = $plain[0];
        self::assertSame(RankOutcome::Selected, $top->outcome);
        self::assertSame(1, $top->rank);

        $hides = [self::hide(self::TEAM, 'no-such-deal', $top->coreKey)];
        $rows = $this->assembler(perPartner: 36, hides: $hides)
            ->build(self::filter(self::TEAM))->sections[0]->rows;
        $hiddenRows = $this->assembler(perPartner: 36, hides: $hides)
            ->build(self::filter(self::TEAM, TuningStatus::Hidden))->sections[0]->rows;

        $hidden = array_values(array_filter($hiddenRows, static fn (TuningRow $r): bool => $r->dealKey === $top->dealKey));
        self::assertCount(1, $hidden);
        self::assertSame(RankOutcome::Hidden, $hidden[0]->outcome);
        self::assertNull($hidden[0]->rank);
        self::assertNotSame($top->dealKey, $rows[0]->dealKey);
        self::assertSame(RankOutcome::Selected, $rows[0]->outcome);
        self::assertSame(1, $rows[0]->rank);
    }

    public function testStatusHiddenKeepsOnlyHiddenRows(): void
    {
        $report = $this->assembler(perPartner: 36)->build(self::filter(self::TEAM, TuningStatus::Hidden));

        self::assertCount(1, $report->sections);
        self::assertSame([], $report->sections[0]->rows);
    }

    public function testUnmatchedFeedbackIsListedPerTeam(): void
    {
        $target = $this->assembler(perPartner: 36)->build(self::filter(self::TEAM))->sections[0]->rows[0];
        $hides = [
            self::hide(self::TEAM, $target->dealKey, 'no-such-core'),
            self::hide(self::TEAM, 'gone-deal', 'gone-core'),
            self::hide(self::TEAM + 1, 'other-deal', 'other-core'),
        ];

        // The status filter drops the matched (hidden) row from view; its hide
        // still counts as matched.
        $section = $this->assembler(perPartner: 36, hides: $hides)
            ->build(self::filter(self::TEAM, TuningStatus::Shown))->sections[0];

        self::assertCount(1, $section->unmatchedFeedback);
        self::assertSame('gone-deal', $section->unmatchedFeedback[0]->dealKey);
    }

    private static function league(): LeagueSnapshot
    {
        return self::$league ??= SnapshotFactory::league();
    }

    private static function filter(?int $tid, TuningStatus $status = TuningStatus::All): TuningFilter
    {
        return new TuningFilter($tid, $status, []);
    }

    /**
     * @param list<TuningFeedback> $hides
     * @param (\Closure(): float)|null $clock
     */
    private function assembler(
        int $perPartner = 1,
        ?int $total = null,
        ?VetoSet $vetoes = null,
        array $hides = [],
        ?\Closure $clock = null,
    ): TuningAssembler {
        $league = self::league();
        $snapshots = new class ($league, $this->snapshotReads) implements LeagueSnapshotProviderInterface {
            private ?string $key = null;

            public function __construct(private readonly LeagueSnapshot $league, private int &$reads)
            {
            }

            public function snapshot(): LeagueSnapshot
            {
                $this->reads++;
                $this->key = TuningAssemblerTest::SIM_KEY;

                return $this->league;
            }

            public function lastKey(): ?string
            {
                return $this->key;
            }
        };

        $feedback = new class ($hides, $this->feedbackCalls) implements TuningFeedbackRepositoryInterface {
            /**
             * @param list<TuningFeedback> $hides
             * @param list<list<int>> $calls
             */
            public function __construct(private readonly array $hides, private array &$calls)
            {
            }

            public function forTeams(array $tids): array
            {
                $this->calls[] = $tids;

                return array_values(array_filter($this->hides, static fn (TuningFeedback $f): bool => in_array($f->tid, $tids, true)));
            }
        };

        $formRepo = $this->formRepo($league);
        $validator = self::createStub(TradeValidatorInterface::class);
        $validator->method('validateSalaryCaps')->willReturn(ValidationResultWithContext::success(['userPostTradeCapTotal' => 0, 'partnerPostTradeCapTotal' => 0]));
        $validator->method('validateRosterLimits')->willReturn(ValidationResult::success());
        $validator->method('canPlayerBeTraded')->willReturn(true);
        $capCalculator = self::createStub(\Trading\TradeCapCalculator::class);
        $capCalculator->method('calculateSalaryCapData')->willReturn([
            'userCurrentSeasonCapTotal' => 0,
            'partnerCurrentSeasonCapTotal' => 0,
            'userCapSentToPartner' => 0,
            'partnerCapSentToUser' => 0,
        ]);
        $season = new \Tests\WideUnit\Mocks\Season(self::createStub(\mysqli::class));
        $season->endingYear = SnapshotFactory::SEASON_YEAR;

        return new TuningAssembler(
            $snapshots,
            new CandidateGenerator([$this->shape($perPartner, $total)]),
            $vetoes ?? VetoSet::standard(),
            new DealRanker(),
            new SuggestionValidationFilter($validator, $capCalculator, $formRepo, $season),
            $formRepo,
            $feedback,
            $clock ?? static fn (): float => 0.0,
        );
    }

    /**
     * One-for-one swaps from the pool, at most `$perPartner` per partner and
     * `$total` per team.
     */
    private function shape(int $perPartner, ?int $total): DealShapeInterface
    {
        return new class ($perPartner, $total, $this->shapeCalls) implements DealShapeInterface {
            /** @var array<int, int> user tid => candidates yielded */
            private array $yielded = [];

            /**
             * @param list<int> $calls
             */
            public function __construct(private readonly int $perPartner, private readonly ?int $total, private array &$calls)
            {
            }

            public function id(): string
            {
                return 'ONE_FOR_ONE';
            }

            public function generate(LeagueSnapshot $s, int $userTid, int $partnerTid, AssetPool $pool): iterable
            {
                $this->calls[] = $userTid;
                $here = 0;
                foreach ($pool->userPids as $give) {
                    foreach ($pool->partnerPids as $get) {
                        if ($here === $this->perPartner) {
                            return;
                        }
                        if ($this->total !== null && ($this->yielded[$userTid] ?? 0) === $this->total) {
                            return;
                        }
                        $here++;
                        $this->yielded[$userTid] = ($this->yielded[$userTid] ?? 0) + 1;
                        yield new TradeCandidate($userTid, $partnerTid, [$give], [], [$get], []);
                    }
                }
            }
        };
    }

    private static function alwaysVeto(): VetoInterface
    {
        return new class () implements VetoInterface {
            public function id(): string
            {
                return 'ALWAYS';
            }

            public function fires(DealView $deal): bool
            {
                return true;
            }
        };
    }

    private static function hide(int $tid, string $dealKey, string $coreKey): TuningFeedback
    {
        return new TuningFeedback($tid, 2, $dealKey, $coreKey, '{}', ['TOO_OLD'], 'too pricey', '2026-11-18 00:00:00');
    }

    private function formRepo(LeagueSnapshot $league): TradeFormRepositoryInterface
    {
        $formRepo = self::createStub(TradeFormRepositoryInterface::class);
        $rows = [];
        foreach ($league->teamNames as $tid => $name) {
            $rows[] = ['teamid' => $tid, 'team_name' => $name, 'team_city' => 'City', 'color1' => '000000', 'color2' => 'FFFFFF'];
        }
        $formRepo->method('getAllTeamsWithCity')->willReturn($rows);
        $formRepo->method('getTeamPlayersForTrading')->willReturnCallback(
            static function (int $tid) use ($league): array {
                $pids = array_keys(array_filter($league->teamOfPid, static fn (int $t): bool => $t === $tid));
                sort($pids);

                return array_map(static fn (int $pid): array => ['pid' => $pid, 'cy' => 1, 'salary_yr1' => 100], $pids);
            },
        );
        $formRepo->method('getTeamDraftPicksForTrading')->willReturn([]);

        return $formRepo;
    }

    /**
     * @return list<int>
     */
    private static function sectionTids(TuningReport $report): array
    {
        return array_map(static fn ($s): int => $s->tid, $report->sections);
    }

    /**
     * @param list<TuningRow> $rows
     * @return list<RankOutcome>
     */
    private static function outcomes(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!in_array($row->outcome, $out, true)) {
                $out[] = $row->outcome;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private static function arrayAt(array $config, string $key): array
    {
        $value = $config[$key] ?? null;
        self::assertIsArray($value);

        return $value;
    }
}
