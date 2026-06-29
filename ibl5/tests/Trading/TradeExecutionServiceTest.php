<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Repositories\Contracts\SalaryCapRepositoryInterface;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Trading\Contracts\TradeCashRepositoryInterface;
use Trading\Contracts\TradeOfferRepositoryInterface;
use Trading\Contracts\TradeProcessorInterface;
use Trading\Contracts\TradeValidatorInterface;
use Trading\TradeExecutionService;
use Trading\TradeItemType;
use Season\Season;

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
