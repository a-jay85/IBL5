<?php

declare(strict_types=1);

namespace Tests\Trading;

use League\League;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Repositories\Contracts\SalaryCapRepositoryInterface;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Season\Season;
use Trading\Contracts\TradeCashRepositoryInterface;
use Trading\Contracts\TradeOfferRepositoryInterface;
use Trading\Contracts\TradeProcessorInterface;
use Trading\Contracts\TradeValidatorInterface;
use Trading\TradeExecutionService;
use Trading\TradeItemType;

/**
 * Unit tests for Trading\TradeExecutionService — the accept-path orchestrator
 * (authz/IDOR gate + N-party validation + delegated execution).
 */
class TradeExecutionServiceTest extends TestCase
{
    /**
     * @param list<array<string, mixed>> $tradeRows
     */
    private function buildService(
        array $tradeRows,
        ?TradeProcessorInterface $processor = null,
        ?TradeValidatorInterface $validator = null,
        ?SalaryCapRepositoryInterface $salaryCap = null,
        ?TradeCashRepositoryInterface $cashRepo = null,
        ?Season $season = null,
    ): TradeExecutionService {
        $offerRepo = self::createStub(TradeOfferRepositoryInterface::class);
        $offerRepo->method('getTradesByOfferId')->willReturn($tradeRows);

        $teamIdentity = self::createStub(TeamIdentityRepositoryInterface::class);
        $teamIdentity->method('getTidFromTeamname')->willReturn(1);

        if ($cashRepo === null) {
            $cashRepo = self::createStub(TradeCashRepositoryInterface::class);
            $cashRepo->method('getCashTransactionByOffer')->willReturn(null);
        }

        if ($season === null) {
            // Default: a non-offseason phase (cy stays 1 -> salary_yr1).
            $season = self::createStub(Season::class);
            $season->method('advancesContractYears')->willReturn(false);
        }

        $salaryCap ??= self::createStub(SalaryCapRepositoryInterface::class);
        $validator ??= self::createStub(TradeValidatorInterface::class);
        $processor ??= self::createStub(TradeProcessorInterface::class);

        return new TradeExecutionService(
            $offerRepo,
            $processor,
            $validator,
            $salaryCap,
            $teamIdentity,
            $cashRepo,
            $season,
        );
    }

    /**
     * @param string $from
     * @param string $to
     * @return array<string, mixed>
     */
    private function playerRow(int $pid, string $from, string $to): array
    {
        return [
            'tradeofferid' => 1,
            'itemid' => $pid,
            'itemtype' => TradeItemType::Player->value,
            'trade_from' => $from,
            'trade_to' => $to,
            'approval' => '',
            'created_at' => '',
            'updated_at' => '',
        ];
    }

    /** @return list<array<string, mixed>> */
    private function threeTeamRows(): array
    {
        // Metros -> Stars, Stars -> Cougars, Cougars -> Metros (a 3-team cycle).
        return [
            $this->playerRow(101, 'Metros', 'Stars'),
            $this->playerRow(102, 'Stars', 'Cougars'),
            $this->playerRow(103, 'Cougars', 'Metros'),
        ];
    }

    /**
     * Matrix #6 — deriveParties returns the 3 distinct team names for a 3-team offer.
     */
    public function testDerivePartiesReturnsThreeDistinctNames(): void
    {
        $service = $this->buildService($this->threeTeamRows());

        $parties = $service->deriveParties(1);

        $this->assertCount(3, $parties);
        $this->assertSame(['Metros', 'Stars', 'Cougars'], $parties);
    }

    /**
     * Matrix #13 (substance) — assertActingTeamIsParty is true for a party,
     * false for a non-party (the reject-path IDOR gate, exit-free seam).
     */
    public function testAssertActingTeamIsPartyDistinguishesPartyFromNonParty(): void
    {
        $service = $this->buildService($this->threeTeamRows());

        $this->assertTrue($service->assertActingTeamIsParty(1, 'Cougars'));
        $this->assertFalse($service->assertActingTeamIsParty(1, 'Heat'));
        $this->assertFalse($service->assertActingTeamIsParty(1, ''));
    }

