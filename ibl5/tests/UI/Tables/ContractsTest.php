<?php

declare(strict_types=1);

namespace Tests\UI\Tables;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Team\TeamTableService;
use UI\Tables\Contracts;

/**
 * ContractsTest - Tests for Contracts table rendering
 *
 * Covers the markExpiringRows flag introduced in Phase 5: rows whose player
 * has an expiring contract (cy === cyt) get the player-fa-expiring-row class
 * when the flag is true. Cash-consideration rows and the Cap Totals footer row
 * must never receive the class regardless of the flag.
 *
 * @covers \UI\Tables\Contracts
 */
#[AllowMockObjectsWithoutExpectations]
class ContractsTest extends TestCase
{
    private const RENDER_TAIL = "\n        ";

    /**
     * Build a minimal player row array suitable for Player::withPlrRow()
     *
     * All fields required by PlayerDataMapper::fillFromCurrentRow() are provided.
     * Supply cy === cyt to get an expiring player; cy < cyt for years remaining.
     *
     * @param int $pid Player ID
     * @param int $cy contractCurrentYear (maps to PlayerData::$contractCurrentYear)
     * @param int $cyt contractTotalYears (maps to PlayerData::$contractTotalYears)
     * @param int $teamid Team ID — must be > 0 for getNameStatusClass() to return a non-empty string
     * @param int $ordinal Roster ordinal — must be <= JsbConstants::WAIVERS_ORDINAL (960) to avoid 'player-waived'
     * @return array<string, mixed>
     */
    private function buildPlayerRow(
        int $pid = 1,
        int $cy = 2,
        int $cyt = 4,
        int $teamid = 1,
        int $ordinal = 1,
    ): array {
        return [
            'pid' => $pid,
            'name' => 'Test Player ' . $pid,
            'nickname' => '',
            'ordinal' => $ordinal,
            'teamid' => $teamid,
            'pos' => 'PG',
            'age' => 25,
            'peak' => null,
            'color1' => null,
            'color2' => null,
            'oo' => 0, 'od' => 0, 'r_drive_off' => 0, 'dd' => 0,
            'po' => 0, 'pd' => 0, 'r_trans_off' => 0, 'td' => 0,
            'clutch' => null, 'consistency' => null,
            'talent' => 75, 'skill' => 70, 'intangibles' => 65,
            'loyalty' => null, 'playing_time' => null, 'winner' => null,
            'tradition' => null, 'security' => null,
            'exp' => 1, 'bird' => 3,
            'cy' => $cy,
            'cyt' => $cyt,
            'salary_yr1' => 500,
            'salary_yr2' => 0,
            'salary_yr3' => 0,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
            'retired' => 0,
            'droptime' => 0,
            'injured' => null,
            'htft' => null, 'htin' => null, 'wt' => null,
            'draftyear' => 0, 'draftround' => 0, 'draftpickno' => 0,
            'draftedby' => '', 'draftedbycurrentname' => '', 'college' => '',
            'r_fga' => 0, 'r_fgp' => 0, 'r_fta' => 0, 'r_ftp' => 0,
            'r_3ga' => 0, 'r_3gp' => 0, 'r_orb' => 0, 'r_drb' => 0,
            'r_ast' => 0, 'r_stl' => 0, 'r_tvr' => 0, 'r_blk' => 0, 'r_foul' => 0,
            'isCashRow' => false,
        ];
    }

    /**
     * buildPlayerRow() with column values replaced (salaries, name, pos, and so on).
     *
     * @param array<string, mixed> $overrides Column values that replace the defaults
     * @return array<string, mixed>
     */
    private function buildPlayerRowWith(array $overrides, int $pid = 1, int $cy = 2, int $cyt = 4): array
    {
        return array_merge($this->buildPlayerRow(pid: $pid, cy: $cy, cyt: $cyt), $overrides);
    }

    /**
     * Build a Season in a given phase with endingYear 2025.
     *
     * The mock mirrors production's isOffseasonPhase() byte for byte, so Draft and
     * Free Agency shift the header year exactly as the real class would.
     */
    private function createPhaseSeason(string $phase): \Tests\WideUnit\Mocks\Season
    {
        $season = new \Tests\WideUnit\Mocks\Season(self::createStub(\mysqli::class));
        $season->phase = $phase;
        $season->endingYear = 2025;

        return $season;
    }

    /**
     * Assert the whole render() return value. render() closes its template with
     * "</table>" + newline + the eight spaces before the final <?php, so the return
     * value ends in that whitespace; a nowdoc cannot carry it through editor trimming.
     */
    private function assertRenderPinned(string $expected, string $html): void
    {
        self::assertSame($expected . self::RENDER_TAIL, $html);
    }

    /**
     * Create a stub Team object with colour properties set.
     *
     * @return \Team\Team&\PHPUnit\Framework\MockObject\Stub
     */
    private function createMockTeam(): \Team\Team
    {
        $team = self::createStub(\Team\Team::class);
        $team->color1 = 'FF0000';
        $team->color2 = '0000FF';
        $team->teamid = 1;

        return $team;
    }

    /**
     * Create a stub Season in a non-free-agency (Regular Season) state.
     *
     * isOffseasonPhase() returns false so Contracts::render() does NOT increment
     * endingYear and does NOT set $isFreeAgency, keeping the fixture simple.
     *
     * @return \Season\Season&\PHPUnit\Framework\MockObject\Stub
     */
    private function createMockSeason(): \Season\Season
    {
        $season = self::createStub(\Season\Season::class);
        $season->phase = 'Regular Season';
        $season->endingYear = 2025;
        $season->method('isOffseasonPhase')->willReturn(false);

        return $season;
    }

    /**
     * When markExpiringRows is true and the player's cy === cyt, the <tr> receives
     * the player-fa-expiring-row CSS class.
     */
    public function testRenderMarksExpiringContractRowWhenFlagEnabled(): void
    {
        $db = self::createStub(\mysqli::class);
        $team = $this->createMockTeam();
        $season = $this->createMockSeason();

        $result = [$this->buildPlayerRow(pid: 1, cy: 2, cyt: 2)];

        $html = Contracts::render($db, $result, $team, $season, markExpiringRows: true);

        $this->assertStringContainsString('player-fa-expiring-row', $html);
    }

    /**
     * When markExpiringRows is false (the default), the expiring row does NOT receive
     * the CSS class even if cy === cyt.
     */
    public function testRenderDoesNotMarkContractRowWhenFlagDisabled(): void
    {
        $db = self::createStub(\mysqli::class);
        $team = $this->createMockTeam();
        $season = $this->createMockSeason();

        $result = [$this->buildPlayerRow(pid: 1, cy: 2, cyt: 2)];

        $html = Contracts::render($db, $result, $team, $season);

        $this->assertStringNotContainsString('player-fa-expiring-row', $html);
    }

    /**
     * A player with years remaining (cy < cyt) must NOT receive the class even
     * when markExpiringRows is true — getNameStatusClass() returns '' for them.
     */
    public function testRenderDoesNotMarkPlayerWithYearsRemaining(): void
    {
        $db = self::createStub(\mysqli::class);
        $team = $this->createMockTeam();
        $season = $this->createMockSeason();

        $result = [$this->buildPlayerRow(pid: 1, cy: 2, cyt: 4)];

        $html = Contracts::render($db, $result, $team, $season, markExpiringRows: true);

        $this->assertStringNotContainsString('player-fa-expiring-row', $html);
    }

