<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Trading\TradeRosterPreviewParamValidator;

class TradeRosterPreviewParamValidatorTest extends TestCase
{
    private TradeRosterPreviewParamValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new TradeRosterPreviewParamValidator();
    }

    protected function tearDown(): void
    {
        $_GET = [];
    }

    // ── validateTeamID ──────────────────────────────────────────────────────

    public function testValidateTeamIDReturnsZeroWhenMissing(): void
    {
        $_GET = [];
        $this->assertSame(0, $this->validator->validateTeamID());
    }

    public function testValidateTeamIDReturnsZeroWhenNonString(): void
    {
        $_GET = ['teamid' => ['1']];
        $this->assertSame(0, $this->validator->validateTeamID());
    }

    public function testValidateTeamIDReturnsZeroWhenZeroString(): void
    {
        $_GET = ['teamid' => '0'];
        $this->assertSame(0, $this->validator->validateTeamID());
    }

    public function testValidateTeamIDReturnsZeroWhenAlpha(): void
    {
        $_GET = ['teamid' => 'abc'];
        $this->assertSame(0, $this->validator->validateTeamID());
    }

    public function testValidateTeamIDReturnsParsedInt(): void
    {
        $_GET = ['teamid' => '12'];
        $this->assertSame(12, $this->validator->validateTeamID());
    }

    public function testValidateTeamIDStripsLeadingZeros(): void
    {
        $_GET = ['teamid' => '007'];
        $this->assertSame(7, $this->validator->validateTeamID());
    }

    // ── validatePidList ─────────────────────────────────────────────────────

    public function testValidatePidListReturnsEmptyArrayWhenMissing(): void
    {
        $_GET = [];
        $this->assertSame([], $this->validator->validatePidList('addPids'));
    }

    public function testValidatePidListReturnsEmptyArrayWhenEmptyString(): void
    {
        $_GET = ['addPids' => ''];
        $this->assertSame([], $this->validator->validatePidList('addPids'));
    }

    public function testValidatePidListReturnsParsedInts(): void
    {
        $_GET = ['addPids' => '1,2'];
        $this->assertSame([1, 2], $this->validator->validatePidList('addPids'));
    }

    public function testValidatePidListTrimsParts(): void
    {
        $_GET = ['addPids' => ' 3 , 4 '];
        $this->assertSame([3, 4], $this->validator->validatePidList('addPids'));
    }

    public function testValidatePidListAcceptsExactlyTwentyPids(): void
    {
        $_GET = ['addPids' => implode(',', range(1, 20))];
        $this->assertSame(range(1, 20), $this->validator->validatePidList('addPids'));
    }

    public function testValidatePidListReturnsNullWhenTwentyOnePids(): void
    {
        $_GET = ['addPids' => implode(',', range(1, 21))];
        $this->assertNull($this->validator->validatePidList('addPids'));
    }

    public function testValidatePidListReturnsNullWhenEmptyPart(): void
    {
        $_GET = ['addPids' => '1,,2'];
        $this->assertNull($this->validator->validatePidList('addPids'));
    }

    public function testValidatePidListReturnsNullWhenAlphaPart(): void
    {
        $_GET = ['addPids' => '1,abc'];
        $this->assertNull($this->validator->validatePidList('addPids'));
    }

    public function testValidatePidListReturnsNullWhenNegative(): void
    {
        $_GET = ['addPids' => '-1'];
        $this->assertNull($this->validator->validatePidList('addPids'));
    }

    // ── validateDisplay ─────────────────────────────────────────────────────

    public function testValidateDisplayReturnsRatings(): void
    {
        $_GET = ['display' => 'ratings'];
        $this->assertSame('ratings', $this->validator->validateDisplay());
    }

    public function testValidateDisplayReturnsTotalS(): void
    {
        $_GET = ['display' => 'total_s'];
        $this->assertSame('total_s', $this->validator->validateDisplay());
    }

    public function testValidateDisplayReturnsAvgS(): void
    {
        $_GET = ['display' => 'avg_s'];
        $this->assertSame('avg_s', $this->validator->validateDisplay());
    }

    public function testValidateDisplayReturnsPer36Mins(): void
    {
        $_GET = ['display' => 'per36mins'];
        $this->assertSame('per36mins', $this->validator->validateDisplay());
    }

    public function testValidateDisplayReturnsContracts(): void
    {
        $_GET = ['display' => 'contracts'];
        $this->assertSame('contracts', $this->validator->validateDisplay());
    }

    public function testValidateDisplayReturnsSplit(): void
    {
        $_GET = ['display' => 'split'];
        $this->assertSame('split', $this->validator->validateDisplay());
    }

    public function testValidateDisplayReturnsChunk(): void
    {
        $_GET = ['display' => 'chunk'];
        $this->assertSame('chunk', $this->validator->validateDisplay());
    }

    public function testValidateDisplayReturnsPlayoffs(): void
    {
        $_GET = ['display' => 'playoffs'];
        $this->assertSame('playoffs', $this->validator->validateDisplay());
    }

    public function testValidateDisplayDefaultsToRatingsWhenMissing(): void
    {
        $_GET = [];
        $this->assertSame('ratings', $this->validator->validateDisplay());
    }

    public function testValidateDisplayDefaultsToRatingsForBogusValue(): void
    {
        $_GET = ['display' => 'bogus'];
        $this->assertSame('ratings', $this->validator->validateDisplay());
    }

    public function testValidateDisplayIsCaseSensitive(): void
    {
        $_GET = ['display' => 'RATINGS'];
        $this->assertSame('ratings', $this->validator->validateDisplay());
    }

    // ── validateStringParam ─────────────────────────────────────────────────

    public function testValidateStringParamReturnsEmptyWhenMissing(): void
    {
        $_GET = [];
        $this->assertSame('', $this->validator->validateStringParam('myParam'));
    }

    public function testValidateStringParamReturnsEmptyWhenWhitespace(): void
    {
        $_GET = ['myParam' => '  '];
        $this->assertSame('', $this->validator->validateStringParam('myParam'));
    }

    public function testValidateStringParamTrimsWhitespace(): void
    {
        $_GET = ['myParam' => '  x  '];
        $this->assertSame('x', $this->validator->validateStringParam('myParam'));
    }

    // ── validateIntParam ────────────────────────────────────────────────────

    public function testValidateIntParamReturnsZeroWhenMissing(): void
    {
        $_GET = [];
        $this->assertSame(0, $this->validator->validateIntParam('myParam'));
    }

    public function testValidateIntParamReturnsZeroForAlpha(): void
    {
        $_GET = ['myParam' => 'abc'];
        $this->assertSame(0, $this->validator->validateIntParam('myParam'));
    }

    public function testValidateIntParamReturnsZeroForNegative(): void
    {
        $_GET = ['myParam' => '-5'];
        $this->assertSame(0, $this->validator->validateIntParam('myParam'));
    }

    public function testValidateIntParamReturnsParsedInt(): void
    {
        $_GET = ['myParam' => '12'];
        $this->assertSame(12, $this->validator->validateIntParam('myParam'));
    }

    // ── validateCashAmount ──────────────────────────────────────────────────

    public function testValidateCashAmountReturnsZeroWhenMissing(): void
    {
        $_GET = [];
        $this->assertSame(0, $this->validator->validateCashAmount('myCash'));
    }

    public function testValidateCashAmountReturnsZeroForAlpha(): void
    {
        $_GET = ['myCash' => 'abc'];
        $this->assertSame(0, $this->validator->validateCashAmount('myCash'));
    }

    public function testValidateCashAmountReturnsAmountFor2000(): void
    {
        $_GET = ['myCash' => '2000'];
        $this->assertSame(2000, $this->validator->validateCashAmount('myCash'));
    }

    public function testValidateCashAmountReturnsZeroFor2001(): void
    {
        $_GET = ['myCash' => '2001'];
        $this->assertSame(0, $this->validator->validateCashAmount('myCash'));
    }

    public function testValidateCashAmountReturnsZeroForZeroString(): void
    {
        $_GET = ['myCash' => '0'];
        $this->assertSame(0, $this->validator->validateCashAmount('myCash'));
    }

    // ── validateCashYearRange ───────────────────────────────────────────────

    public function testValidateCashYearRangeRejectsAttackInput(): void
    {
        $_GET = ['cashStartYear' => '1', 'cashEndYear' => '999999'];
        $this->assertSame([0, 0], $this->validator->validateCashYearRange(2031));
    }

    public function testValidateCashYearRangeAcceptsValidRange(): void
    {
        $_GET = ['cashStartYear' => '1', 'cashEndYear' => '3'];
        $this->assertSame([1, 3], $this->validator->validateCashYearRange(6));
    }

    public function testValidateCashYearRangeRejectsInvertedRange(): void
    {
        $_GET = ['cashStartYear' => '5', 'cashEndYear' => '2'];
        $this->assertSame([0, 0], $this->validator->validateCashYearRange(6));
    }

    public function testValidateCashYearRangeRejectsBoundaryOverMax(): void
    {
        $_GET = ['cashStartYear' => '1', 'cashEndYear' => '7'];
        $this->assertSame([0, 0], $this->validator->validateCashYearRange(6));
    }

    public function testValidateCashYearRangeAcceptsBoundaryAtMax(): void
    {
        $_GET = ['cashStartYear' => '6', 'cashEndYear' => '6'];
        $this->assertSame([6, 6], $this->validator->validateCashYearRange(6));
    }
}
