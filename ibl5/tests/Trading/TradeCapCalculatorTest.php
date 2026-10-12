<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Trading\Contracts\BuyoutLedgerRepositoryInterface;
use Trading\TradeCapCalculator;
use Trading\TradeValidator;
use Season\Season;

/**
 * @covers \Trading\TradeCapCalculator
 */
class TradeCapCalculatorTest extends TestCase
{
    /**
     * @return array{offeringTeam: string, listeningTeam: string, switchCounter: int, fieldsCounter: int, check: array<int, string|null>, index: array<int, string>, type: array<int, string>, contract: array<int, string>, userSendsCash: array<int, int>, partnerSendsCash: array<int, int>}
     */
    private function makeTradeData(int $switchCounter = 0, int $fieldsCounter = 0): array
    {
        return [
            'offeringTeam' => 'Lakers',
            'listeningTeam' => 'Celtics',
            'switchCounter' => $switchCounter,
            'fieldsCounter' => $fieldsCounter,
            'check' => [],
            'index' => [],
            'type' => [],
            'contract' => [],
            'userSendsCash' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0],
            'partnerSendsCash' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0],
        ];
    }

    /**
     * @return array{cy: int, salary_yr1: int, salary_yr2: int, salary_yr3: int, salary_yr4: int, salary_yr5: int, salary_yr6: int}
     */
    private function makeDiscriminatorRow(): array
    {
        // yr1=100, yr2=500: yr1 ≠ yr2 proves cy-offset changes the sum
        return ['cy' => 1, 'salary_yr1' => 100, 'salary_yr2' => 500, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0];
    }

    /** V6: advances=true → cy 1→2 → yr2=500 per team */
    public function testCalculateSalaryCapDataAdvancesTrue(): void
    {
        $commonRepo = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $commonRepo->method('getTidFromTeamname')->willReturn(1);
        $cashConsiderationRepo = self::createStub(BuyoutLedgerRepositoryInterface::class);
        $cashConsiderationRepo->method('getTeamCashForSalary')->willReturn([$this->makeDiscriminatorRow()]);
        $season = self::createStub(Season::class);
        $season->method('advancesContractYears')->willReturn(true);
        $validator = self::createStub(TradeValidator::class);
        $validator->method('getCurrentSeasonCashConsiderations')->willReturn(['cashSentToThem' => 0, 'cashSentToMe' => 0]);

        $calculator = new TradeCapCalculator($commonRepo, $cashConsiderationRepo, $season, $validator);

        $this->assertSame(
            ['userCurrentSeasonCapTotal' => 500, 'partnerCurrentSeasonCapTotal' => 500, 'userCapSentToPartner' => 0, 'partnerCapSentToUser' => 0],
            $calculator->calculateSalaryCapData($this->makeTradeData())
        );
    }

    /** V7: advances=false → cy stays 1 → yr1=100 per team */
    public function testCalculateSalaryCapDataAdvancesFalse(): void
    {
        $commonRepo = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $commonRepo->method('getTidFromTeamname')->willReturn(1);
        $cashConsiderationRepo = self::createStub(BuyoutLedgerRepositoryInterface::class);
        $cashConsiderationRepo->method('getTeamCashForSalary')->willReturn([$this->makeDiscriminatorRow()]);
        $season = self::createStub(Season::class);
        $season->method('advancesContractYears')->willReturn(false);
        $validator = self::createStub(TradeValidator::class);
        $validator->method('getCurrentSeasonCashConsiderations')->willReturn(['cashSentToThem' => 0, 'cashSentToMe' => 0]);

        $calculator = new TradeCapCalculator($commonRepo, $cashConsiderationRepo, $season, $validator);

        $this->assertSame(
            ['userCurrentSeasonCapTotal' => 100, 'partnerCurrentSeasonCapTotal' => 100, 'userCapSentToPartner' => 0, 'partnerCapSentToUser' => 0],
            $calculator->calculateSalaryCapData($this->makeTradeData())
        );
    }

    /** V8: self-trade (offeringTeam === listeningTeam) zeroes sent keys despite checked contracts */
    public function testCalculateSalaryCapDataSelfTradeZeroesCapSentKeys(): void
    {
        $commonRepo = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $commonRepo->method('getTidFromTeamname')->willReturn(1);
        $cashConsiderationRepo = self::createStub(BuyoutLedgerRepositoryInterface::class);
        $cashConsiderationRepo->method('getTeamCashForSalary')->willReturn([]);
        $season = self::createStub(Season::class);
        $season->method('advancesContractYears')->willReturn(false);
        $validator = self::createStub(TradeValidator::class);
        $validator->method('getCurrentSeasonCashConsiderations')->willReturn(['cashSentToThem' => 0, 'cashSentToMe' => 0]);

        $calculator = new TradeCapCalculator($commonRepo, $cashConsiderationRepo, $season, $validator);
        $tradeData = $this->makeTradeData(1, 2);
        $tradeData['offeringTeam'] = 'Lakers';
        $tradeData['listeningTeam'] = 'Lakers';
        $tradeData['check'] = [0 => 'on', 1 => 'on'];
        $tradeData['type'] = [0 => '1', 1 => '1'];
        $tradeData['contract'] = [0 => '500', 1 => '600'];

        $this->assertSame(
            ['userCurrentSeasonCapTotal' => 500, 'partnerCurrentSeasonCapTotal' => 600, 'userCapSentToPartner' => 0, 'partnerCapSentToUser' => 0],
            $calculator->calculateSalaryCapData($tradeData)
        );
    }

    /** V9: cash record CY-offset row summed into cap total alongside contracts */
    public function testCalculateSalaryCapDataCashRecordCyOffsetIncludedInTotal(): void
    {
        // advances=true → cy 1→2 → yr2=500 per team; contracts 300 (user), 400 (partner)
        // user total = 300+500=800, partner total = 400+500=900, sent = 300, received = 400
        $commonRepo = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $commonRepo->method('getTidFromTeamname')->willReturn(1);
        $cashConsiderationRepo = self::createStub(BuyoutLedgerRepositoryInterface::class);
        $cashConsiderationRepo->method('getTeamCashForSalary')->willReturn([$this->makeDiscriminatorRow()]);
        $season = self::createStub(Season::class);
        $season->method('advancesContractYears')->willReturn(true);
        $validator = self::createStub(TradeValidator::class);
        $validator->method('getCurrentSeasonCashConsiderations')->willReturn(['cashSentToThem' => 0, 'cashSentToMe' => 0]);

        $calculator = new TradeCapCalculator($commonRepo, $cashConsiderationRepo, $season, $validator);
        $tradeData = $this->makeTradeData(1, 2);
        $tradeData['check'] = [0 => 'on', 1 => 'on'];
        $tradeData['type'] = [0 => '1', 1 => '1'];
        $tradeData['contract'] = [0 => '300', 1 => '400'];

        $this->assertSame(
            ['userCurrentSeasonCapTotal' => 800, 'partnerCurrentSeasonCapTotal' => 900, 'userCapSentToPartner' => 300, 'partnerCapSentToUser' => 400],
            $calculator->calculateSalaryCapData($tradeData)
        );
    }

    /**
     * @return array<string, array{bool, int}>
     */
    public static function advancesContractYearsProvider(): array
    {
        return [
            'offseason: advancesContractYears true' => [true, 500],
            'in-season: advancesContractYears false' => [false, 100],
        ];
    }

    #[DataProvider('advancesContractYearsProvider')]
    public function testCashConsiderationsAdjustTotalsInOppositeDirections(bool $advances, int $cashRecordBase): void
    {
        $userSendsCash = [1 => 200, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];
        $partnerSendsCash = [1 => 50, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];

        $commonRepo = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $commonRepo->method('getTidFromTeamname')->willReturn(1);
        $cashConsiderationRepo = self::createStub(BuyoutLedgerRepositoryInterface::class);
        $cashConsiderationRepo->method('getTeamCashForSalary')->willReturn([$this->makeDiscriminatorRow()]);
        $season = self::createStub(Season::class);
        $season->method('advancesContractYears')->willReturn($advances);
        $validator = self::createMock(TradeValidator::class);
        $validator->expects(self::once())
            ->method('getCurrentSeasonCashConsiderations')
            ->with($userSendsCash, $partnerSendsCash)
            ->willReturn(['cashSentToThem' => 200, 'cashSentToMe' => 50]);

        $calculator = new TradeCapCalculator($commonRepo, $cashConsiderationRepo, $season, $validator);
        $tradeData = $this->makeTradeData();
        $tradeData['userSendsCash'] = $userSendsCash;
        $tradeData['partnerSendsCash'] = $partnerSendsCash;

        $this->assertSame(
            ['userCurrentSeasonCapTotal' => $cashRecordBase + 150, 'partnerCurrentSeasonCapTotal' => $cashRecordBase - 150, 'userCapSentToPartner' => 0, 'partnerCapSentToUser' => 0],
            $calculator->calculateSalaryCapData($tradeData)
        );
    }

    public function testUncheckedRowsCountInTotalButNotInSent(): void
    {
        $commonRepo = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $commonRepo->method('getTidFromTeamname')->willReturn(1);
        $cashConsiderationRepo = self::createStub(BuyoutLedgerRepositoryInterface::class);
        $cashConsiderationRepo->method('getTeamCashForSalary')->willReturn([]);
        $season = self::createStub(Season::class);
        $season->method('advancesContractYears')->willReturn(false);
        $validator = self::createStub(TradeValidator::class);
        $validator->method('getCurrentSeasonCashConsiderations')->willReturn(['cashSentToThem' => 0, 'cashSentToMe' => 0]);

        $calculator = new TradeCapCalculator($commonRepo, $cashConsiderationRepo, $season, $validator);
        $tradeData = $this->makeTradeData(3, 6);
        $tradeData['check'] = [0 => 'on', 1 => 'off', 3 => 'on', 4 => 'off'];
        $tradeData['contract'] = [0 => '100', 1 => '200', 2 => '400', 3 => '1000', 4 => '2000', 5 => '4000', 6 => '9999'];

        $this->assertSame(
            ['userCurrentSeasonCapTotal' => 700, 'partnerCurrentSeasonCapTotal' => 7000, 'userCapSentToPartner' => 100, 'partnerCapSentToUser' => 1000],
            $calculator->calculateSalaryCapData($tradeData)
        );
    }

    public function testMissingContractIndexCountsAsZeroSalary(): void
    {
        $commonRepo = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $commonRepo->method('getTidFromTeamname')->willReturn(1);
        $cashConsiderationRepo = self::createStub(BuyoutLedgerRepositoryInterface::class);
        $cashConsiderationRepo->method('getTeamCashForSalary')->willReturn([]);
        $season = self::createStub(Season::class);
        $season->method('advancesContractYears')->willReturn(false);
        $validator = self::createStub(TradeValidator::class);
        $validator->method('getCurrentSeasonCashConsiderations')->willReturn(['cashSentToThem' => 0, 'cashSentToMe' => 0]);

        $calculator = new TradeCapCalculator($commonRepo, $cashConsiderationRepo, $season, $validator);
        $tradeData = $this->makeTradeData(1, 2);
        $tradeData['check'] = [0 => 'on', 1 => 'on'];
        $tradeData['contract'] = [];

        $this->assertSame(
            ['userCurrentSeasonCapTotal' => 0, 'partnerCurrentSeasonCapTotal' => 0, 'userCapSentToPartner' => 0, 'partnerCapSentToUser' => 0],
            $calculator->calculateSalaryCapData($tradeData)
        );
    }

    public function testNullTeamIdFallsBackToZeroForCashLookup(): void
    {
        $commonRepo = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $commonRepo->method('getTidFromTeamname')->willReturn(null);
        $cashConsiderationRepo = self::createMock(BuyoutLedgerRepositoryInterface::class);
        $cashConsiderationRepo->expects(self::exactly(2))
            ->method('getTeamCashForSalary')
            ->with(0)
            ->willReturn([]);
        $season = self::createStub(Season::class);
        $season->method('advancesContractYears')->willReturn(false);
        $validator = self::createStub(TradeValidator::class);
        $validator->method('getCurrentSeasonCashConsiderations')->willReturn(['cashSentToThem' => 0, 'cashSentToMe' => 0]);

        $calculator = new TradeCapCalculator($commonRepo, $cashConsiderationRepo, $season, $validator);

        $this->assertSame(
            ['userCurrentSeasonCapTotal' => 0, 'partnerCurrentSeasonCapTotal' => 0, 'userCapSentToPartner' => 0, 'partnerCapSentToUser' => 0],
            $calculator->calculateSalaryCapData($this->makeTradeData())
        );
    }

    public function testCashRecordsAttributedToEachSideByTeamId(): void
    {
        $commonRepo = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $commonRepo->method('getTidFromTeamname')->willReturnMap([['Lakers', 7], ['Celtics', 9]]);
        $userRow = $this->makeDiscriminatorRow();
        $partnerRow = array_replace($this->makeDiscriminatorRow(), ['salary_yr1' => 300]);
        $cashConsiderationRepo = self::createStub(BuyoutLedgerRepositoryInterface::class);
        $cashConsiderationRepo->method('getTeamCashForSalary')
            ->willReturnCallback(static fn (int $tid): array => $tid === 7 ? [$userRow] : [$partnerRow]);
        $season = self::createStub(Season::class);
        $season->method('advancesContractYears')->willReturn(false);
        $validator = self::createStub(TradeValidator::class);
        $validator->method('getCurrentSeasonCashConsiderations')->willReturn(['cashSentToThem' => 0, 'cashSentToMe' => 0]);

        $calculator = new TradeCapCalculator($commonRepo, $cashConsiderationRepo, $season, $validator);

        $this->assertSame(
            ['userCurrentSeasonCapTotal' => 100, 'partnerCurrentSeasonCapTotal' => 300, 'userCapSentToPartner' => 0, 'partnerCapSentToUser' => 0],
            $calculator->calculateSalaryCapData($this->makeTradeData())
        );
    }
}
