<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Cache\DatabaseCache;
use League\League;
use PHPUnit\Framework\Attributes\Group;
use ProjectedDraftOrder\ProjectedDraftOrderRepository;
use ProjectedDraftOrder\ProjectedDraftOrderService;
use Repositories\TeamIdentityRepository;
use Settings\SettingsService;
use Standings\StandingsRepository;
use Tests\TradeSuggestions\Fixtures\LaughTestRows;
use Tests\TradeSuggestions\LaughTest\Fixtures\LaughTestFactory;
use Tests\WideUnit\Mocks\Season;
use TradeSuggestions\Contracts\TradeSuggestionsServiceInterface;
use TradeSuggestions\Engine\CachedLeagueSnapshotProvider;
use TradeSuggestions\Engine\CandidateGenerator;
use TradeSuggestions\Engine\Contracts\LeagueSnapshotProviderInterface;
use TradeSuggestions\Engine\DealRanker;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\LeagueSnapshotLoader;
use TradeSuggestions\Engine\ModeResolver;
use TradeSuggestions\Engine\Shape\ChangeOfScenery;
use TradeSuggestions\Engine\Shape\DumpPaidWithPick;
use TradeSuggestions\Engine\Shape\ExpiringForLong;
use TradeSuggestions\Engine\Shape\OneForOne;
use TradeSuggestions\Engine\Shape\OneForTwo;
use TradeSuggestions\Engine\Shape\PickSwap;
use TradeSuggestions\Engine\Shape\StarForYoungAndPicks;
use TradeSuggestions\Engine\Shape\TwoForOne;
use TradeSuggestions\Engine\SnapshotKeyRepository;
use TradeSuggestions\Engine\Sweep\LeagueSweepArgs;
use TradeSuggestions\Engine\Sweep\LeagueSweepCommand;
use TradeSuggestions\Projection\ProjectionPlayerRepository;
use TradeSuggestions\ResolvedTeamMode;
use TradeSuggestions\SuggestionAssetRepository;
use TradeSuggestions\SuggestionDiagnostics;
use TradeSuggestions\SuggestionItem;
use TradeSuggestions\SuggestionLink;
use TradeSuggestions\SuggestionValidationFilter;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TeamModeGuesser;
use TradeSuggestions\TeamModeRepository;
use TradeSuggestions\TeamModeService;
use TradeSuggestions\TradeCandidate;
use TradeSuggestions\TradeSuggestionsPanelData;
use TradeSuggestions\TradeSuggestionsService;
use TradeSuggestions\Valuation\ValuationTeamFactorsRepository;
use TradeSuggestions\Veto\VetoSet;
use Trading\BuyoutLedgerRepository;
use Trading\Contracts\TradeFormRepositoryInterface;
use Trading\TradeCapCalculator;
use Trading\TradeFormRepository;
use Trading\TradeValidator;

/**
 * Runs the whole v3 engine over the database, with the real TradeValidator,
 * cap calculator and repositories wired as NewsFactory wires them.
 *
 * Staged rows (inside the test transaction): a bench player on team 1 whose
 * salary puts team 1's effective payroll at 7100, team 2 set to rebuild, and
 * a round 2 pick owned by team 1 for next year.
 */
#[Group('database')]
class TradeSuggestionsEngineInvariantTest extends DatabaseTestCase
{
    private const int USER_TID = 1;
    private const int REBUILD_TID = 2;
    private const int STAGED_PAYROLL = 7100;
    private const int DUMP_PID = 200201;

    private Season $season;
    private TradeSuggestionsService $service;
    private CachedLeagueSnapshotProvider $snapshots;
    private TradeFormRepository $formRepo;
    private VetoSet $vetoes;

    /** @var array<int, string> */
    private array $teamNames = [];

    /** @var array<mixed> */
    private array $panelStore = [];

