<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Trading\TradeRosterPreviewCashRowBuilder;
use Trading\TradeRosterPreviewParamValidator;
use Trading\Contracts\TradeRosterPreviewParamValidatorInterface;

class TradeRosterPreviewCashRowBuilderTest extends TestCase
{
    private const MAX_YEAR = TradeRosterPreviewCashRowBuilder::CASH_YEAR_FORWARD_HORIZON;

    private TradeRosterPreviewCashRowBuilder $sut;

    protected function setUp(): void
    {
        $this->sut = new TradeRosterPreviewCashRowBuilder(new TradeRosterPreviewParamValidator());
    }

    protected function tearDown(): void
    {
        $_GET = [];
    }

    // -------------------------------------------------------------------------
    // makeCashRow() — row shape and edge cases
    // -------------------------------------------------------------------------

    public function testMakeCashRowReturnsTheFullRowShape(): void
    {
        $actual = $this->sut->makeCashRow('| Cash to Boston', 7, [1 => 500], 1, 1, false);

        $expected = [
            'pid' => 0,
            'name' => '| Cash to Boston',
            'nickname' => '',
            'ordinal' => 100000,
            'teamid' => 7,
            'pos' => '',
            'age' => null,
            'color1' => null,
            'color2' => null,
            'r_fga' => 0, 'r_fgp' => 0, 'r_fta' => 0, 'r_ftp' => 0,
            'r_3ga' => 0, 'r_3gp' => 0, 'r_orb' => 0, 'r_drb' => 0,
            'r_ast' => 0, 'r_stl' => 0, 'r_tvr' => 0, 'r_blk' => 0, 'r_foul' => 0,
            'oo' => 0, 'od' => 0, 'r_drive_off' => 0, 'dd' => 0,
            'po' => 0, 'pd' => 0, 'r_trans_off' => 0, 'td' => 0,
            'clutch' => null, 'consistency' => null,
            'talent' => 0, 'skill' => 0, 'intangibles' => 0,
            'loyalty' => null, 'playing_time' => null, 'winner' => null,
            'tradition' => null, 'security' => null,
            'exp' => 1,
            'bird' => null,
            'cy' => 1,
            'cyt' => 1,
            'salary_yr1' => 500, 'salary_yr2' => 0, 'salary_yr3' => 0,
            'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0,
            'draftyear' => 0, 'draftround' => 0, 'draftpickno' => 0,
            'draftedby' => '', 'draftedbycurrentname' => '', 'college' => '',
            'htft' => 0, 'htin' => 0, 'wt' => 0,
            'injured' => null,
            'retired' => 0,
            'droptime' => 0,
            'isCashRow' => true,
        ];

        $this->assertSame($expected, $actual);
    }

    public function testMakeCashRowMapsCyIndexOneToSalaryYr1AndSixToSalaryYr6(): void
    {
        $amounts = [1 => 100, 2 => 200, 3 => 300, 4 => 400, 5 => 500, 6 => 600];

        $actual = $this->sut->makeCashRow('| Cash to Boston', 1, $amounts, 1, 6, false);

        $this->assertSame(100, $actual['salary_yr1']);
        $this->assertSame(200, $actual['salary_yr2']);
        $this->assertSame(300, $actual['salary_yr3']);
        $this->assertSame(400, $actual['salary_yr4']);
        $this->assertSame(500, $actual['salary_yr5']);
        $this->assertSame(600, $actual['salary_yr6']);
        $this->assertSame(6, $actual['cyt']);
    }

    public function testMakeCashRowSilentlyDropsYearSevenAmount(): void
    {
        // cyIndex 7 falls outside [1..6]; year-7 amount is silently discarded (Preserve-don't-fix)
        $amounts = [1 => 100, 2 => 200, 3 => 300, 4 => 400, 5 => 500, 6 => 600, 7 => 700];

        $actual = $this->sut->makeCashRow('| Cash to Boston', 1, $amounts, 1, 7, false);

        $this->assertSame(100, $actual['salary_yr1']);
        $this->assertSame(200, $actual['salary_yr2']);
        $this->assertSame(300, $actual['salary_yr3']);
        $this->assertSame(400, $actual['salary_yr4']);
        $this->assertSame(500, $actual['salary_yr5']);
        $this->assertSame(600, $actual['salary_yr6']);
        $this->assertSame(6, $actual['cyt']);
    }