    /**
     * Cash-consideration rows (isCashRow === true) must NEVER receive the expiring
     * class, even when their cy / cyt would satisfy the expiring condition.
     *
     * Guards the !$row['isCashRow'] conditional in Contracts.php § 5.4: synthetic
     * cash rows have cy === cyt === 1 by default, so without the guard every cash
     * row would be incorrectly faded.
     */
    public function testRenderNeverMarksCashConsiderationRow(): void
    {
        $db = self::createStub(\mysqli::class);
        $team = $this->createMockTeam();
        $season = $this->createMockSeason();

        // Build a cash row via the production helper — cy=1, cyt=1 by design,
        // so getNameStatusClass() WOULD return 'player-expiring' without the guard.
        $cashRow = TeamTableService::cashConsiderationToRosterRow([
            'teamid' => 1,
            'label' => 'Cash Consideration',
            'cy' => 1,
            'cyt' => 1,
            'salary_yr1' => 500,
            'salary_yr2' => 0,
            'salary_yr3' => 0,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
        ]);

        $html = Contracts::render($db, [$cashRow], $team, $season, markExpiringRows: true);

        // The row carries data-cash-row (the isCashRow guard is working)
        $this->assertStringContainsString('data-cash-row', $html);
        // ...but must NOT carry the expiring-row class
        $this->assertStringNotContainsString('player-fa-expiring-row', $html);
    }

    /**
     * With one expiring player in the tbody and markExpiringRows true, exactly one
     * player-fa-expiring-row class should appear in the output, and it must belong
     * to a <tr> before the Cap Totals footer row — proving the class never reaches
     * the <tfoot>.
     */
    public function testRenderNeverMarksCapTotalsRow(): void
    {
        $db = self::createStub(\mysqli::class);
        $team = $this->createMockTeam();
        $season = $this->createMockSeason();

        $result = [$this->buildPlayerRow(pid: 1, cy: 3, cyt: 3)];

        $html = Contracts::render($db, $result, $team, $season, markExpiringRows: true);

        // Exactly one expiring row in the whole output
        $this->assertSame(1, substr_count($html, 'player-fa-expiring-row'));

        // The Cap Totals row must be present
        $capTotalsPos = strpos($html, 'Cap Totals');
        $this->assertNotFalse($capTotalsPos, 'Cap Totals row must be present in output');

        // The expiring class must appear BEFORE Cap Totals (tbody before tfoot)
        $expiringClassPos = strpos($html, 'player-fa-expiring-row');
        $this->assertNotFalse($expiringClassPos);
        $this->assertLessThan(
            $capTotalsPos,
            $expiringClassPos,
            'player-fa-expiring-row must appear before Cap Totals (in tbody, not tfoot)',
        );
    }

    /**
     * The contract-hint-link count (extension / rookie-option eligibility markers)
     * must be identical whether markExpiringRows is true or false.
     *
     * Proves the new flag did not perturb the $isFreeAgency-driven link/span logic
     * that sits immediately adjacent to the new ternary.
     */
    public function testExtensionEligibilityMarkerUnchangedByMarkExpiringRows(): void
    {
        $db = self::createStub(\mysqli::class);
        $team = $this->createMockTeam();

        // cy=1, cyt=3, salary_yr2=0 → canRenegotiateContract() returns true (next-year
        // salary is zero) → hasExtension=true → contract-hint-link renders.
        // This player is NOT expiring (cy !== cyt), so the flag only affects whether
        // it would suppress other rows — here it changes nothing.
        $result = [$this->buildPlayerRow(pid: 1, cy: 1, cyt: 3)];

        $seasonFlagOff = $this->createMockSeason();
        $htmlFlagOff = Contracts::render($db, $result, $team, $seasonFlagOff, markExpiringRows: false);

        $seasonFlagOn = $this->createMockSeason();
        $htmlFlagOn = Contracts::render($db, $result, $team, $seasonFlagOn, markExpiringRows: true);

        $this->assertSame(
            substr_count($htmlFlagOff, 'contract-hint-link'),
            substr_count($htmlFlagOn, 'contract-hint-link'),
            'contract-hint-link count must be identical regardless of markExpiringRows flag',
        );
    }

    /**
     * Regular season roster: starter class on pid 101 only, pid 202 stays in tbody but out of the cap sums,
     * header years read 24-25 through 29-30.
     */
    public function testRenderPinsRegularSeasonRosterWithStarterAndCapExclusion(): void
    {
        $db = self::createStub(\mysqli::class);
        $result = [
            $this->buildPlayerRowWith(['salary_yr1' => 500, 'salary_yr2' => 520, 'salary_yr3' => 540, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0], pid: 101, cy: 1, cyt: 3),
            $this->buildPlayerRowWith(['salary_yr1' => 100, 'salary_yr2' => 600, 'salary_yr3' => 610, 'salary_yr4' => 620, 'salary_yr5' => 0, 'salary_yr6' => 0], pid: 202, cy: 2, cyt: 4),
        ];

        $html = Contracts::render($db, $result, $this->createMockTeam(), $this->createMockSeason(), [101], [202]);

        self::assertStringContainsString('<th class="col-salary">24-25</th>', $html);
        self::assertStringContainsString('<td class="col-salary">600</td>', $html);
        self::assertStringNotContainsString('contract-hint-cell', $html);
        $expected = <<<'HTML'
        <table class="ibl-data-table team-table responsive-table contracts-table sortable" style="--team-color-primary: #FF0000; --team-color-secondary: #0000FF;">
            <thead>
                <tr>
                    <th>Pos</th>
                    <th class="sticky-col">Player</th>
                    <th>Age</th>
                    <th>Exp</th>
                    <th class="sep-r-team">Bird</th>
                    <th class="col-salary">24-25</th>
                    <th class="col-salary">25-26</th>
                    <th class="col-salary">26-27</th>
                    <th class="col-salary">27-28</th>
                    <th class="col-salary">28-29</th>
                    <th class="col-salary sep-r-team">29-30</th>
                    <th>Tal</th>
                    <th>Skl</th>
                    <th class="sep-r-team">Int</th>
                    <th>Loy</th>
                    <th>PFW</th>
                    <th>PT</th>
                    <th>Sec</th>
                    <th>Trd</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>PG</td>
                    <td class="sticky-col ibl-player-cell is-starter"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=101"><img src="./images/player/101.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Test Player 101</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Player 101</span></a></td>            <td>25</td>
                    <td>1</td>
                    <td class="sep-r-team">3</td>
                    <td class="col-salary">500</td>
                                <td class="col-salary">520</td>
                    <td class="col-salary">540</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary sep-r-team">0</td>
                                <td>75</td>
                    <td>70</td>
                    <td class="sep-r-team">65</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                </tr>
                <tr>
                    <td>PG</td>
                    <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=202"><img src="./images/player/202.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Test Player 202</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Player 202</span></a></td>            <td>25</td>
                    <td>1</td>
                    <td class="sep-r-team">3</td>
                    <td class="col-salary">600</td>
                                <td class="col-salary">610</td>
                    <td class="col-salary">620</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary sep-r-team">0</td>
                                <td>75</td>
                    <td>70</td>
                    <td class="sep-r-team">65</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td class="sticky-col">Cap Totals</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td class="col-salary">500</td>
                    <td class="col-salary">520</td>
                    <td class="col-salary">540</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary sep-r-team">0</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr class="tfoot-legend">
                    <td colspan="19" class="text-left">
                        Key: &nbsp; <em>(Waived)*</em> &nbsp; Becomes Free Agent^ &nbsp; Eligible for Rookie Option/Extension 0* (hover/tap to reveal link)
                    </td>
                </tr>
            </tfoot>
        </table>
        HTML;
        $this->assertRenderPinned($expected, $html);
    }

