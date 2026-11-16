<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use League\League;
use PHPUnit\Framework\Attributes\Group;
use ProjectedDraftOrder\ProjectedDraftOrderRepository;
use ProjectedDraftOrder\ProjectedDraftOrderService;
use Repositories\TeamIdentityRepository;
use Settings\SettingsService;
use Tests\WideUnit\Mocks\Season;
use TradeSuggestions\NeedJudge;
use TradeSuggestions\NeedPairMatcher;
use TradeSuggestions\NeedsCardBuilder;
use TradeSuggestions\PickValuer;
use TradeSuggestions\ReasonBuilder;
use TradeSuggestions\SuggestionAssetRepository;
use TradeSuggestions\SuggestionItem;
use TradeSuggestions\SuggestionRanker;
use TradeSuggestions\SuggestionValidationFilter;
use TradeSuggestions\TeamModeGuesser;
use TradeSuggestions\TeamModeRepository;
use TradeSuggestions\TeamModeService;
use TradeSuggestions\TradeCandidate;
use TradeSuggestions\TradeSuggestionsService;
use Trading\TradeCapCalculator;
use Trading\TradeFormRepository;
use Trading\TradeValidator;

/**
 * Runs the whole need-based engine over the database, with the real
 * TradeValidator, cap calculator and repositories wired as the News module
 * wires them.
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
    private const int DUMP_PID = 200000201;

    private Season $season;
    private TradeSuggestionsService $service;

    /** @var array<int, string> */
    private array $teamNames = [];

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
        $formRepo = new TradeFormRepository($this->db);
        $teamModeRepo = new TeamModeRepository($this->db);

        $this->service = new TradeSuggestionsService(
            new TeamModeService($teamModeRepo, new TeamModeGuesser()),
            $teamModeRepo,
            new SuggestionAssetRepository($this->db),
            new ProjectedDraftOrderService(new ProjectedDraftOrderRepository($this->db)),
            $formRepo,
            new SettingsService($this->season),
            $this->season,
            new PickValuer(),
            new NeedsCardBuilder(),
            new NeedPairMatcher(),
            new NeedJudge(),
            new ReasonBuilder(),
            $this->newFilter(),
            new SuggestionRanker(),
        );

        foreach ($formRepo->getAllTeamsWithCity() as $team) {
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
            new TradeCapCalculator(new TeamIdentityRepository($this->db), null, $this->season, $validator, $this->db),
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
     * Rebuilds the deal from the item's prefill link (`1:<pid>` and `0:<pickid>`
     * keys), assigning each asset to the side that owns it in the database.
     */
    private function candidateFromPrefill(int $tid, SuggestionItem $item): TradeCandidate
    {
        $partnerTid = array_search($item->partnerTeamName, $this->teamNames, true);
        $this->assertIsInt($partnerTid);

        $query = parse_url($item->prefillUrl, PHP_URL_QUERY);
        $this->assertIsString($query);
        parse_str($query, $params);
        $prefill = $params['prefill'] ?? '';
        $this->assertIsString($prefill);

        $userPids = [];
        $partnerPids = [];
        $userPicks = [];
        $partnerPicks = [];
        foreach (explode(',', $prefill) as $key) {
            [$type, $id] = array_map('intval', explode(':', $key));
            $owner = $type === 1
                ? $this->ownerOf('SELECT teamid AS owner FROM ibl_plr WHERE pid = ' . $id)
                : $this->ownerOf('SELECT owner_teamid AS owner FROM ibl_draft_picks WHERE pickid = ' . $id);
            $this->assertContains($owner, [$tid, $partnerTid], 'Prefill key ' . $key . ' belongs to neither side');
            if ($type === 1 && $owner === $tid) {
                $userPids[] = $id;
            } elseif ($type === 1) {
                $partnerPids[] = $id;
            } elseif ($owner === $tid) {
                $userPicks[] = $id;
            } else {
                $partnerPicks[] = $id;
            }
        }

        return new TradeCandidate($tid, $partnerTid, $userPids, $userPicks, $partnerPids, $partnerPicks);
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
