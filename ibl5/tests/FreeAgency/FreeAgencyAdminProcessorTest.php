<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use FreeAgency\Contracts\FreeAgencyAdminRepositoryInterface;
use FreeAgency\FreeAgencyAdminProcessor;
use PHPUnit\Framework\TestCase;

class FreeAgencyAdminProcessorTest extends TestCase
{
    private \mysqli $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new class extends \mysqli {
            public int $connect_errno = 0;
            public ?string $connect_error = null;

            public function __construct()
            {
            }

            public function begin_transaction(int $flags = 0, ?string $name = null): bool
            {
                return true;
            }

            public function commit(int $flags = 0, ?string $name = null): bool
            {
                return true;
            }

            public function rollback(int $flags = 0, ?string $name = null): bool
            {
                return true;
            }
        };
    }

    // ============================================
    // executeSignings() — delegates to repository
    // ============================================

    public function testExecuteSigningsAllSucceed(): void
    {
        $signings = [
            $this->makeSigning(1, 10, 'Miami', 500, 600, 0, 0, 0, 0, 2, false, false),
        ];

        $mock = $this->createMock(FreeAgencyAdminRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('executeSigningsTransactionally')
            ->with($signings, 'FA Day 1', 'Home text', 'Body text')
            ->willReturn(['successCount' => 3, 'errorCount' => 0]);

        $processor = new FreeAgencyAdminProcessor($mock, $this->mockDb);
        $result = $processor->executeSignings(1, $signings, 'FA Day 1', 'Home text', 'Body text');

        $this->assertTrue($result['success']);
        $this->assertSame(3, $result['successCount']);
        $this->assertSame(0, $result['errorCount']);
    }

    public function testExecuteSigningsPartialFailure(): void
    {
        $signings = [
            $this->makeSigning(1, 10, 'Miami', 500, 600, 0, 0, 0, 0, 2, false, false),
        ];

        $stub = $this->createStub(FreeAgencyAdminRepositoryInterface::class);
        $stub->method('executeSigningsTransactionally')
            ->willReturn(['successCount' => 1, 'errorCount' => 1]);

        $processor = new FreeAgencyAdminProcessor($stub, $this->mockDb);
        $result = $processor->executeSignings(1, $signings, 'FA Day 1', 'Home text', 'Body text');

        $this->assertFalse($result['success']);
        $this->assertSame(1, $result['errorCount']);
    }

    public function testExecuteSigningsDelegatesEmptyNewsText(): void
    {
        $signings = [
            $this->makeSigning(1, 10, 'Miami', 500, 0, 0, 0, 0, 0, 1, false, false),
        ];

        $mock = $this->createMock(FreeAgencyAdminRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('executeSigningsTransactionally')
            ->with($signings, 'FA Day 1', '', '')
            ->willReturn(['successCount' => 1, 'errorCount' => 0]);

        $processor = new FreeAgencyAdminProcessor($mock, $this->mockDb);
        $result = $processor->executeSignings(1, $signings, 'FA Day 1', '', '');

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['successCount']);
    }

    public function testExecuteSigningsNoOperations(): void
    {
        $stub = $this->createStub(FreeAgencyAdminRepositoryInterface::class);
        $processor = new FreeAgencyAdminProcessor($stub, $this->mockDb);

        $result = $processor->executeSignings(1, [], 'FA Day 1', '', '');

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['successCount']);
        $this->assertStringContainsString('No operations', $result['message']);
    }

    // ============================================
    // clearOffers()
    // ============================================

    public function testClearOffersReturnsSuccess(): void
    {
        $mock = $this->createMock(FreeAgencyAdminRepositoryInterface::class);
        $mock->expects($this->once())->method('clearAllOffers');

        $processor = new FreeAgencyAdminProcessor($mock, $this->mockDb);
        $result = $processor->clearOffers();

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('cleared', $result['message']);
    }

    // ============================================
    // processDay() — empty offers
    // ============================================

    public function testProcessDayEmptyOffersReturnsEmptyResults(): void
    {
        $stub = $this->createStub(FreeAgencyAdminRepositoryInterface::class);
        $stub->method('getAllOffersWithBirdYears')->willReturn([]);
        $stub->method('getPlayerDemandsBatch')->willReturn([]);

        $processor = new FreeAgencyAdminProcessor($stub, $this->mockDb);
        $result = $processor->processDay(1);

        $this->assertSame([], $result['signings']);
        $this->assertSame([], $result['rejections']);
        $this->assertSame([], $result['autoRejections']);
        $this->assertSame([], $result['allOffers']);
        $this->assertSame('', $result['newsHomeText']);
        $this->assertSame('', $result['newsBodyText']);
        $this->assertSame('', $result['discordText']);
    }

    // ============================================
    // processDay() — auto-rejection (perceived value <= demands/2)
    // ============================================

    public function testProcessDayAutoRejectsLowOffers(): void
    {
        $offer = $this->makeOfferRow('Player A', 100, 'Miami', 1, 200, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1.0);

        $stub = $this->createStub(FreeAgencyAdminRepositoryInterface::class);
        $stub->method('getAllOffersWithBirdYears')->willReturn([$offer]);
        // Demands: dem1=1000, rest 0 → total=1000, years=1
        // day 1: demands = (1000/1)*((11-1)/10) = 1000
        // perceived value 1.0 <= 1000/2 = 500 → auto-reject
        $stub->method('getPlayerDemandsBatch')->willReturn([
            100 => ['dem1' => 1000, 'dem2' => 0, 'dem3' => 0, 'dem4' => 0, 'dem5' => 0, 'dem6' => 0],
        ]);

        $processor = new FreeAgencyAdminProcessor($stub, $this->mockDb);
        $result = $processor->processDay(1);

        $this->assertCount(1, $result['autoRejections']);
        $this->assertSame('Player A', $result['autoRejections'][0]['playerName']);
        $this->assertCount(0, $result['signings']);
        $this->assertCount(0, $result['rejections']);
    }


    // ============================================
    // HELPERS
    // ============================================

    /**
     * @return array{playerId: int, teamId: int, teamName: string, offers: array{offer1: int, offer2: int, offer3: int, offer4: int, offer5: int, offer6: int}, offerYears: int, offerTotal: float, usedMle: bool, usedLle: bool}
     */
    private function makeSigning(
        int $playerId,
        int $teamId,
        string $teamName,
        int $offer1,
        int $offer2,
        int $offer3,
        int $offer4,
        int $offer5,
        int $offer6,
        int $offerYears,
        bool $usedMle,
        bool $usedLle
    ): array {
        return [
            'playerId' => $playerId,
            'teamId' => $teamId,
            'teamName' => $teamName,
            'offers' => [
                'offer1' => $offer1,
                'offer2' => $offer2,
                'offer3' => $offer3,
                'offer4' => $offer4,
                'offer5' => $offer5,
                'offer6' => $offer6,
            ],
            'offerYears' => $offerYears,
            'offerTotal' => ($offer1 + $offer2 + $offer3 + $offer4 + $offer5 + $offer6) / 100,
            'usedMle' => $usedMle,
            'usedLle' => $usedLle,
        ];
    }

    /**
     * @return array{name: string, pid: int, team: string, tid: int, offer1: int, offer2: int, offer3: int, offer4: int, offer5: int, offer6: int, bird: int, MLE: int, LLE: int, random: int, perceivedvalue: float}
     */
    private function makeOfferRow(
        string $name,
        int $pid,
        string $team,
        int $tid,
        int $offer1,
        int $offer2,
        int $offer3,
        int $offer4,
        int $offer5,
        int $offer6,
        int $bird,
        int $mle,
        int $lle,
        int $random,
        float $perceivedvalue
    ): array {
        return [
            'name' => $name,
            'pid' => $pid,
            'team' => $team,
            'tid' => $tid,
            'offer1' => $offer1,
            'offer2' => $offer2,
            'offer3' => $offer3,
            'offer4' => $offer4,
            'offer5' => $offer5,
            'offer6' => $offer6,
            'bird' => $bird,
            'MLE' => $mle,
            'LLE' => $lle,
            'random' => $random,
            'perceivedvalue' => $perceivedvalue,
        ];
    }
}