    protected function setUp(): void
    {
        parent::setUp();

        // The mock Season pins 2024 and Regular Season. Read the league's real
        // year and phase so picks and last-season stats line up with the data.
        $this->season = new Season($this->db);
        $result = $this->db->query("SELECT setting_key, setting_value FROM ibl_settings WHERE league = 'ibl' AND setting_key IN ('Current Season Ending Year', 'Current Season Phase')");
        $this->assertInstanceOf(\mysqli_result::class, $result);
        foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
            $value = is_string($row['setting_value']) ? $row['setting_value'] : '';
            if ($row['setting_key'] === 'Current Season Ending Year' && is_numeric($value)) {
                $this->season->endingYear = (int) $value;
                $this->season->beginningYear = (int) $value - 1;
            } elseif ($row['setting_key'] === 'Current Season Phase' && $value !== '') {
                $this->season->phase = $value;
            }
        }
        $this->formRepo = new TradeFormRepository($this->db);
        $teamModeRepo = new TeamModeRepository($this->db);
        $assetRepo = new SuggestionAssetRepository($this->db);
        $draftOrder = new ProjectedDraftOrderService(new ProjectedDraftOrderRepository($this->db));

        // One resolver for the loader, the provider and the service, as in NewsFactory.
        $modes = new ModeResolver(
            $assetRepo,
            $draftOrder,
            $teamModeRepo,
            $this->formRepo,
            new TeamModeService($teamModeRepo, new TeamModeGuesser()),
            $this->season,
        );
        $this->snapshots = new CachedLeagueSnapshotProvider(
            new LeagueSnapshotLoader(
                new ProjectionPlayerRepository($this->db),
                new ValuationTeamFactorsRepository($this->db),
                new StandingsRepository($this->db),
                $draftOrder,
                $assetRepo,
                $this->formRepo,
                $modes,
                $this->season,
            ),
            new DatabaseCache($this->db),
            new SnapshotKeyRepository($this->db),
            $modes,
            $this->season,
        );
        $this->vetoes = VetoSet::standard();
        $this->service = new TradeSuggestionsService(
            $this->snapshots,
            new CandidateGenerator([
                new OneForOne(),
                new TwoForOne(),
                new OneForTwo(),
                new StarForYoungAndPicks(),
                new DumpPaidWithPick(),
                new ExpiringForLong(),
                new PickSwap(),
                new ChangeOfScenery(),
            ]),
            $this->vetoes,
            new DealRanker(),
            $this->newFilter(),
            $this->formRepo,
            new SettingsService($this->season),
            $modes,
            new \TradeSuggestions\Feedback\FeedbackRepository($this->db),
            new \TradeSuggestions\Feedback\PanelSessionState($this->panelStore),
        );