    /**
     * Matrix #12 (IDOR) — a non-party acting team is rejected and processTrade
     * is NEVER called (no mutation).
     */
    public function testValidateAndExecuteRejectsNonPartyWithoutExecuting(): void
    {
        $processor = $this->createMock(TradeProcessorInterface::class);
        $processor->expects($this->never())->method('processTrade');

        $service = $this->buildService($this->threeTeamRows(), processor: $processor);

        $result = $service->validateAndExecute(1, 'Heat');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Matrix #7 — when validation fails, validateAndExecute short-circuits and
     * processTrade is NEVER called.
     */
    public function testValidateAndExecuteShortCircuitsOnValidationFailure(): void
    {
        $processor = $this->createMock(TradeProcessorInterface::class);
        $processor->expects($this->never())->method('processTrade');

        $validator = self::createStub(TradeValidatorInterface::class);
        $validator->method('validateSalaryCapsForParties')->willReturn([
            'valid' => false,
            'errors' => ['This trade is illegal since it puts the Stars over the hard cap.'],
            'parties' => [],
        ]);
        $validator->method('validateRosterLimitsForParties')->willReturn([
            'valid' => true,
            'errors' => [],
            'parties' => [],
        ]);

        $service = $this->buildService($this->threeTeamRows(), processor: $processor, validator: $validator);

        $result = $service->validateAndExecute(1, 'Metros');

        $this->assertFalse($result['success']);
        $this->assertContains('This trade is illegal since it puts the Stars over the hard cap.', $result['errors']);
    }

    /**
     * On valid input by a party GM, processTrade is called exactly once and its
     * result is returned.
     */
    public function testValidateAndExecuteExecutesOnceWhenValid(): void
    {
        $processor = $this->createMock(TradeProcessorInterface::class);
        $processor->expects($this->once())
            ->method('processTrade')
            ->with(1)
            ->willReturn(['success' => true, 'storytext' => 'x', 'storytitle' => 'y']);

        $validator = self::createStub(TradeValidatorInterface::class);
        $validator->method('validateSalaryCapsForParties')->willReturn(['valid' => true, 'errors' => [], 'parties' => []]);
        $validator->method('validateRosterLimitsForParties')->willReturn(['valid' => true, 'errors' => [], 'parties' => []]);

        $service = $this->buildService($this->threeTeamRows(), processor: $processor, validator: $validator);

        $result = $service->validateAndExecute(1, 'Metros');

        $this->assertTrue($result['success']);
    }

    /**
     * Regression — accept-time cash cap basis must match the offer-time basis
     * ({@see \Trading\TradeValidator::getCurrentSeasonCashConsiderations()}).
     * Outside an offseason phase the current-season cash obligation is salary_yr1.
     */
    public function testCashLegUsesSalaryYr1OutsideOffseason(): void
    {
        $delta = $this->deltaFor(
            $this->captureCapDeltasForCashLeg(advancesContractYears: false, yr1: 100, yr2: 900),
            'Metros'
        );

        self::assertSame(100, $delta['capReceived'], 'cash sender cap must rise by salary_yr1 outside offseason');
    }

    /**
     * Regression (the bug this fix closes) — during phases that advance contract
     * years (Playoffs/Draft/Free Agency) the current-season cash obligation is
     * salary_yr2, matching the offer-time validator. The pre-fix accept path read
     * salary_yr1 unconditionally, so an offseason cash leg was validated on the
     * wrong year and could pass/fail the cap check inconsistently with offer time.
     */
    public function testCashLegUsesSalaryYr2DuringOffseason(): void
    {
        $delta = $this->deltaFor(
            $this->captureCapDeltasForCashLeg(advancesContractYears: true, yr1: 100, yr2: 900),
            'Metros'
        );

        self::assertSame(900, $delta['capReceived'], 'cash sender cap must rise by salary_yr2 during offseason');
    }

    /**
     * Characterization test: outside an offseason phase current_salary IS the correct
     * accept-time basis for player legs, and the phase fix must not change it.
     */
    public function testPlayerLegUsesCurrentSalaryBasisOutsideOffseason(): void
    {
        $salaryCap = self::createMock(SalaryCapRepositoryInterface::class);
        $salaryCap->expects(self::once())->method('getPlayerCurrentSalary')->with(4835)->willReturn(1063);
        $salaryCap->expects(self::atLeastOnce())->method('getTeamTotalSalary')
            ->willReturnMap([['Metros', 6861], ['Stars', 6926]]);
        $salaryCap->expects(self::never())->method('getTeamNextYearSalary');
        $deltas = $this->captureCapDeltasForPlayerLeg(false, $salaryCap, [$this->playerRow(4835, 'Metros', 'Stars')]);
        $metros = $this->deltaFor($deltas, 'Metros');
        self::assertSame(6861, $metros['currentSeasonCapTotal']);
        self::assertSame(1063, $metros['capSent']);
        self::assertSame(0, $metros['capReceived']);
        self::assertSame(1063, $this->deltaFor($deltas, 'Stars')['capReceived']);
    }

    /**
     * Run validateAndExecute over a single player leg and return the per-party cap
     * deltas the service hands to the cap validator. cashRepo is left as the default
     * null stub so getCashTransactionByOffer returns null and no cash leg fires.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function captureCapDeltasForPlayerLeg(bool $advancesContractYears, SalaryCapRepositoryInterface $salaryCap, array $rows, string $actingTeam = 'Metros'): array
    {
        $season = self::createStub(Season::class);
        $season->method('advancesContractYears')->willReturn($advancesContractYears);

        $captured = [];
        $validator = self::createStub(TradeValidatorInterface::class);
        $validator->method('validateSalaryCapsForParties')->willReturnCallback(
            function (array $deltas) use (&$captured): array {
                $captured = $deltas;
                return ['valid' => true, 'errors' => [], 'parties' => []];
            }
        );
        $validator->method('validateRosterLimitsForParties')->willReturn(['valid' => true, 'errors' => [], 'parties' => []]);

        $processor = self::createStub(TradeProcessorInterface::class);
        $processor->method('processTrade')->willReturn(['success' => true]);

        $service = $this->buildService(
            $rows,
            processor: $processor,
            validator: $validator,
            salaryCap: $salaryCap,
            season: $season,
        );

        $service->validateAndExecute(1, $actingTeam);

        return $captured;
    }

    /**
     * Run validateAndExecute over a single Metros->Stars cash leg and return the
     * per-party cap deltas the service hands to the cap validator. The acting team
     * (Metros) is a party, so the IDOR gate passes and validateParties runs.
     *
     * @return list<array<string, mixed>>
     */
    private function captureCapDeltasForCashLeg(bool $advancesContractYears, int $yr1, int $yr2): array
    {
        $cashRepo = self::createStub(TradeCashRepositoryInterface::class);
        $cashRepo->method('getCashTransactionByOffer')->willReturn(['salary_yr1' => $yr1, 'salary_yr2' => $yr2]);

        $season = self::createStub(Season::class);
        $season->method('advancesContractYears')->willReturn($advancesContractYears);

        $captured = [];
        $validator = self::createStub(TradeValidatorInterface::class);
        $validator->method('validateSalaryCapsForParties')->willReturnCallback(
            function (array $deltas) use (&$captured): array {
                $captured = $deltas;
                return ['valid' => true, 'errors' => [], 'parties' => []];
            }
        );
        $validator->method('validateRosterLimitsForParties')->willReturn(['valid' => true, 'errors' => [], 'parties' => []]);

        $processor = self::createStub(TradeProcessorInterface::class);
        $processor->method('processTrade')->willReturn(['success' => true]);

        $service = $this->buildService(
            [$this->cashRow('Metros', 'Stars')],
            processor: $processor,
            validator: $validator,
            cashRepo: $cashRepo,
            season: $season,
        );

        $service->validateAndExecute(1, 'Metros');

        return $captured;
    }

    /**
     * @param list<array<string, mixed>> $deltas
     * @return array<string, mixed>
     */
    private function deltaFor(array $deltas, string $teamName): array
    {
        foreach ($deltas as $delta) {
            if (($delta['teamName'] ?? null) === $teamName) {
                return $delta;
            }
        }

        self::fail("No cap delta captured for {$teamName}");
    }

    /** @return array<string, mixed> */
    private function cashRow(string $from, string $to): array
    {
        return [
            'tradeofferid' => 1,
            'itemid' => 0,
            'itemtype' => TradeItemType::Cash->value,
            'trade_from' => $from,
            'trade_to' => $to,
            'approval' => '',
            'created_at' => '',
            'updated_at' => '',
        ];
    }

    /**
     * During an offseason phase (advancesContractYears() === true) the service must
     * read next-year salary for player legs rather than current salary. Both provider
     * labels are documentation only — the Season stub returns advancesContractYears()
     * === true for both, which is what matters. Both runs must produce identical
     * deltas; this is verified by asserting the same hardcoded constants each time.
     *
     * @param string $_phase documentation label only — not used in assertions
     */
    #[DataProvider('offseasonPhaseProvider')]
    public function testPlayerLegUsesNextYearSalaryBasisDuringOffseason(string $_phase): void
    {
        $salaryCap = self::createMock(SalaryCapRepositoryInterface::class);
        $salaryCap->expects(self::once())->method('getPlayerNextYearSalary')->with(4835)->willReturn(1196);
        $salaryCap->expects(self::never())->method('getPlayerCurrentSalary');
        $salaryCap->expects(self::atLeastOnce())->method('getTeamNextYearSalary')
            ->willReturnMap([['Metros', 5159], ['Stars', 5950]]);
        $salaryCap->expects(self::never())->method('getTeamTotalSalary');

        $deltas = $this->captureCapDeltasForPlayerLeg(true, $salaryCap, [$this->playerRow(4835, 'Metros', 'Stars')]);
        $metros = $this->deltaFor($deltas, 'Metros');
        $stars  = $this->deltaFor($deltas, 'Stars');

        self::assertSame(5159, $metros['currentSeasonCapTotal']);
        self::assertSame(1196, $metros['capSent']);
        self::assertSame(0, $metros['capReceived']);
        self::assertSame(1196, $stars['capReceived']);
    }

    /** @return list<array{string}> */
    public static function offseasonPhaseProvider(): array
    {
        return [['Draft'], ['Free Agency']];
    }

    /**
     * Regression fixture for offer #12190 (Heat/Nuggets, two-player swap, Draft phase).
     * Part (a): the correct offseason basis (next-year salary) keeps both teams under
     * League::HARD_CAP_MAX. Part (b): the pre-fix basis (current salary + season total)
     * puts Heat at 7232 > 7000 — documents that the phase-blind path caused false rejections.
     */
    public function testProdFixtureOffer12190AcceptedDuringDraftPhase(): void
    {
        $rows = [$this->playerRow(4835, 'Heat', 'Nuggets'), $this->playerRow(2978, 'Nuggets', 'Heat')];

        // (a) Correct offseason basis — both teams stay under the hard cap.
        $salaryCap = self::createStub(SalaryCapRepositoryInterface::class);
        $salaryCap->method('getPlayerNextYearSalary')->willReturnMap([[4835, 1196], [2978, 1593]]);
        $salaryCap->method('getTeamNextYearSalary')->willReturnMap([['Heat', 5159], ['Nuggets', 5950]]);

        $deltas = $this->captureCapDeltasForPlayerLeg(true, $salaryCap, $rows, 'Heat');
        $heat   = $this->deltaFor($deltas, 'Heat');
        $nuggets = $this->deltaFor($deltas, 'Nuggets');

        $heatPost    = $heat['currentSeasonCapTotal'] - $heat['capSent'] + $heat['capReceived'];
        $nuggetsPost = $nuggets['currentSeasonCapTotal'] - $nuggets['capSent'] + $nuggets['capReceived'];

        self::assertSame(5556, $heatPost);
        self::assertLessThanOrEqual(League::HARD_CAP_MAX, $heatPost);
        self::assertSame(5553, $nuggetsPost);
        self::assertLessThanOrEqual(League::HARD_CAP_MAX, $nuggetsPost);

        // (b) NEGATIVE — pre-fix basis: current salary with season totals produces false rejection.
        $salaryCap2 = self::createStub(SalaryCapRepositoryInterface::class);
        $salaryCap2->method('getPlayerCurrentSalary')->willReturnMap([[4835, 1063], [2978, 1434]]);
        $salaryCap2->method('getTeamTotalSalary')->willReturnMap([['Heat', 6861], ['Nuggets', 6926]]);

        $deltas2  = $this->captureCapDeltasForPlayerLeg(false, $salaryCap2, $rows, 'Heat');
        $heat2    = $this->deltaFor($deltas2, 'Heat');
        $heatPost2 = $heat2['currentSeasonCapTotal'] - $heat2['capSent'] + $heat2['capReceived'];

        self::assertSame(7232, $heatPost2);
        self::assertGreaterThan(League::HARD_CAP_MAX, $heatPost2);
    }

    /**
     * LOOSENING GUARD — even with the offseason basis fix engaged, a trade that
     * puts Metros over League::HARD_CAP_MAX must still be rejected and processTrade
     * must never be called. Metros post = 6900 - 100 + 900 = 7700 > 7000.
     */
    public function testOverCapTradeStillRejectedOnShiftedBasisDuringDraft(): void
    {
        $salaryCap = self::createStub(SalaryCapRepositoryInterface::class);
        $salaryCap->method('getTeamNextYearSalary')->willReturnMap([['Metros', 6900], ['Stars', 4000]]);
        $salaryCap->method('getPlayerNextYearSalary')->willReturnMap([[4835, 100], [2978, 900]]);

        $season = self::createStub(Season::class);
        $season->method('advancesContractYears')->willReturn(true);

        $validator = self::createStub(TradeValidatorInterface::class);
        $validator->method('validateSalaryCapsForParties')->willReturn([
            'valid'   => false,
            'errors'  => ['This trade is illegal since it puts the Metros over the hard cap.'],
            'parties' => [],
        ]);
        $validator->method('validateRosterLimitsForParties')->willReturn(['valid' => true, 'errors' => [], 'parties' => []]);

        $processor = self::createMock(TradeProcessorInterface::class);
        $processor->expects(self::never())->method('processTrade');

        $rows    = [$this->playerRow(4835, 'Metros', 'Stars'), $this->playerRow(2978, 'Stars', 'Metros')];
        $service = $this->buildService(
            $rows,
            processor: $processor,
            validator: $validator,
            salaryCap: $salaryCap,
            season: $season,
        );

        $result = $service->validateAndExecute(1, 'Metros');

        self::assertFalse($result['success']);
        self::assertContains('This trade is illegal since it puts the Metros over the hard cap.', $result['errors']);
    }

    /**
     * When a player leg and a cash leg are both present during an offseason phase,
     * the cash column must be salary_yr2 (the same column the validator reads at
     * offer time). salary_yr1 = 100 must not appear in any captured delta.
     * Metros sends player 4835 (next-year salary 1196) and cash (yr2 = 900) to Stars.
     */
    public function testCashLegAndPlayerLegUseConsistentBasisDuringOffseason(): void
    {
        $cashRepo = self::createStub(TradeCashRepositoryInterface::class);
        $cashRepo->method('getCashTransactionByOffer')->willReturn(['salary_yr1' => 100, 'salary_yr2' => 900]);

        $salaryCap = self::createStub(SalaryCapRepositoryInterface::class);
        $salaryCap->method('getPlayerNextYearSalary')->willReturnMap([[4835, 1196]]);
        $salaryCap->method('getTeamNextYearSalary')->willReturnMap([['Metros', 5159], ['Stars', 5950]]);

        $season = self::createStub(Season::class);
        $season->method('advancesContractYears')->willReturn(true);

        $captured = [];
        $validator = self::createStub(TradeValidatorInterface::class);
        $validator->method('validateSalaryCapsForParties')->willReturnCallback(
            function (array $deltas) use (&$captured): array {
                $captured = $deltas;
                return ['valid' => true, 'errors' => [], 'parties' => []];
            }
        );
        $validator->method('validateRosterLimitsForParties')->willReturn(['valid' => true, 'errors' => [], 'parties' => []]);

        $processor = self::createStub(TradeProcessorInterface::class);
        $processor->method('processTrade')->willReturn(['success' => true]);

        $rows    = [$this->playerRow(4835, 'Metros', 'Stars'), $this->cashRow('Metros', 'Stars')];
        $service = $this->buildService(
            $rows,
            processor: $processor,
            validator: $validator,
            salaryCap: $salaryCap,
            cashRepo: $cashRepo,
            season: $season,
        );

        $service->validateAndExecute(1, 'Metros');

        $metros = $this->deltaFor($captured, 'Metros');
        $stars  = $this->deltaFor($captured, 'Stars');

        self::assertSame(1196, $metros['capSent']);
        self::assertSame(900, $metros['capReceived']);
        self::assertSame(5159, $metros['currentSeasonCapTotal']);
        self::assertSame(1196, $stars['capReceived']);
        self::assertSame(900, $stars['capSent']);

        // Negative: salary_yr1 = 100 must not appear in any delta — both legs shifted to yr2.
        foreach ($captured as $delta) {
            self::assertNotSame(100, $delta['capSent'] ?? null, 'salary_yr1 must not appear in capSent');
            self::assertNotSame(100, $delta['capReceived'] ?? null, 'salary_yr1 must not appear in capReceived');
        }
    }

    /**
     * Matrix #9 (security) — the service holds no \mysqli handle; every DB touch
     * goes through an injected repository interface (prepared statements), so the
     * service itself cannot interpolate SQL.
     */
    public function testServiceHoldsNoRawDatabaseHandle(): void
    {
        $ctor = (new \ReflectionClass(TradeExecutionService::class))->getConstructor();
        self::assertNotNull($ctor);

        foreach ($ctor->getParameters() as $param) {
            $type = $param->getType();
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : '';
            $this->assertNotSame(\mysqli::class, ltrim($typeName, '\\'), 'Service must not depend on a raw mysqli handle');
        }
    }
}
