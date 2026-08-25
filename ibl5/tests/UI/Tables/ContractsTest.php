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
     * @param int $ordinal Roster ordinal — must be <= JSB::WAIVERS_ORDINAL (960) to avoid 'player-waived'
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
}