        foreach ($this->formRepo->getAllTeamsWithCity() as $team) {
            $this->teamNames[$team['teamid']] = $team['team_name'];
        }
    }

    public function testOverCapUserGetsAtLeastOneValidDump(): void
    {
        $this->stageOverCapUser();

        $panel = $this->service->buildForTeam(self::USER_TID);

        $this->assertNotNull($panel);
        $this->assertGreaterThanOrEqual(1, count($panel->items), (string) json_encode($panel->diagnostics));
    }

    public function testEverySuggestionPassesRealValidator(): void
    {
        $this->stageOverCapUser();

        $checked = 0;
        foreach (array_keys($this->teamNames) as $tid) {
            $panel = $this->service->buildForTeam($tid);
            $this->assertNotNull($panel);
            foreach ($panel->items as $item) {
                $candidate = $this->candidateFromPrefill($tid, $item);
                $filter = $this->newFilter();
                $data = $filter->buildTradeFormData(
                    $this->teamNames[$tid],
                    $item->partnerTeamName,
                    $tid,
                    $candidate->partnerTid,
                    $candidate,
                );
                $this->assertNull(
                    $filter->rejection($data, $tid, $candidate->partnerTid, $candidate),
                    'Team ' . $tid . ' suggestion ' . $item->prefillUrl . ' failed the real validator',
                );
                $checked++;
            }
        }

        // The staged dump guarantees team 1 has an item, so this never passes vacuously.
        $this->assertGreaterThanOrEqual(1, $checked);
    }

    public function testOverCapUserLandsAtOrUnderHardCap(): void
    {
        $this->stageOverCapUser();
        $this->assertSame(self::STAGED_PAYROLL, $this->payroll(self::USER_TID));

        $panel = $this->service->buildForTeam(self::USER_TID);

        $this->assertNotNull($panel);
        $this->assertNotSame([], $panel->items);
        foreach ($panel->items as $item) {
            $this->assertLessThanOrEqual(
                League::HARD_CAP_MAX,
                $this->payrollAfter(self::USER_TID, $this->candidateFromPrefill(self::USER_TID, $item)),
                'Suggestion ' . $item->prefillUrl . ' leaves team 1 over the hard cap',
            );
        }
    }

    /**
     * One tab-separated line per team: tid, effective payroll, suggestion
     * count, then each suggestion's partner, payroll after (over-cap teams
     * only) and user reason. Ends with a summary line. Asserts that every
     * over-cap team's suggestions land at or under the hard cap.
     *
     * Runs against whatever database DB_NAME names, so the same test prints
     * the prod-copy sweep on a worktree stack.
     */
    public function testLeagueSweepReport(): void
    {
        $teams = 0;
        $suggestions = 0;
        $overCap = 0;
        $overCapServed = 0;
        $lines = [];
        foreach ($this->teamNames as $tid => $name) {
            $panel = $this->service->buildForTeam($tid);
            $this->assertNotNull($panel);
            $payroll = $this->payroll($tid);
            $isOver = $payroll > League::HARD_CAP_MAX;
            $teams++;
            $suggestions += count($panel->items);
            if ($isOver) {
                $overCap++;
                if ($panel->items !== []) {
                    $overCapServed++;
                }
            }

            $cells = [(string) $tid, $name, (string) $payroll, (string) count($panel->items)];
            foreach ($panel->items as $item) {
                $after = '';
                if ($isOver) {
                    $afterPayroll = $this->payrollAfter($tid, $this->candidateFromPrefill($tid, $item));
                    $this->assertLessThanOrEqual(League::HARD_CAP_MAX, $afterPayroll, 'Team ' . $tid . ' suggestion ' . $item->prefillUrl);
                    $after = ' after=' . $afterPayroll;
                }
                $cells[] = $item->partnerTeamName . $after . ': ' . $item->userReason;
            }
            $lines[] = implode("\t", $cells);
        }
        $lines[] = sprintf('summary teams=%d suggestions=%d over_cap=%d over_cap_served=%d', $teams, $suggestions, $overCap, $overCapServed);

        fwrite(STDOUT, "\n" . implode("\n", $lines) . "\n");
        $this->assertGreaterThan(0, $teams);
    }

    public function testLeagueSweepHasZeroVetoViolations(): void
    {
        $this->stageOverCapUser();
        $out = fopen('php://memory', 'w+');
        $this->assertIsResource($out);

        $code = (new LeagueSweepCommand($this->service, $this->snapshots, $this->vetoes, $this->formRepo))
            ->run(League::MAX_REAL_TEAMID, $out);

        rewind($out);
        $lines = explode(PHP_EOL, trim((string) stream_get_contents($out)));
        $last = end($lines);
        $this->assertIsString($last);
        $this->assertMatchesRegularExpression('/^TEAMS=[1-9][0-9]* IDEAS=\d+ VETOED=\d+ SHOWN=[1-9][0-9]* VIOLATIONS=0$/', $last);
        $this->assertSame(0, $code, implode("\n", $lines));
    }

    public function testStagedDupreeShapeIsNeverShown(): void
    {
        $this->stageLaughTestPlayer(LaughTestRows::dupree(), self::USER_TID);
        $this->stageLaughTestPlayer(LaughTestRows::laettner(), self::REBUILD_TID);

        $panel = $this->service->buildForTeam(self::USER_TID);

        $this->assertNotNull($panel);
        foreach ($panel->items as $item) {
            $labels = [...$item->giveLabels, ...$item->getLabels];
            $this->assertFalse(
                in_array('Dupree', $labels, true) && in_array('Laettner', $labels, true),
                'Dupree-for-Laettner shown: ' . $item->prefillUrl,
            );
        }
    }

    public function testSweepCommandRejectsSpaceFormFlag(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        LeagueSweepArgs::parse(['x', '--max-teams', '4']);
    }

    public function testSweepExitsOneOnViolation(): void
    {
        // The unit laugh-test league, where Dupree for Laettner straight up
        // fires a veto. The service double shows exactly that deal.
        $league = LaughTestFactory::league();
        $deal = new TradeCandidate(
            LaughTestRows::USER_TID,
            LaughTestRows::PARTNER_TID,
            [LaughTestRows::DUPREE_PID],
            [],
            [LaughTestRows::LAETTNER_PID],
            [],
        );
        $partnerName = $league->teamNames[LaughTestRows::PARTNER_TID];
        $item = new SuggestionItem($partnerName, ['Dupree'], ['Laettner'], 'u', 'p', SuggestionLink::forCandidate($partnerName, $deal), str_repeat('a', 40), str_repeat('b', 40), []);
        $panel = new TradeSuggestionsPanelData(
            new ResolvedTeamMode(TeamMode::WinNow, TeamMode::WinNow, false),
            [$item],
            new SuggestionDiagnostics(1, 1, [], 1, []),
        );

        $service = self::createStub(TradeSuggestionsServiceInterface::class);
        $service->method('buildForTeam')->willReturnCallback(
            static fn (int $tid): ?TradeSuggestionsPanelData => $tid === LaughTestRows::USER_TID ? $panel : null,
        );
        $snapshots = self::createStub(LeagueSnapshotProviderInterface::class);
        $snapshots->method('snapshot')->willReturn($league);
        $formRepo = self::createStub(TradeFormRepositoryInterface::class);
        $formRepo->method('getAllTeamsWithCity')->willReturn(self::teamRows($league));
        $out = fopen('php://memory', 'w+');
        $this->assertIsResource($out);

        $code = (new LeagueSweepCommand($service, $snapshots, VetoSet::standard(), $formRepo))->run(2, $out);

        rewind($out);
        $text = (string) stream_get_contents($out);
        $this->assertSame(1, $code, $text);
        $this->assertStringContainsString('VIOLATIONS=1', $text);
    }

    /**
     * @return list<array{teamid: int, team_name: string, team_city: string, color1: string, color2: string}>
     */
    private static function teamRows(LeagueSnapshot $league): array
    {
        $rows = [];
        foreach ($league->teamNames as $tid => $name) {
            $rows[] = ['teamid' => $tid, 'team_name' => $name, 'team_city' => '', 'color1' => '', 'color2' => ''];
        }

        return $rows;
    }

    /**
     * @param array{pid: int, name: string, pos: string, age: int, cy: int, salaries: list<int>, ratings: array<string, int>, injured: int, statsPts: int, minutes: int} $spec
     */
    private function stageLaughTestPlayer(array $spec, int $tid): void
    {
        $row = LaughTestRows::asPlrRow($spec, $tid);
        unset($row['pid'], $row['name']);
        $this->insertTestPlayer($spec['pid'], $spec['name'], $row);
    }

    private function stageOverCapUser(): void
    {
        $payroll = $this->payroll(self::USER_TID);
        $this->assertLessThan(self::STAGED_PAYROLL, $payroll, 'Seed payroll for team 1 is already above the staged 7100');

        $this->insertTestPlayer(self::DUMP_PID, 'Suggest Dump Bench', [
            'teamid' => self::USER_TID,
            // Same slot as the seed's lone team 1 player (pid 1, PG), so one
            // of the two sits on the bench and the dump empties no slot.
            'pos' => 'PG',
            'age' => 30,
            'cy' => 1,
            'cyt' => 3,
            'salary_yr1' => self::STAGED_PAYROLL - $payroll,
            'salary_yr2' => self::STAGED_PAYROLL - $payroll,
            'salary_yr3' => self::STAGED_PAYROLL - $payroll,
            'dc_pg_depth' => 0,
            'dc_sg_depth' => 0,
            'dc_sf_depth' => 0,
            'dc_pf_depth' => 0,
            'dc_c_depth' => 0,
            'dc_minutes' => 0,
        ]);
        $this->db->query("UPDATE ibl_team_info SET trade_mode = 'rebuild' WHERE teamid = " . self::REBUILD_TID);
        $this->insertDraftPickRow(self::USER_TID, self::USER_TID, $this->season->endingYear + 1, 2);
    }

    /**
     * A filter caches each team's form rows on first use, so one built before
     * the staged rows would read stale salaries. Every caller gets its own.
     */
    private function newFilter(): SuggestionValidationFilter
    {
        $validator = new TradeValidator($this->db, $this->season);

        return new SuggestionValidationFilter(
            $validator,
            new TradeCapCalculator(new TeamIdentityRepository($this->db), new BuyoutLedgerRepository($this->db), $this->season, $validator),
            new TradeFormRepository($this->db),
            $this->season,
        );
    }

    /**
     * Effective-year payroll, read the way the engine reads it.
     */
    private function payroll(int $tid): int
    {
        return League::HARD_CAP_MAX - $this->newFilter()->capRoom($this->teamNames[$tid] ?? '', $tid);
    }

    private function payrollAfter(int $tid, TradeCandidate $c): int
    {
        return $this->payroll($tid) - $this->salaryOf($c->userPids) + $this->salaryOf($c->partnerPids);
    }

    /**
     * @param list<int> $pids
     */
    private function salaryOf(array $pids): int
    {
        if ($pids === []) {
            return 0;
        }
        $total = 0;
        $result = $this->db->query('SELECT cy, salary_yr1, salary_yr2, salary_yr3, salary_yr4, salary_yr5, salary_yr6 FROM ibl_plr WHERE pid IN (' . implode(',', $pids) . ')');
        $this->assertInstanceOf(\mysqli_result::class, $result);
        foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
            $cy = is_int($row['cy']) ? $row['cy'] : 0;
            $salary = $row['salary_yr' . $cy] ?? 0;
            $total += is_int($salary) ? $salary : 0;
        }

        return $total;
    }

    /**
     * The sweep command's parser, checked against the database's owners.
     */
    private function candidateFromPrefill(int $tid, SuggestionItem $item): TradeCandidate
    {
        $candidate = LeagueSweepCommand::candidateFromPrefill($tid, $item, array_flip($this->teamNames), $this->snapshots->snapshot());
        foreach ($candidate->userPids as $pid) {
            $this->assertSame($tid, $this->ownerOf('SELECT teamid AS owner FROM ibl_plr WHERE pid = ' . $pid));
        }
        foreach ($candidate->partnerPids as $pid) {
            $this->assertSame($candidate->partnerTid, $this->ownerOf('SELECT teamid AS owner FROM ibl_plr WHERE pid = ' . $pid));
        }
        foreach ($candidate->userPickIds as $pickId) {
            $this->assertSame($tid, $this->ownerOf('SELECT owner_teamid AS owner FROM ibl_draft_picks WHERE pickid = ' . $pickId));
        }
        foreach ($candidate->partnerPickIds as $pickId) {
            $this->assertSame($candidate->partnerTid, $this->ownerOf('SELECT owner_teamid AS owner FROM ibl_draft_picks WHERE pickid = ' . $pickId));
        }

        return $candidate;
    }

    private function ownerOf(string $sql): int
    {
        $result = $this->db->query($sql);
        $this->assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        $this->assertIsArray($row);
        $this->assertIsInt($row['owner']);

        return $row['owner'];
    }
}