    /**
     * Free Agency header and salary pin: the first salary column reads 25-26 (endingYear 2025 shifted one
     * year forward) and the six salary cells are read from the one-year-offset contract slots.
     */
    public function testRenderPinsFreeAgencyHeaderAndSalaryColumns(): void
    {
        $db = self::createStub(\mysqli::class);
        $season = $this->createPhaseSeason('Free Agency');
        $result = [
            $this->buildPlayerRowWith(['salary_yr1' => 100, 'salary_yr2' => 200, 'salary_yr3' => 300, 'salary_yr4' => 400, 'salary_yr5' => 500, 'salary_yr6' => 600], pid: 101, cy: 2, cyt: 4),
        ];

        $html = Contracts::render($db, $result, $this->createMockTeam(), $season);

        self::assertStringContainsString('<th class="col-salary">25-26</th>', $html);
        $expected = <<<'HTML'
        <table class="ibl-data-table team-table responsive-table contracts-table sortable" style="--team-color-primary: #FF0000; --team-color-secondary: #0000FF;">
            <thead>
                <tr>
                    <th>Pos</th>
                    <th class="sticky-col">Player</th>
                    <th>Age</th>
                    <th>Exp</th>
                    <th class="sep-r-team">Bird</th>
                    <th class="col-salary">25-26</th>
                    <th class="col-salary">26-27</th>
                    <th class="col-salary">27-28</th>
                    <th class="col-salary">28-29</th>
                    <th class="col-salary">29-30</th>
                    <th class="col-salary sep-r-team">30-31</th>
                    <th>Tal</th>
                    <th>Skl</th>
                    <th class="sep-r-team">Int</th>
                    <th>Loy</th>
                    <th>PFW</th>
                    <th>PT</th>
                    <th>Sec</th>
                    <th>Trd</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>PG</td>
                    <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=101"><img src="./images/player/101.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Test Player 101</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Player 101</span></a></td>            <td>25</td>
                    <td>1</td>
                    <td class="sep-r-team">3</td>
                    <td class="col-salary">300</td>
                                <td class="col-salary">400</td>
                    <td class="col-salary">500</td>
                    <td class="col-salary">600</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary sep-r-team">0</td>
                                <td>75</td>
                    <td>70</td>
                    <td class="sep-r-team">65</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td class="sticky-col">Cap Totals</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td class="col-salary">300</td>
                    <td class="col-salary">400</td>
                    <td class="col-salary">500</td>
                    <td class="col-salary">600</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary sep-r-team">0</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr class="tfoot-legend">
                    <td colspan="19" class="text-left">
                        Key: &nbsp; <em>(Waived)*</em> &nbsp; Becomes Free Agent^ &nbsp; Eligible for Rookie Option/Extension 0* (hover/tap to reveal link)
                    </td>
                </tr>
            </tfoot>
        </table>
        HTML;
        $this->assertRenderPinned($expected, $html);
    }

    /**
     * Regular Season header and salary pin: the first salary column reads 24-25 and endingYear is untouched.
     */
    public function testRenderPinsRegularSeasonHeaderAndSalaryColumns(): void
    {
        $db = self::createStub(\mysqli::class);
        $season = $this->createPhaseSeason('Regular Season');
        $result = [
            $this->buildPlayerRowWith(['salary_yr1' => 100, 'salary_yr2' => 200, 'salary_yr3' => 300, 'salary_yr4' => 400, 'salary_yr5' => 500, 'salary_yr6' => 600], pid: 101, cy: 2, cyt: 4),
        ];

        $html = Contracts::render($db, $result, $this->createMockTeam(), $season);

        self::assertStringContainsString('<th class="col-salary">24-25</th>', $html);
        self::assertSame(2025, $season->endingYear);
        $expected = <<<'HTML'
        <table class="ibl-data-table team-table responsive-table contracts-table sortable" style="--team-color-primary: #FF0000; --team-color-secondary: #0000FF;">
            <thead>
                <tr>
                    <th>Pos</th>
                    <th class="sticky-col">Player</th>
                    <th>Age</th>
                    <th>Exp</th>
                    <th class="sep-r-team">Bird</th>
                    <th class="col-salary">24-25</th>
                    <th class="col-salary">25-26</th>
                    <th class="col-salary">26-27</th>
                    <th class="col-salary">27-28</th>
                    <th class="col-salary">28-29</th>
                    <th class="col-salary sep-r-team">29-30</th>
                    <th>Tal</th>
                    <th>Skl</th>
                    <th class="sep-r-team">Int</th>
                    <th>Loy</th>
                    <th>PFW</th>
                    <th>PT</th>
                    <th>Sec</th>
                    <th>Trd</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>PG</td>
                    <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=101"><img src="./images/player/101.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Test Player 101</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Player 101</span></a></td>            <td>25</td>
                    <td>1</td>
                    <td class="sep-r-team">3</td>
                    <td class="col-salary">200</td>
                                <td class="col-salary">300</td>
                    <td class="col-salary">400</td>
                    <td class="col-salary">500</td>
                    <td class="col-salary">600</td>
                    <td class="col-salary sep-r-team">0</td>
                                <td>75</td>
                    <td>70</td>
                    <td class="sep-r-team">65</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td class="sticky-col">Cap Totals</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td class="col-salary">200</td>
                    <td class="col-salary">300</td>
                    <td class="col-salary">400</td>
                    <td class="col-salary">500</td>
                    <td class="col-salary">600</td>
                    <td class="col-salary sep-r-team">0</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr class="tfoot-legend">
                    <td colspan="19" class="text-left">
                        Key: &nbsp; <em>(Waived)*</em> &nbsp; Becomes Free Agent^ &nbsp; Eligible for Rookie Option/Extension 0* (hover/tap to reveal link)
                    </td>
                </tr>
            </tfoot>
        </table>
        HTML;
        $this->assertRenderPinned($expected, $html);
    }

    /**
     * A Free Agency render leaves the injected Season untouched.
     */
    public function testRenderDoesNotMutateSeasonEndingYearInFreeAgency(): void
    {
        $db = self::createStub(\mysqli::class);
        $season = $this->createPhaseSeason('Free Agency');
        $result = [
            $this->buildPlayerRowWith(['salary_yr1' => 100, 'salary_yr2' => 200, 'salary_yr3' => 300, 'salary_yr4' => 400, 'salary_yr5' => 500, 'salary_yr6' => 600], pid: 101, cy: 2, cyt: 4),
        ];

        Contracts::render($db, $result, $this->createMockTeam(), $season);

        self::assertSame(2025, $season->endingYear);
    }

    /**
     * Two renders with one shared Season produce identical HTML in Free Agency.
     */
    public function testRenderTwiceWithSameSeasonIsIdenticalInFreeAgency(): void
    {
        $db = self::createStub(\mysqli::class);
        $season = $this->createPhaseSeason('Free Agency');
        $result = [
            $this->buildPlayerRowWith(['salary_yr1' => 100, 'salary_yr2' => 200, 'salary_yr3' => 300, 'salary_yr4' => 400, 'salary_yr5' => 500, 'salary_yr6' => 600], pid: 101, cy: 2, cyt: 4),
        ];

        $first = Contracts::render($db, $result, $this->createMockTeam(), $season);
        $second = Contracts::render($db, $result, $this->createMockTeam(), $season);

        self::assertSame($first, $second);
        self::assertStringContainsString('<th class="col-salary">25-26</th>', $second);
    }