    public function testMakeCashRowNegatesAmountsWhenNegateIsTrue(): void
    {
        $amounts = [1 => 100, 2 => 200];

        $actual = $this->sut->makeCashRow('| Cash from Boston', 1, $amounts, 1, 2, true);

        $this->assertSame(-100, $actual['salary_yr1']);
        $this->assertSame(-200, $actual['salary_yr2']);
        // Negated non-zero amounts still count toward totalYears
        $this->assertSame(2, $actual['cyt']);
    }

    public function testMakeCashRowDefaultsCytToOneWhenAllAmountsAreZero(): void
    {
        $amounts = [1 => 0, 2 => 0];

        $actual = $this->sut->makeCashRow('| Cash to Boston', 1, $amounts, 1, 2, false);

        $this->assertSame(0, $actual['salary_yr1']);
        $this->assertSame(0, $actual['salary_yr2']);
        $this->assertSame(1, $actual['cyt']);
    }

    public function testMakeCashRowUsesZeroForMissingYearKey(): void
    {
        // amounts[2] is absent — the ?? 0 path fills it with 0
        $amounts = [1 => 100];

        $actual = $this->sut->makeCashRow('| Cash to Boston', 1, $amounts, 1, 2, false);

        $this->assertSame(100, $actual['salary_yr1']);
        $this->assertSame(0, $actual['salary_yr2']);
        $this->assertSame(1, $actual['cyt']);
    }

    // -------------------------------------------------------------------------
    // buildCashRows() — guard cases returning []
    // -------------------------------------------------------------------------

    public function testBuildCashRowsReturnsEmptyWhenUserTeamIsEmpty(): void
    {
        $_GET = [
            // userTeam intentionally absent → validateStringParam returns ''
            'partnerTeam' => 'Boston',
            'userTeamId' => '1',
            'cashStartYear' => '1',
            'cashEndYear' => '1',
            'userCash1' => '500',
        ];

        $this->assertSame([], $this->sut->buildCashRows(1, self::MAX_YEAR));
    }

    public function testBuildCashRowsReturnsEmptyWhenPartnerTeamIsEmpty(): void
    {
        $_GET = [
            'userTeam' => 'Miami',
            // partnerTeam intentionally absent → validateStringParam returns ''
            'userTeamId' => '1',
            'cashStartYear' => '1',
            'cashEndYear' => '1',
            'userCash1' => '500',
        ];

        $this->assertSame([], $this->sut->buildCashRows(1, self::MAX_YEAR));
    }

    public function testBuildCashRowsReturnsEmptyWhenCashStartYearIsZero(): void
    {
        $_GET = [
            'userTeam' => 'Miami',
            'partnerTeam' => 'Boston',
            'userTeamId' => '1',
            // cashStartYear absent → validateIntParam returns 0
            'cashEndYear' => '1',
            'userCash1' => '500',
        ];

        $this->assertSame([], $this->sut->buildCashRows(1, self::MAX_YEAR));
    }

    public function testBuildCashRowsReturnsEmptyWhenCashEndYearIsZero(): void
    {
        $_GET = [
            'userTeam' => 'Miami',
            'partnerTeam' => 'Boston',
            'userTeamId' => '1',
            'cashStartYear' => '1',
            // cashEndYear absent → validateIntParam returns 0
            'userCash1' => '500',
        ];

        $this->assertSame([], $this->sut->buildCashRows(1, self::MAX_YEAR));
    }

    public function testBuildCashRowsReturnsEmptyWhenAllCashAmountsAreZero(): void
    {
        $_GET = [
            'userTeam' => 'Miami',
            'partnerTeam' => 'Boston',
            'userTeamId' => '1',
            'cashStartYear' => '1',
            'cashEndYear' => '1',
            'userCash1' => '0',
            'partnerCash1' => '0',
        ];

        $this->assertSame([], $this->sut->buildCashRows(1, self::MAX_YEAR));
    }

    // -------------------------------------------------------------------------
    // buildCashRows() — viewing own team
    // -------------------------------------------------------------------------

    public function testBuildCashRowsViewingOwnTeamWithUserCashReturnsOneCashToRow(): void
    {
        $_GET = [
            'userTeam' => 'Miami',
            'partnerTeam' => 'Boston',
            'userTeamId' => '1',
            'cashStartYear' => '1',
            'cashEndYear' => '1',
            'userCash1' => '500',
            // partnerCash1 absent → 0, so no partner-cash row
        ];

        $rows = $this->sut->buildCashRows(1, self::MAX_YEAR);

        $this->assertCount(1, $rows);
        $this->assertSame('| Cash to Boston', $rows[0]['name']);
        $this->assertSame(1, $rows[0]['teamid']);
    }