    /**
     * An extension-eligible row renders 0* and a negotiate link in season.
     */
    public function testRenderPinsExtensionEligibleRowAsLinkInRegularSeason(): void
    {
        $db = self::createStub(\mysqli::class);
        $result = [$this->buildPlayerRowWith(array_merge(['salary_yr1' => 300, 'salary_yr2' => 310, 'salary_yr3' => 320, 'salary_yr4' => 330, 'salary_yr5' => 340, 'salary_yr6' => 350], ['exp' => 5]), pid: 303, cy: 6, cyt: 6)];

        $html = Contracts::render($db, $result, $this->createMockTeam(), $this->createMockSeason());

        self::assertStringContainsString('<a href="modules.php?name=Player&amp;pa=negotiate&amp;pid=303" class="contract-hint-link" data-no-abbreviate>Contract Extension</a>', $html);
        self::assertSame(5, substr_count($html, 'contract-hint-cell" tabindex="0"') + substr_count($html, 'contract-hint-cell sep-r-team" tabindex="0"'));
        $expected = <<<'HTML'
        <table class="ibl-data-table team-table responsive-table contracts-table sortable" style="--team-color-primary: #FF0000; --team-color-secondary: #0000FF;">
            <thead>
                <tr>
                    <th>Pos</th>
                    <th class="sticky-col">Player</th>
                    <th>Age</th>
                    <th>Exp</th>
                    <th class="sep-r-team">Bird</th>
                    <th class="col-salary">24-25</th>
                    <th class="col-salary">25-26</th>
                    <th class="col-salary">26-27</th>
                    <th class="col-salary">27-28</th>
                    <th class="col-salary">28-29</th>
                    <th class="col-salary sep-r-team">29-30</th>
                    <th>Tal</th>
                    <th>Skl</th>
                    <th class="sep-r-team">Int</th>
                    <th>Loy</th>
                    <th>PFW</th>
                    <th>PT</th>
                    <th>Sec</th>
                    <th>Trd</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>PG</td>
                    <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=303"><img src="./images/player/303.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full player-expiring">Test Player 303</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev player-expiring">T. Player 303</span></a></td>            <td>25</td>
                    <td>5</td>
                    <td class="sep-r-team">3</td>
                    <td class="col-salary">350</td>
                                <td class="col-salary contract-hint-cell" tabindex="0">0*<a href="modules.php?name=Player&amp;pa=negotiate&amp;pid=303" class="contract-hint-link" data-no-abbreviate>Contract Extension</a></td>
                    <td class="col-salary contract-hint-cell" tabindex="0">0</td>
                    <td class="col-salary contract-hint-cell" tabindex="0">0</td>
                    <td class="col-salary contract-hint-cell" tabindex="0">0</td>
                    <td class="col-salary contract-hint-cell sep-r-team" tabindex="0">0</td>
                                <td>75</td>
                    <td>70</td>
                    <td class="sep-r-team">65</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td class="sticky-col">Cap Totals</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td class="col-salary">350</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary sep-r-team">0</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr class="tfoot-legend">
                    <td colspan="19" class="text-left">
                        Key: &nbsp; <em>(Waived)*</em> &nbsp; Becomes Free Agent^ &nbsp; Eligible for Rookie Option/Extension 0* (hover/tap to reveal link)
                    </td>
                </tr>
            </tfoot>
        </table>
        HTML;
        $this->assertRenderPinned($expected, $html);
    }

    /**
     * The same row renders a span, not a link, when action links are hidden.
     */
    public function testRenderPinsExtensionEligibleRowAsSpanWhenActionLinksHidden(): void
    {
        $db = self::createStub(\mysqli::class);
        $result = [$this->buildPlayerRowWith(array_merge(['salary_yr1' => 300, 'salary_yr2' => 310, 'salary_yr3' => 320, 'salary_yr4' => 330, 'salary_yr5' => 340, 'salary_yr6' => 350], ['exp' => 5]), pid: 303, cy: 6, cyt: 6)];

        $html = Contracts::render($db, $result, $this->createMockTeam(), $this->createMockSeason(), showActionLinks: false);

        self::assertStringContainsString('<span class="contract-hint-link" data-no-abbreviate>Contract Extension</span>', $html);
        self::assertStringNotContainsString('<a href="modules.php?name=Player&amp;pa=negotiate', $html);
        $expected = <<<'HTML'
        <table class="ibl-data-table team-table responsive-table contracts-table sortable" style="--team-color-primary: #FF0000; --team-color-secondary: #0000FF;">
            <thead>
                <tr>
                    <th>Pos</th>
                    <th class="sticky-col">Player</th>
                    <th>Age</th>
                    <th>Exp</th>
                    <th class="sep-r-team">Bird</th>
                    <th class="col-salary">24-25</th>
                    <th class="col-salary">25-26</th>
                    <th class="col-salary">26-27</th>
                    <th class="col-salary">27-28</th>
                    <th class="col-salary">28-29</th>
                    <th class="col-salary sep-r-team">29-30</th>
                    <th>Tal</th>
                    <th>Skl</th>
                    <th class="sep-r-team">Int</th>
                    <th>Loy</th>
                    <th>PFW</th>
                    <th>PT</th>
                    <th>Sec</th>
                    <th>Trd</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>PG</td>
                    <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=303"><img src="./images/player/303.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full player-expiring">Test Player 303</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev player-expiring">T. Player 303</span></a></td>            <td>25</td>
                    <td>5</td>
                    <td class="sep-r-team">3</td>
                    <td class="col-salary">350</td>
                                <td class="col-salary contract-hint-cell" tabindex="0">0*<span class="contract-hint-link" data-no-abbreviate>Contract Extension</span></td>
                    <td class="col-salary contract-hint-cell" tabindex="0">0</td>
                    <td class="col-salary contract-hint-cell" tabindex="0">0</td>
                    <td class="col-salary contract-hint-cell" tabindex="0">0</td>
                    <td class="col-salary contract-hint-cell sep-r-team" tabindex="0">0</td>
                                <td>75</td>
                    <td>70</td>
                    <td class="sep-r-team">65</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td class="sticky-col">Cap Totals</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td class="col-salary">350</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary sep-r-team">0</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr class="tfoot-legend">
                    <td colspan="19" class="text-left">
                        Key: &nbsp; <em>(Waived)*</em> &nbsp; Becomes Free Agent^ &nbsp; Eligible for Rookie Option/Extension 0* (hover/tap to reveal link)
                    </td>
                </tr>
            </tfoot>
        </table>
        HTML;
        $this->assertRenderPinned($expected, $html);
    }

    /**
     * A rookie-option row in the HEAT phase renders a rookieoption link with a non-zero year-2 salary.
     */
    public function testRenderPinsRookieOptionRowAsLinkInHeatPhase(): void
    {
        $db = self::createStub(\mysqli::class);
        $result = [$this->buildPlayerRowWith(array_merge(['salary_yr1' => 80, 'salary_yr2' => 90, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0], ['draftround' => 2, 'exp' => 2]), pid: 404, cy: 1, cyt: 2)];

        $html = Contracts::render($db, $result, $this->createMockTeam(), $this->createPhaseSeason('HEAT'));

        self::assertStringContainsString('<a href="modules.php?name=Player&amp;pa=rookieoption&amp;pid=404&amp;from=team" class="contract-hint-link" data-no-abbreviate>Rookie Option</a>', $html);
        $expected = <<<'HTML'
        <table class="ibl-data-table team-table responsive-table contracts-table sortable" style="--team-color-primary: #FF0000; --team-color-secondary: #0000FF;">
            <thead>
                <tr>
                    <th>Pos</th>
                    <th class="sticky-col">Player</th>
                    <th>Age</th>
                    <th>Exp</th>
                    <th class="sep-r-team">Bird</th>
                    <th class="col-salary">24-25</th>
                    <th class="col-salary">25-26</th>
                    <th class="col-salary">26-27</th>
                    <th class="col-salary">27-28</th>
                    <th class="col-salary">28-29</th>
                    <th class="col-salary sep-r-team">29-30</th>
                    <th>Tal</th>
                    <th>Skl</th>
                    <th class="sep-r-team">Int</th>
                    <th>Loy</th>
                    <th>PFW</th>
                    <th>PT</th>
                    <th>Sec</th>
                    <th>Trd</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>PG</td>
                    <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=404"><img src="./images/player/404.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Test Player 404</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Player 404</span></a></td>            <td>25</td>
                    <td>2</td>
                    <td class="sep-r-team">3</td>
                    <td class="col-salary">80</td>
                                <td class="col-salary contract-hint-cell" tabindex="0">90<a href="modules.php?name=Player&amp;pa=rookieoption&amp;pid=404&amp;from=team" class="contract-hint-link" data-no-abbreviate>Rookie Option</a></td>
                    <td class="col-salary contract-hint-cell" tabindex="0">0</td>
                    <td class="col-salary contract-hint-cell" tabindex="0">0</td>
                    <td class="col-salary contract-hint-cell" tabindex="0">0</td>
                    <td class="col-salary contract-hint-cell sep-r-team" tabindex="0">0</td>
                                <td>75</td>
                    <td>70</td>
                    <td class="sep-r-team">65</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td class="sticky-col">Cap Totals</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td class="col-salary">80</td>
                    <td class="col-salary">90</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary sep-r-team">0</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr class="tfoot-legend">
                    <td colspan="19" class="text-left">
                        Key: &nbsp; <em>(Waived)*</em> &nbsp; Becomes Free Agent^ &nbsp; Eligible for Rookie Option/Extension 0* (hover/tap to reveal link)
                    </td>
                </tr>
            </tfoot>
        </table>
        HTML;
        $this->assertRenderPinned($expected, $html);
    }

    /**
     * A rookie-option row with links hidden renders 0* plus a span.
     */
    public function testRenderPinsRookieOptionRowAsSpanWithZeroStar(): void
    {
        $db = self::createStub(\mysqli::class);
        $result = [$this->buildPlayerRowWith(array_merge(['salary_yr1' => 80, 'salary_yr2' => 90, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0], ['draftround' => 2, 'exp' => 2]), pid: 404, cy: 2, cyt: 2)];

        $html = Contracts::render($db, $result, $this->createMockTeam(), $this->createPhaseSeason('HEAT'), showActionLinks: false);

        self::assertStringContainsString('0*<span class="contract-hint-link" data-no-abbreviate>Rookie Option</span>', $html);
        $expected = <<<'HTML'
        <table class="ibl-data-table team-table responsive-table contracts-table sortable" style="--team-color-primary: #FF0000; --team-color-secondary: #0000FF;">
            <thead>
                <tr>
                    <th>Pos</th>
                    <th class="sticky-col">Player</th>
                    <th>Age</th>
                    <th>Exp</th>
                    <th class="sep-r-team">Bird</th>
                    <th class="col-salary">24-25</th>
                    <th class="col-salary">25-26</th>
                    <th class="col-salary">26-27</th>
                    <th class="col-salary">27-28</th>
                    <th class="col-salary">28-29</th>
                    <th class="col-salary sep-r-team">29-30</th>
                    <th>Tal</th>
                    <th>Skl</th>
                    <th class="sep-r-team">Int</th>
                    <th>Loy</th>
                    <th>PFW</th>
                    <th>PT</th>
                    <th>Sec</th>
                    <th>Trd</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>PG</td>
                    <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=404"><img src="./images/player/404.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full player-expiring">Test Player 404</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev player-expiring">T. Player 404</span></a></td>            <td>25</td>
                    <td>2</td>
                    <td class="sep-r-team">3</td>
                    <td class="col-salary">90</td>
                                <td class="col-salary contract-hint-cell" tabindex="0">0*<span class="contract-hint-link" data-no-abbreviate>Rookie Option</span></td>
                    <td class="col-salary contract-hint-cell" tabindex="0">0</td>
                    <td class="col-salary contract-hint-cell" tabindex="0">0</td>
                    <td class="col-salary contract-hint-cell" tabindex="0">0</td>
                    <td class="col-salary contract-hint-cell sep-r-team" tabindex="0">0</td>
                                <td>75</td>
                    <td>70</td>
                    <td class="sep-r-team">65</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td class="sticky-col">Cap Totals</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td class="col-salary">90</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary sep-r-team">0</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr class="tfoot-legend">
                    <td colspan="19" class="text-left">
                        Key: &nbsp; <em>(Waived)*</em> &nbsp; Becomes Free Agent^ &nbsp; Eligible for Rookie Option/Extension 0* (hover/tap to reveal link)
                    </td>
                </tr>
            </tfoot>
        </table>
        HTML;
        $this->assertRenderPinned($expected, $html);
    }

    /**
     * A cash row carries data-cash-row, never the expiring class, and never a hint cell.
     */
    public function testRenderPinsCashConsiderationRowWithDataCashRowAttribute(): void
    {
        $db = self::createStub(\mysqli::class);
        $cashRow = TeamTableService::cashConsiderationToRosterRow([
            'teamid' => 1,
            'label' => 'Cash Consideration',
            'cy' => 1,
            'cyt' => 1,
            'salary_yr1' => 500,
            'salary_yr2' => 0,
            'salary_yr3' => 0,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
        ]);

        $html = Contracts::render($db, [$cashRow], $this->createMockTeam(), $this->createMockSeason(), markExpiringRows: true);

        self::assertStringContainsString('<tr data-cash-row>', $html);
        self::assertStringNotContainsString('player-fa-expiring-row', $html);
        self::assertStringNotContainsString('contract-hint-cell', $html);
        $expected = <<<'HTML'
        <table class="ibl-data-table team-table responsive-table contracts-table sortable" style="--team-color-primary: #FF0000; --team-color-secondary: #0000FF;">
            <thead>
                <tr>
                    <th>Pos</th>
                    <th class="sticky-col">Player</th>
                    <th>Age</th>
                    <th>Exp</th>
                    <th class="sep-r-team">Bird</th>
                    <th class="col-salary">24-25</th>
                    <th class="col-salary">25-26</th>
                    <th class="col-salary">26-27</th>
                    <th class="col-salary">27-28</th>
                    <th class="col-salary">28-29</th>
                    <th class="col-salary sep-r-team">29-30</th>
                    <th>Tal</th>
                    <th>Skl</th>
                    <th class="sep-r-team">Int</th>
                    <th>Loy</th>
                    <th>PFW</th>
                    <th>PT</th>
                    <th>Sec</th>
                    <th>Trd</th>
                </tr>
            </thead>
            <tbody>
                <tr data-cash-row>
                    <td></td>
                    <td class="sticky-col ibl-player-cell"><span class="ibl-player-cell__name player-waived"> Cash Consideration</span></td>            <td>0</td>
                    <td>1</td>
                    <td class="sep-r-team">0</td>
                    <td class="col-salary">500</td>
                                <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary sep-r-team">0</td>
                                <td>0</td>
                    <td>0</td>
                    <td class="sep-r-team">0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td class="sticky-col">Cap Totals</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td class="col-salary">500</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary sep-r-team">0</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr class="tfoot-legend">
                    <td colspan="19" class="text-left">
                        Key: &nbsp; <em>(Waived)*</em> &nbsp; Becomes Free Agent^ &nbsp; Eligible for Rookie Option/Extension 0* (hover/tap to reveal link)
                    </td>
                </tr>
            </tfoot>
        </table>
        HTML;
        $this->assertRenderPinned($expected, $html);
    }