    public function testBuildCashRowsViewingOwnTeamWithPartnerCashReturnsOneCashFromRow(): void
    {
        $_GET = [
            'userTeam' => 'Miami',
            'partnerTeam' => 'Boston',
            'userTeamId' => '1',
            'cashStartYear' => '1',
            'cashEndYear' => '1',
            // userCash1 absent → 0, so no user-cash row
            'partnerCash1' => '300',
        ];

        $rows = $this->sut->buildCashRows(1, self::MAX_YEAR);

        $this->assertCount(1, $rows);
        $this->assertSame('| Cash from Boston', $rows[0]['name']);
        $this->assertSame(1, $rows[0]['teamid']);
    }

    public function testBuildCashRowsViewingOwnTeamWithBothCashReturnsTwoRowsInOrder(): void
    {
        $_GET = [
            'userTeam' => 'Miami',
            'partnerTeam' => 'Boston',
            'userTeamId' => '1',
            'cashStartYear' => '1',
            'cashEndYear' => '1',
            'userCash1' => '500',
            'partnerCash1' => '300',
        ];

        $rows = $this->sut->buildCashRows(1, self::MAX_YEAR);

        $this->assertCount(2, $rows);
        // Cash to comes before Cash from
        $this->assertSame('| Cash to Boston', $rows[0]['name']);
        $this->assertSame('| Cash from Boston', $rows[1]['name']);
    }

    // -------------------------------------------------------------------------
    // buildCashRows() — viewing partner's team
    // -------------------------------------------------------------------------

    public function testBuildCashRowsViewingPartnerTeamHasCorrectLabelsAndTeamId(): void
    {
        $_GET = [
            'userTeam' => 'Miami',
            'partnerTeam' => 'Boston',
            'userTeamId' => '1',
            'cashStartYear' => '1',
            'cashEndYear' => '1',
            'userCash1' => '500',
            'partnerCash1' => '300',
        ];

        // viewingTeamId=2 !== userTeamId=1 → viewing partner's team
        $rows = $this->sut->buildCashRows(2, self::MAX_YEAR);

        $this->assertCount(2, $rows);
        // Partner's cash going to user's team (Miami)
        $this->assertSame('| Cash to Miami', $rows[0]['name']);
        $this->assertSame(2, $rows[0]['teamid']);
        // User's cash (Miami) received by partner
        $this->assertSame('| Cash from Miami', $rows[1]['name']);
        $this->assertSame(2, $rows[1]['teamid']);
    }

    // -------------------------------------------------------------------------
    // buildCashRows() — XSS escaping
    // -------------------------------------------------------------------------

    public function testBuildCashRowsEscapesXssInPartnerTeamName(): void
    {
        $_GET = [
            'userTeam' => 'Miami',
            'partnerTeam' => '<script>alert(1)</script>',
            'userTeamId' => '1',
            'cashStartYear' => '1',
            'cashEndYear' => '1',
            'userCash1' => '500',
        ];

        $rows = $this->sut->buildCashRows(1, self::MAX_YEAR);

        $this->assertCount(1, $rows);
        $this->assertStringNotContainsString('<script>', $rows[0]['name']);
        $this->assertStringContainsString('| Cash to ', $rows[0]['name']);
    }

    // -------------------------------------------------------------------------
    // buildCashRows() — horizon bound
    // -------------------------------------------------------------------------

    public function testBuildCashRowsRejectsOverHorizonEndYear(): void
    {
        $_GET = [
            'userTeam'      => 'Miami',
            'partnerTeam'   => 'Boston',
            'userTeamId'    => '1',
            'cashStartYear' => '1',
            'cashEndYear'   => '999999',
            'userCash1'     => '500',
        ];

        $this->assertSame([], $this->sut->buildCashRows(1, self::MAX_YEAR));
    }

    // -------------------------------------------------------------------------
    // buildCashRows() — injected validator contract
    // -------------------------------------------------------------------------

    public function testBuildCashRowsUsesTheInjectedValidator(): void
    {
        /** @var TradeRosterPreviewParamValidatorInterface&\PHPUnit\Framework\MockObject\MockObject $mockValidator */
        $mockValidator = $this->createMock(TradeRosterPreviewParamValidatorInterface::class);
        // validateStringParam is called for both 'userTeam' and 'partnerTeam' before the guard check
        $mockValidator->expects($this->atLeastOnce())->method('validateStringParam')->willReturn('');
        // validateCashYearRange is destructured before the guard, so it must return a two-element tuple
        $mockValidator->method('validateCashYearRange')->willReturn([0, 0]);

        $sut = new TradeRosterPreviewCashRowBuilder($mockValidator);

        $this->assertSame([], $sut->buildCashRows(1, self::MAX_YEAR));
    }
}