    /**
     * cap-exceeded applies only strictly above League::HARD_CAP_MAX: 7001 is over, 7000 is not.
     */
    public function testRenderPinsCapExceededClassOnlyAboveHardCap(): void
    {
        $db = self::createStub(\mysqli::class);
        $result = [$this->buildPlayerRowWith(['salary_yr1' => 7001, 'salary_yr2' => 7000, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0], pid: 505, cy: 1, cyt: 2)];

        $html = Contracts::render($db, $result, $this->createMockTeam(), $this->createMockSeason());

        self::assertStringContainsString('<td class="col-salary cap-exceeded">7001</td>', $html);
        self::assertStringContainsString('<td class="col-salary">7000</td>', $html);
        $expected = <<<'HTML'
        <table class="ibl-data-table team-table responsive-table contracts-table sortable" style="--team-color-primary: #FF0000; --team-color-secondary: #0000FF;">
            <thead>
                <tr>
                    <th>Pos</th>
                    <th class="sticky-col">Player</th>
                    <th>Age</th>
                    <th>Exp</th>
                    <th class="sep-r-team">Bird</th>
                    <th class="col-salary">24-25</th>
                    <th class="col-salary">25-26</th>
                    <th class="col-salary">26-27</th>
                    <th class="col-salary">27-28</th>
                    <th class="col-salary">28-29</th>
                    <th class="col-salary sep-r-team">29-30</th>
                    <th>Tal</th>
                    <th>Skl</th>
                    <th class="sep-r-team">Int</th>
                    <th>Loy</th>
                    <th>PFW</th>
                    <th>PT</th>
                    <th>Sec</th>
                    <th>Trd</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>PG</td>
                    <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=505"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGBgAAAABQABpfZFQAAAAABJRU5ErkJggg==" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Test Player 505</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Player 505</span></a></td>            <td>25</td>
                    <td>1</td>
                    <td class="sep-r-team">3</td>
                    <td class="col-salary">7001</td>
                                <td class="col-salary">7000</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary sep-r-team">0</td>
                                <td>75</td>
                    <td>70</td>
                    <td class="sep-r-team">65</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td class="sticky-col">Cap Totals</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td class="col-salary cap-exceeded">7001</td>
                    <td class="col-salary">7000</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary sep-r-team">0</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr class="tfoot-legend">
                    <td colspan="19" class="text-left">
                        Key: &nbsp; <em>(Waived)*</em> &nbsp; Becomes Free Agent^ &nbsp; Eligible for Rookie Option/Extension 0* (hover/tap to reveal link)
                    </td>
                </tr>
            </tfoot>
        </table>
        HTML;
        $this->assertRenderPinned($expected, $html);

        // Every column sits exactly on the cap: equal is not exceeded.
        $atCap = array_fill_keys(['salary_yr1', 'salary_yr2', 'salary_yr3', 'salary_yr4', 'salary_yr5', 'salary_yr6'], 7000);
        $atCapHtml = Contracts::render($db, [$this->buildPlayerRowWith($atCap, pid: 505, cy: 1, cyt: 2)], $this->createMockTeam(), $this->createMockSeason());
        self::assertStringNotContainsString('cap-exceeded', $atCapHtml);

        // Every column one above the cap: all six totals are exceeded.
        $overCap = array_fill_keys(['salary_yr1', 'salary_yr2', 'salary_yr3', 'salary_yr4', 'salary_yr5', 'salary_yr6'], 7001);
        $overCapHtml = Contracts::render($db, [$this->buildPlayerRowWith($overCap, pid: 505, cy: 1, cyt: 2)], $this->createMockTeam(), $this->createMockSeason());
        self::assertSame(6, substr_count($overCapHtml, 'cap-exceeded'));
    }

    /**
     * Null age, ratings, experience, bird years and position render as 0 and empty.
     */
    public function testRenderPinsNullRatingsAgeAndPositionAsZeroAndEmpty(): void
    {
        $db = self::createStub(\mysqli::class);
        $nulls = [
            'pos' => null, 'age' => null, 'exp' => null, 'bird' => null,
            'talent' => null, 'skill' => null, 'intangibles' => null,
            'loyalty' => null, 'playing_time' => null, 'winner' => null,
            'tradition' => null, 'security' => null,
        ];
        $result = [$this->buildPlayerRowWith(array_merge(['salary_yr1' => 100, 'salary_yr2' => 100, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0], $nulls), pid: 606, cy: 1, cyt: 2)];

        $html = Contracts::render($db, $result, $this->createMockTeam(), $this->createMockSeason());

        self::assertStringContainsString('<td></td>', $html);
        self::assertStringContainsString('<td>0</td>', $html);
        $expected = <<<'HTML'
        <table class="ibl-data-table team-table responsive-table contracts-table sortable" style="--team-color-primary: #FF0000; --team-color-secondary: #0000FF;">
            <thead>
                <tr>
                    <th>Pos</th>
                    <th class="sticky-col">Player</th>
                    <th>Age</th>
                    <th>Exp</th>
                    <th class="sep-r-team">Bird</th>
                    <th class="col-salary">24-25</th>
                    <th class="col-salary">25-26</th>
                    <th class="col-salary">26-27</th>
                    <th class="col-salary">27-28</th>
                    <th class="col-salary">28-29</th>
                    <th class="col-salary sep-r-team">29-30</th>
                    <th>Tal</th>
                    <th>Skl</th>
                    <th class="sep-r-team">Int</th>
                    <th>Loy</th>
                    <th>PFW</th>
                    <th>PT</th>
                    <th>Sec</th>
                    <th>Trd</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td></td>
                    <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=606"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGBgAAAABQABpfZFQAAAAABJRU5ErkJggg==" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Test Player 606</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Player 606</span></a></td>            <td>0</td>
                    <td>0</td>
                    <td class="sep-r-team">0</td>
                    <td class="col-salary">100</td>
                                <td class="col-salary">100</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary sep-r-team">0</td>
                                <td>0</td>
                    <td>0</td>
                    <td class="sep-r-team">0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td class="sticky-col">Cap Totals</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td class="col-salary">100</td>
                    <td class="col-salary">100</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary sep-r-team">0</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr class="tfoot-legend">
                    <td colspan="19" class="text-left">
                        Key: &nbsp; <em>(Waived)*</em> &nbsp; Becomes Free Agent^ &nbsp; Eligible for Rookie Option/Extension 0* (hover/tap to reveal link)
                    </td>
                </tr>
            </tfoot>
        </table>
        HTML;
        $this->assertRenderPinned($expected, $html);
    }

    /**
     * Hostile markup in pos and name renders escaped; the raw tag never reaches the output.
     */
    public function testRenderEscapesHostilePositionAndName(): void
    {
        $db = self::createStub(\mysqli::class);
        $result = [$this->buildPlayerRowWith(
            array_merge(['salary_yr1' => 100, 'salary_yr2' => 100, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0], ['pos' => '<b>PG</b>', 'name' => '<script>alert(1)</script> Smith']),
            pid: 707,
            cy: 1,
            cyt: 2,
        )];

        $html = Contracts::render($db, $result, $this->createMockTeam(), $this->createMockSeason());

        self::assertStringContainsString('<td>&lt;b&gt;PG&lt;/b&gt;</td>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>', $html);
        $expected = <<<'HTML'
        <table class="ibl-data-table team-table responsive-table contracts-table sortable" style="--team-color-primary: #FF0000; --team-color-secondary: #0000FF;">
            <thead>
                <tr>
                    <th>Pos</th>
                    <th class="sticky-col">Player</th>
                    <th>Age</th>
                    <th>Exp</th>
                    <th class="sep-r-team">Bird</th>
                    <th class="col-salary">24-25</th>
                    <th class="col-salary">25-26</th>
                    <th class="col-salary">26-27</th>
                    <th class="col-salary">27-28</th>
                    <th class="col-salary">28-29</th>
                    <th class="col-salary sep-r-team">29-30</th>
                    <th>Tal</th>
                    <th>Skl</th>
                    <th class="sep-r-team">Int</th>
                    <th>Loy</th>
                    <th>PFW</th>
                    <th>PT</th>
                    <th>Sec</th>
                    <th>Trd</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>&lt;b&gt;PG&lt;/b&gt;</td>
                    <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=707"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGBgAAAABQABpfZFQAAAAABJRU5ErkJggg==" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">&lt;script&gt;alert(1)&lt;/script&gt; Smith</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">&. Smith</span></a></td>            <td>25</td>
                    <td>1</td>
                    <td class="sep-r-team">3</td>
                    <td class="col-salary">100</td>
                                <td class="col-salary">100</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary sep-r-team">0</td>
                                <td>75</td>
                    <td>70</td>
                    <td class="sep-r-team">65</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td class="sticky-col">Cap Totals</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td class="col-salary">100</td>
                    <td class="col-salary">100</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary sep-r-team">0</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr class="tfoot-legend">
                    <td colspan="19" class="text-left">
                        Key: &nbsp; <em>(Waived)*</em> &nbsp; Becomes Free Agent^ &nbsp; Eligible for Rookie Option/Extension 0* (hover/tap to reveal link)
                    </td>
                </tr>
            </tfoot>
        </table>
        HTML;
        $this->assertRenderPinned($expected, $html);
    }

    /**
     * Draft and Free Agency shift the header years one season forward without touching the caller's Season.
     *
     * The Season is request-shared, so render() computes the shifted label year locally. A second
     * render() with the same Season reads the same year again.
     */
    public function testRenderShiftsHeaderYearsDuringOffseasonWithoutMutatingSeason(): void
    {
        $db = self::createStub(\mysqli::class);
        $season = $this->createPhaseSeason('Free Agency');
        $result = [
            $this->buildPlayerRowWith(array_merge(['salary_yr1' => 300, 'salary_yr2' => 310, 'salary_yr3' => 320, 'salary_yr4' => 330, 'salary_yr5' => 340, 'salary_yr6' => 350], ['exp' => 8, 'draftround' => 0]), pid: 808, cy: 5, cyt: 6),
            $this->buildPlayerRow(pid: 1, cy: 2, cyt: 2),
        ];

        $html = Contracts::render($db, $result, $this->createMockTeam(), $season, markExpiringRows: true, showActionLinks: true);

        self::assertSame(2025, $season->endingYear);
        self::assertStringContainsString('<th class="col-salary">25-26</th>', $html);
        self::assertStringContainsString(' class="player-fa-expiring-row"', $html);
        self::assertStringContainsString('<span class="contract-hint-link" data-no-abbreviate>Contract Extension</span>', $html);
        $expected = <<<'HTML'
        <table class="ibl-data-table team-table responsive-table contracts-table sortable" style="--team-color-primary: #FF0000; --team-color-secondary: #0000FF;">
            <thead>
                <tr>
                    <th>Pos</th>
                    <th class="sticky-col">Player</th>
                    <th>Age</th>
                    <th>Exp</th>
                    <th class="sep-r-team">Bird</th>
                    <th class="col-salary">25-26</th>
                    <th class="col-salary">26-27</th>
                    <th class="col-salary">27-28</th>
                    <th class="col-salary">28-29</th>
                    <th class="col-salary">29-30</th>
                    <th class="col-salary sep-r-team">30-31</th>
                    <th>Tal</th>
                    <th>Skl</th>
                    <th class="sep-r-team">Int</th>
                    <th>Loy</th>
                    <th>PFW</th>
                    <th>PT</th>
                    <th>Sec</th>
                    <th>Trd</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>PG</td>
                    <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=808"><img src="./images/player/808.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Test Player 808</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Player 808</span></a></td>            <td>25</td>
                    <td>8</td>
                    <td class="sep-r-team">3</td>
                    <td class="col-salary">350</td>
                                <td class="col-salary contract-hint-cell" tabindex="0">0*<span class="contract-hint-link" data-no-abbreviate>Contract Extension</span></td>
                    <td class="col-salary contract-hint-cell" tabindex="0">0</td>
                    <td class="col-salary contract-hint-cell" tabindex="0">0</td>
                    <td class="col-salary contract-hint-cell" tabindex="0">0</td>
                    <td class="col-salary contract-hint-cell sep-r-team" tabindex="0">0</td>
                                <td>75</td>
                    <td>70</td>
                    <td class="sep-r-team">65</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                </tr>
                <tr class="player-fa-expiring-row">
                    <td>PG</td>
                    <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=1"><img src="./images/player/1.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full player-expiring">Test Player 1</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev player-expiring">T. Player 1</span></a></td>            <td>25</td>
                    <td>1</td>
                    <td class="sep-r-team">3</td>
                    <td class="col-salary">0</td>
                                <td class="col-salary contract-hint-cell" tabindex="0">0*<span class="contract-hint-link" data-no-abbreviate>Contract Extension</span></td>
                    <td class="col-salary contract-hint-cell" tabindex="0">0</td>
                    <td class="col-salary contract-hint-cell" tabindex="0">0</td>
                    <td class="col-salary contract-hint-cell" tabindex="0">0</td>
                    <td class="col-salary contract-hint-cell sep-r-team" tabindex="0">0</td>
                                <td>75</td>
                    <td>70</td>
                    <td class="sep-r-team">65</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td class="sticky-col">Cap Totals</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td class="col-salary">350</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary sep-r-team">0</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr class="tfoot-legend">
                    <td colspan="19" class="text-left">
                        Key: &nbsp; <em>(Waived)*</em> &nbsp; Becomes Free Agent^ &nbsp; Eligible for Rookie Option/Extension 0* (hover/tap to reveal link)
                    </td>
                </tr>
            </tfoot>
        </table>
        HTML;
        $this->assertRenderPinned($expected, $html);

        $secondHtml = Contracts::render($db, $result, $this->createMockTeam(), $season, markExpiringRows: true, showActionLinks: true);

        self::assertSame(2025, $season->endingYear);
        self::assertStringContainsString('<th class="col-salary">25-26</th>', $secondHtml);
    }

    /**
     * Draft phase with both flags off: offset-by-one salaries, no hint cells, no expiring class.
     */
    public function testRenderPinsDraftPhaseWithFlagsOff(): void
    {
        $db = self::createStub(\mysqli::class);
        $season = $this->createPhaseSeason('Draft');
        $result = [$this->buildPlayerRowWith(['salary_yr1' => 500, 'salary_yr2' => 520, 'salary_yr3' => 540, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0], pid: 101, cy: 1, cyt: 3)];

        $html = Contracts::render($db, $result, $this->createMockTeam(), $season, showActionLinks: false, markExpiringRows: false);

        self::assertSame(2025, $season->endingYear);
        self::assertStringContainsString('<th class="col-salary">25-26</th>', $html);
        self::assertStringNotContainsString('player-fa-expiring-row', $html);
        self::assertStringNotContainsString('contract-hint-cell', $html);
        $expected = <<<'HTML'
        <table class="ibl-data-table team-table responsive-table contracts-table sortable" style="--team-color-primary: #FF0000; --team-color-secondary: #0000FF;">
            <thead>
                <tr>
                    <th>Pos</th>
                    <th class="sticky-col">Player</th>
                    <th>Age</th>
                    <th>Exp</th>
                    <th class="sep-r-team">Bird</th>
                    <th class="col-salary">25-26</th>
                    <th class="col-salary">26-27</th>
                    <th class="col-salary">27-28</th>
                    <th class="col-salary">28-29</th>
                    <th class="col-salary">29-30</th>
                    <th class="col-salary sep-r-team">30-31</th>
                    <th>Tal</th>
                    <th>Skl</th>
                    <th class="sep-r-team">Int</th>
                    <th>Loy</th>
                    <th>PFW</th>
                    <th>PT</th>
                    <th>Sec</th>
                    <th>Trd</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>PG</td>
                    <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=101"><img src="./images/player/101.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Test Player 101</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Player 101</span></a></td>            <td>25</td>
                    <td>1</td>
                    <td class="sep-r-team">3</td>
                    <td class="col-salary">520</td>
                                <td class="col-salary">540</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary sep-r-team">0</td>
                                <td>75</td>
                    <td>70</td>
                    <td class="sep-r-team">65</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td class="sticky-col">Cap Totals</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td class="col-salary">520</td>
                    <td class="col-salary">540</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary sep-r-team">0</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr class="tfoot-legend">
                    <td colspan="19" class="text-left">
                        Key: &nbsp; <em>(Waived)*</em> &nbsp; Becomes Free Agent^ &nbsp; Eligible for Rookie Option/Extension 0* (hover/tap to reveal link)
                    </td>
                </tr>
            </tfoot>
        </table>
        HTML;
        $this->assertRenderPinned($expected, $html);
    }

    /**
     * The public surface is exactly static render() with the pinned signature; the class has no
     * properties and no constructor, so no builder can hold a DB handle or identity.
     */
    public function testContractsExposesOnlyRenderAsPublicMethod(): void
    {
        $reflection = new \ReflectionClass(Contracts::class);

        $publicMethods = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
        );
        self::assertSame(['render'], $publicMethods);
        self::assertSame([], $reflection->getProperties());
        self::assertNull($reflection->getConstructor());

        $render = $reflection->getMethod('render');
        self::assertTrue($render->isStatic());
        self::assertSame('string', (string) $render->getReturnType());

        $params = $render->getParameters();
        self::assertSame(
            ['db', 'result', 'team', 'season', 'starterPids', 'excludeFromCapPids', 'showActionLinks', 'markExpiringRows'],
            array_map(static fn (\ReflectionParameter $param): string => $param->getName(), $params),
        );
        self::assertSame([], $params[4]->getDefaultValue());
        self::assertSame([], $params[5]->getDefaultValue());
        self::assertTrue($params[6]->getDefaultValue());
        self::assertFalse($params[7]->getDefaultValue());
    }

    /**
     * A pipe in the name marks a cash-style player: no hint, but no data-cash-row either (isCashRow is false).
     */
    public function testRenderPinsPipeNamedNonCashRowSuppressesHintWithoutDataCashRow(): void
    {
        $db = self::createStub(\mysqli::class);
        $result = [$this->buildPlayerRowWith(array_merge(['salary_yr1' => 500, 'salary_yr2' => 0, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0], ['name' => 'Cash | Consideration']), pid: 909, cy: 1, cyt: 3)];

        $html = Contracts::render($db, $result, $this->createMockTeam(), $this->createMockSeason());

        self::assertStringNotContainsString('contract-hint-cell', $html);
        self::assertStringNotContainsString('data-cash-row', $html);
        $expected = <<<'HTML'
        <table class="ibl-data-table team-table responsive-table contracts-table sortable" style="--team-color-primary: #FF0000; --team-color-secondary: #0000FF;">
            <thead>
                <tr>
                    <th>Pos</th>
                    <th class="sticky-col">Player</th>
                    <th>Age</th>
                    <th>Exp</th>
                    <th class="sep-r-team">Bird</th>
                    <th class="col-salary">24-25</th>
                    <th class="col-salary">25-26</th>
                    <th class="col-salary">26-27</th>
                    <th class="col-salary">27-28</th>
                    <th class="col-salary">28-29</th>
                    <th class="col-salary sep-r-team">29-30</th>
                    <th>Tal</th>
                    <th>Skl</th>
                    <th class="sep-r-team">Int</th>
                    <th>Loy</th>
                    <th>PFW</th>
                    <th>PT</th>
                    <th>Sec</th>
                    <th>Trd</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>PG</td>
                    <td class="sticky-col ibl-player-cell"><span class="ibl-player-cell__name">Cash  Consideration</span></td>            <td>25</td>
                    <td>1</td>
                    <td class="sep-r-team">3</td>
                    <td class="col-salary">500</td>
                                <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary sep-r-team">0</td>
                                <td>75</td>
                    <td>70</td>
                    <td class="sep-r-team">65</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td class="sticky-col">Cap Totals</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td class="col-salary">500</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary sep-r-team">0</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr class="tfoot-legend">
                    <td colspan="19" class="text-left">
                        Key: &nbsp; <em>(Waived)*</em> &nbsp; Becomes Free Agent^ &nbsp; Eligible for Rookie Option/Extension 0* (hover/tap to reveal link)
                    </td>
                </tr>
            </tfoot>
        </table>
        HTML;
        $this->assertRenderPinned($expected, $html);
    }

    /**
     * A null pid renders pid=0 in the hint URL and the player cell href.
     */
    public function testRenderPinsNullPlayerIdAsZeroInHintUrlAndPlayerCell(): void
    {
        $db = self::createStub(\mysqli::class);
        $result = [$this->buildPlayerRowWith(array_merge(['salary_yr1' => 500, 'salary_yr2' => 0, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0], ['pid' => null]), cy: 1, cyt: 3)];

        $html = Contracts::render($db, $result, $this->createMockTeam(), $this->createMockSeason());

        self::assertStringContainsString('pa=negotiate&amp;pid=0"', $html);
        $expected = <<<'HTML'
        <table class="ibl-data-table team-table responsive-table contracts-table sortable" style="--team-color-primary: #FF0000; --team-color-secondary: #0000FF;">
            <thead>
                <tr>
                    <th>Pos</th>
                    <th class="sticky-col">Player</th>
                    <th>Age</th>
                    <th>Exp</th>
                    <th class="sep-r-team">Bird</th>
                    <th class="col-salary">24-25</th>
                    <th class="col-salary">25-26</th>
                    <th class="col-salary">26-27</th>
                    <th class="col-salary">27-28</th>
                    <th class="col-salary">28-29</th>
                    <th class="col-salary sep-r-team">29-30</th>
                    <th>Tal</th>
                    <th>Skl</th>
                    <th class="sep-r-team">Int</th>
                    <th>Loy</th>
                    <th>PFW</th>
                    <th>PT</th>
                    <th>Sec</th>
                    <th>Trd</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>PG</td>
                    <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=0"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGBgAAAABQABpfZFQAAAAABJRU5ErkJggg==" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Test Player 1</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Player 1</span></a></td>            <td>25</td>
                    <td>1</td>
                    <td class="sep-r-team">3</td>
                    <td class="col-salary">500</td>
                                <td class="col-salary contract-hint-cell" tabindex="0">0*<a href="modules.php?name=Player&amp;pa=negotiate&amp;pid=0" class="contract-hint-link" data-no-abbreviate>Contract Extension</a></td>
                    <td class="col-salary contract-hint-cell" tabindex="0">0</td>
                    <td class="col-salary contract-hint-cell" tabindex="0">0</td>
                    <td class="col-salary contract-hint-cell" tabindex="0">0</td>
                    <td class="col-salary contract-hint-cell sep-r-team" tabindex="0">0</td>
                                <td>75</td>
                    <td>70</td>
                    <td class="sep-r-team">65</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                    <td>0</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td class="sticky-col">Cap Totals</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td class="col-salary">500</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary">0</td>
                    <td class="col-salary sep-r-team">0</td>
                    <td></td>
                    <td></td>
                    <td class="sep-r-team"></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr class="tfoot-legend">
                    <td colspan="19" class="text-left">
                        Key: &nbsp; <em>(Waived)*</em> &nbsp; Becomes Free Agent^ &nbsp; Eligible for Rookie Option/Extension 0* (hover/tap to reveal link)
                    </td>
                </tr>
            </tfoot>
        </table>
        HTML;
        $this->assertRenderPinned($expected, $html);
    }
}
