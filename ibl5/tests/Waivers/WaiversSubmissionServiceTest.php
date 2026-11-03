<?php

declare(strict_types=1);

namespace Tests\Waivers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Waivers\WaiversSubmissionService;
use Waivers\Contracts\WaiversProcessorInterface;
use Repositories\Contracts\SalaryCapRepositoryInterface;
use Season\Season;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Waivers\Contracts\WaiversRosterSlotCounterInterface;
use Waivers\Contracts\WaiversTeamLockInterface;

/**
 * Security deliverable for backlog 6.22 (Waivers half).
 *
 * The authz verdict that previously lived inline in WaiversController now lives in
 * WaiversSubmissionService::submit(), which returns a verdict rather than calling a
 * never-returning redirect. That makes the "non-party refused AND no mutation" property
 * unit-testable exit-free. Each refusal test asserts never() on the mutation methods
 * (processAdd/processDrop) — the load-bearing property — and on the salary read that
 * would otherwise follow (getTeamTotalSalary), so a failure localizes to the gate.
 */
class WaiversSubmissionServiceTest extends TestCase
{
    public function testSubmitRefusesNullTeamWithoutProcessingAdd(): void
    {
        $slotCounter = $this->createMock(WaiversRosterSlotCounterInterface::class);
        $slotCounter->expects($this->never())->method('countOpenSlots');
        [$service, $processor, $salaryCapRepo] = $this->buildServiceWithMocks(slotCounter: $slotCounter);
        $processor->expects($this->never())->method('processAdd');
        $processor->expects($this->never())->method('processDrop');
        $salaryCapRepo->expects($this->never())->method('getTeamTotalSalary');
        $salaryCapRepo->expects($this->never())->method('getTeamNextYearSalary');

        $result = $service->submit(['Action' => 'add', 'Player_ID' => 42, 'healthyrosterslots' => 12], null);

        $this->assertFalse($result['success']);
        $this->assertSame('Unable to determine your team.', $result['error'] ?? '');
    }

    public function testSubmitRefusesEmptyTeamWithoutProcessingDrop(): void
    {
        $slotCounter = $this->createMock(WaiversRosterSlotCounterInterface::class);
        $slotCounter->expects($this->never())->method('countOpenSlots');
        [$service, $processor, $salaryCapRepo] = $this->buildServiceWithMocks(slotCounter: $slotCounter);
        $processor->expects($this->never())->method('processAdd');
        $processor->expects($this->never())->method('processDrop');
        $salaryCapRepo->expects($this->never())->method('getTeamTotalSalary');
        $salaryCapRepo->expects($this->never())->method('getTeamNextYearSalary');

        $result = $service->submit(['Action' => 'waive', 'Player_ID' => 42, 'rosterslots' => 14], '');

        $this->assertFalse($result['success']);
        $this->assertSame('Unable to determine your team.', $result['error'] ?? '');
    }

    public function testSubmitRefusesFreeAgentsPseudoTeamCollapsedToNull(): void
    {
        $slotCounter = $this->createMock(WaiversRosterSlotCounterInterface::class);
        $slotCounter->expects($this->never())->method('countOpenSlots');
        [$service, $processor, $salaryCapRepo] = $this->buildServiceWithMocks(slotCounter: $slotCounter);
        $processor->expects($this->never())->method('processAdd');
        $processor->expects($this->never())->method('processDrop');
        $salaryCapRepo->expects($this->never())->method('getTeamTotalSalary');

        // The controller collapses \League\League::FREE_AGENTS_TEAM_NAME (the free-agent
        // pseudo-team, a non-empty string) to null at its identity-resolution block before
        // delegating, so the service only ever receives that null — asserted here.
        $result = $service->submit(['Action' => 'add', 'Player_ID' => 42, 'healthyrosterslots' => 12], null);

        $this->assertFalse($result['success']);
        $this->assertSame('Unable to determine your team.', $result['error'] ?? '');
    }

    public function testSubmitRefusesUnknownActionWithoutProcessing(): void
    {
        $slotCounter = $this->createMock(WaiversRosterSlotCounterInterface::class);
        $slotCounter->expects($this->never())->method('countOpenSlots');
        [$service, $processor, $salaryCapRepo] = $this->buildServiceWithMocks(slotCounter: $slotCounter);
        $processor->expects($this->never())->method('processAdd');
        $processor->expects($this->never())->method('processDrop');
        // The in_array($action, ['add','waive']) check sits above the salary read, so a
        // malformed action must be rejected before any DB read.
        $salaryCapRepo->expects($this->never())->method('getTeamTotalSalary');

        $result = $service->submit(['Action' => 'trade', 'Player_ID' => 42], 'Chicago Bulls');

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid submission data.', $result['error'] ?? '');
    }

    public function testSubmitIgnoresTeamNameSuppliedInPostData(): void
    {
        [$service, $processor, $salaryCapRepo] = $this->buildServiceWithMocks();
        $processor->expects($this->never())->method('processAdd');
        $processor->expects($this->never())->method('processDrop');
        $salaryCapRepo->expects($this->never())->method('getTeamTotalSalary');

        // A spoofed team in the request body must not rescue a request whose session-derived
        // acting team is absent (IDOR fix D-08).
        $result = $service->submit(
            ['Action' => 'add', 'Player_ID' => 42, 'teamName' => 'Chicago Bulls', 'Teamname' => 'Chicago Bulls'],
            null
        );

        $this->assertFalse($result['success']);
        $this->assertSame('Unable to determine your team.', $result['error'] ?? '');
    }

    public function testSubmitForwardsAuthorizedAddToProcessor(): void
    {
        [$service, $processor, $salaryCapRepo] = $this->buildServiceWithMocks(
            slotCounter: $this->counterReporting(0, 12)
        );
        $salaryCapRepo->expects($this->once())->method('getTeamTotalSalary')
            ->with('Chicago Bulls')->willReturn(75000000);
        $processor->expects($this->never())->method('processDrop');
        $processor->expects($this->once())->method('processAdd')
            ->with(42, 'Chicago Bulls', 0, 12, 75000000)
            ->willReturn(['success' => true, 'result' => 'Player added']);

        $result = $service->submit(
            ['Action' => 'add', 'Player_ID' => 42, 'healthyrosterslots' => 12],
            'Chicago Bulls'
        );

        $this->assertTrue($result['success']);
        $this->assertSame('Player added', $result['result'] ?? '');
    }

    /**
     * A team at 15 with two injured players: the counter reports open 0, healthy 2.
     * The add path forwards both counts, open first.
     */
    public function testSubmitForwardsHealthyRosterSlotsToProcessAdd(): void
    {
        [$service, $processor, $salaryCapRepo] = $this->buildServiceWithMocks(
            slotCounter: $this->counterReporting(0, 2)
        );
        $salaryCapRepo->expects($this->once())->method('getTeamTotalSalary')
            ->with('Chicago Bulls')->willReturn(75000000);
        $processor->expects($this->once())->method('processAdd')
            ->with(42, 'Chicago Bulls', 0, 2, 75000000)
            ->willReturn(['success' => true, 'result' => 'Player added']);

        $result = $service->submit(
            ['Action' => 'add', 'Player_ID' => 42, 'rosterslots' => 0, 'healthyrosterslots' => 2],
            'Chicago Bulls'
        );

        $this->assertTrue($result['success']);
    }

    public function testSubmitForwardsAuthorizedWaiveToProcessor(): void
    {
        [$service, $processor, $salaryCapRepo] = $this->buildServiceWithMocks(
            slotCounter: $this->counterReporting(14, 14)
        );
        $salaryCapRepo->expects($this->once())->method('getTeamTotalSalary')
            ->with('Chicago Bulls')->willReturn(75000000);
        $processor->expects($this->never())->method('processAdd');
        $processor->expects($this->once())->method('processDrop')
            ->with(42, 'Chicago Bulls', 14, 75000000)
            ->willReturn(['success' => true, 'result' => 'Player waived']);

        $result = $service->submit(
            ['Action' => 'waive', 'Player_ID' => 42, 'rosterslots' => 14],
            'Chicago Bulls'
        );

        $this->assertTrue($result['success']);
        $this->assertSame('Player waived', $result['result'] ?? '');
    }

    /**
     * Backlog 13.14 — the cap basis must follow the contract-year phase.
     */
    #[DataProvider('salaryBasisProvider')]
    public function testSubmitSelectsSalaryBasisByContractYearPhase(
        string $action,
        bool $advancesContractYears,
        string $expectedMethod,
        string $unusedMethod,
        int $expectedSalary
    ): void {
        [$service, $processor, $salaryCapRepo] = $this->buildServiceWithMocks(
            $advancesContractYears,
            $this->counterReporting(12, 12)
        );

        $salaryCapRepo->expects($this->once())
            ->method($expectedMethod)
            ->with('Miami Heat')
            ->willReturn($expectedSalary);
        $salaryCapRepo->expects($this->never())->method($unusedMethod);

        $processorMethod = $action === 'waive' ? 'processDrop' : 'processAdd';
        $expectedArguments = $action === 'waive'
            ? [42, 'Miami Heat', 12, $expectedSalary]
            : [42, 'Miami Heat', 12, 12, $expectedSalary];
        $processor->expects($this->once())
            ->method($processorMethod)
            ->with(...$expectedArguments)
            ->willReturn(['success' => true]);

        $service->submit(
            ['Action' => $action, 'Player_ID' => 42, 'rosterslots' => 12, 'healthyrosterslots' => 12],
            'Miami Heat'
        );
    }

    /**
     * @return array<string, array{0: string, 1: bool, 2: string, 3: string, 4: int}>
     */
    public static function salaryBasisProvider(): array
    {
        return [
            // name => [action, advancesContractYears, expected, unused, salary]
            'add in-season uses current-year basis'   => ['add',   false, 'getTeamTotalSalary',    'getTeamNextYearSalary', 6861],
            'add offseason uses next-year basis'      => ['add',   true,  'getTeamNextYearSalary', 'getTeamTotalSalary',    5159],
            'waive in-season uses current-year basis' => ['waive', false, 'getTeamTotalSalary',    'getTeamNextYearSalary', 6861],
            'waive offseason uses next-year basis'    => ['waive', true,  'getTeamNextYearSalary', 'getTeamTotalSalary',    5159],
        ];
    }

    /**
     * backlog#1538 — the posted rosterslots / healthyrosterslots are tamperable; an add must
     * forward the counter's numbers, not the posted ones.
     */
    public function testSubmitAddUsesServerCountsNotPostedRosterSlots(): void
    {
        [$service, $processor, $salaryCapRepo] = $this->buildServiceWithMocks(
            slotCounter: $this->counterReporting(0, 0)
        );
        $salaryCapRepo->expects($this->once())->method('getTeamTotalSalary')->willReturn(75000000);
        $processor->expects($this->once())->method('processAdd')
            ->with(42, 'Chicago Bulls', 0, 0, self::anything())
            ->willReturn(['success' => false, 'error' => 'No roster spot']);

        $service->submit(
            ['Action' => 'add', 'Player_ID' => 42, 'rosterslots' => 5, 'healthyrosterslots' => 5],
            'Chicago Bulls'
        );
    }

    public function testSubmitDropUsesServerCountNotPostedRosterSlots(): void
    {
        [$service, $processor, $salaryCapRepo] = $this->buildServiceWithMocks(
            slotCounter: $this->counterReporting(5, 5)
        );
        $salaryCapRepo->expects($this->once())->method('getTeamTotalSalary')->willReturn(75000000);
        $processor->expects($this->once())->method('processDrop')
            ->with(42, 'Chicago Bulls', 5, self::anything())
            ->willReturn(['success' => true]);

        $service->submit(
            ['Action' => 'waive', 'Player_ID' => 42, 'rosterslots' => 0],
            'Chicago Bulls'
        );
    }

    public function testSubmitAddWithoutPostedSlotFieldsStillPassesServerCounts(): void
    {
        [$service, $processor, $salaryCapRepo] = $this->buildServiceWithMocks(
            slotCounter: $this->counterReporting(3, 2)
        );
        $salaryCapRepo->expects($this->once())->method('getTeamTotalSalary')->willReturn(75000000);
        $processor->expects($this->once())->method('processAdd')
            ->with(42, 'Chicago Bulls', 3, 2, self::anything())
            ->willReturn(['success' => true]);

        $service->submit(['Action' => 'add', 'Player_ID' => 42], 'Chicago Bulls');
    }

    public function testSubmitRefusesUnknownTeamIdWithoutCountingOrProcessing(): void
    {
        $teamIdentityRepo = $this->createMock(TeamIdentityRepositoryInterface::class);
        $teamIdentityRepo->expects($this->once())->method('getTidFromTeamname')
            ->with('Chicago Bulls')->willReturn(null);
        $slotCounter = $this->createMock(WaiversRosterSlotCounterInterface::class);
        $slotCounter->expects($this->never())->method('countOpenSlots');

        [$service, $processor, $salaryCapRepo] = $this->buildServiceWithMocks(
            slotCounter: $slotCounter,
            teamIdentityRepo: $teamIdentityRepo
        );
        $processor->expects($this->never())->method('processAdd');
        $processor->expects($this->never())->method('processDrop');
        $salaryCapRepo->expects($this->never())->method('getTeamTotalSalary');
        $salaryCapRepo->expects($this->never())->method('getTeamNextYearSalary');

        $result = $service->submit(['Action' => 'add', 'Player_ID' => 42], 'Chicago Bulls');

        $this->assertSame(['success' => false, 'error' => 'Unable to determine your team.'], $result);
    }

    public function testSubmitForwardsVerifiedTeamIdAndInjectedSeasonToCounter(): void
    {
        $teamIdentityRepo = self::createStub(TeamIdentityRepositoryInterface::class);
        $teamIdentityRepo->method('getTidFromTeamname')->willReturn(7);
        $slotCounter = $this->createMock(WaiversRosterSlotCounterInterface::class);

        [$service, $processor, $salaryCapRepo, $season] = $this->buildServiceWithMocks(
            slotCounter: $slotCounter,
            teamIdentityRepo: $teamIdentityRepo
        );
        $salaryCapRepo->expects($this->once())->method('getTeamTotalSalary')->willReturn(75000000);
        $slotCounter->expects($this->once())->method('countOpenSlots')
            ->with(7, self::identicalTo($season))
            ->willReturn(['open' => 5, 'healthy' => 5]);
        $processor->expects($this->once())->method('processAdd')->willReturn(['success' => true]);

        $service->submit(
            ['Action' => 'add', 'Player_ID' => 42, 'Team_Name' => 'Some Other Team'],
            'Chicago Bulls'
        );
    }

    /**
     * backlog#1570 — count, salary read, and the processor write all run strictly inside the
     * team lock, so a same-team submission that waits on the lock counts a roster that
     * already holds the first signing.
     */
    public function testSubmitAddRunsCountSalaryAndProcessInsideTeamLock(): void
    {
        $events = [];
        $slotCounter = self::createStub(WaiversRosterSlotCounterInterface::class);
        $slotCounter->method('countOpenSlots')->willReturnCallback(
            static function () use (&$events): array {
                $events[] = 'count';

                return ['open' => 1, 'healthy' => 11];
            }
        );

        [$service, $processor, $salaryCapRepo] = $this->buildServiceWithMocks(
            slotCounter: $slotCounter,
            teamLock: $this->eventRecordingLock($events)
        );
        $salaryCapRepo->expects($this->once())->method('getTeamTotalSalary')->willReturnCallback(
            static function () use (&$events): int {
                $events[] = 'salary';

                return 5000;
            }
        );
        $salaryCapRepo->expects($this->never())->method('getTeamNextYearSalary');
        $processor->expects($this->never())->method('processDrop');
        $processor->expects($this->once())->method('processAdd')->willReturnCallback(
            static function () use (&$events): array {
                $events[] = 'process';

                return ['success' => true, 'result' => 'player_added'];
            }
        );

        $result = $service->submit(['Action' => 'add', 'Player_ID' => '42'], 'Chicago Bulls');

        $this->assertSame(['lock:start', 'count', 'salary', 'process', 'lock:end'], $events);
        $this->assertSame(['success' => true, 'result' => 'player_added'], $result);
    }

    public function testSubmitWaiveRunsCountSalaryAndProcessInsideTeamLock(): void
    {
        $events = [];
        $slotCounter = self::createStub(WaiversRosterSlotCounterInterface::class);
        $slotCounter->method('countOpenSlots')->willReturnCallback(
            static function () use (&$events): array {
                $events[] = 'count';

                return ['open' => 1, 'healthy' => 11];
            }
        );

        [$service, $processor, $salaryCapRepo] = $this->buildServiceWithMocks(
            advancesContractYears: true,
            slotCounter: $slotCounter,
            teamLock: $this->eventRecordingLock($events)
        );
        $salaryCapRepo->expects($this->once())->method('getTeamNextYearSalary')->willReturnCallback(
            static function () use (&$events): int {
                $events[] = 'salary';

                return 5000;
            }
        );
        $salaryCapRepo->expects($this->never())->method('getTeamTotalSalary');
        $processor->expects($this->never())->method('processAdd');
        $processor->expects($this->once())->method('processDrop')->willReturnCallback(
            static function () use (&$events): array {
                $events[] = 'process';

                return ['success' => true, 'result' => 'player_dropped'];
            }
        );

        $result = $service->submit(['Action' => 'waive', 'Player_ID' => '42'], 'Chicago Bulls');

        $this->assertSame(['lock:start', 'count', 'salary', 'process', 'lock:end'], $events);
        $this->assertSame(['success' => true, 'result' => 'player_dropped'], $result);
    }

    public function testSubmitLocksTheVerifiedTeamId(): void
    {
        $teamIdentityRepo = self::createStub(TeamIdentityRepositoryInterface::class);
        $teamIdentityRepo->method('getTidFromTeamname')->willReturn(7);
        $teamLock = $this->createMock(WaiversTeamLockInterface::class);
        $teamLock->expects($this->once())->method('withTeamLock')
            ->with(7, self::isCallable())
            ->willReturnCallback(static fn (int $teamId, callable $fn): mixed => $fn());

        [$service, $processor, $salaryCapRepo] = $this->buildServiceWithMocks(
            teamIdentityRepo: $teamIdentityRepo,
            teamLock: $teamLock
        );
        $salaryCapRepo->expects($this->once())->method('getTeamTotalSalary')->willReturn(5000);
        $processor->expects($this->once())->method('processAdd')->willReturn(['success' => true]);

        $service->submit(['Action' => 'add', 'Player_ID' => '42'], 'Chicago Bulls');
    }

    public function testSubmitReturnsTeamErrorWhenLockFindsNoTeamRow(): void
    {
        $teamLock = self::createStub(WaiversTeamLockInterface::class);
        $teamLock->method('withTeamLock')->willReturn(null);
        $slotCounter = $this->createMock(WaiversRosterSlotCounterInterface::class);
        $slotCounter->expects($this->never())->method('countOpenSlots');

        [$service, $processor, $salaryCapRepo] = $this->buildServiceWithMocks(
            slotCounter: $slotCounter,
            teamLock: $teamLock
        );
        $salaryCapRepo->expects($this->never())->method('getTeamTotalSalary');
        $salaryCapRepo->expects($this->never())->method('getTeamNextYearSalary');
        $processor->expects($this->never())->method('processAdd');
        $processor->expects($this->never())->method('processDrop');

        $result = $service->submit(['Action' => 'add', 'Player_ID' => '42'], 'Chicago Bulls');

        $this->assertSame(['success' => false, 'error' => 'Unable to determine your team.'], $result);
    }

    public function testSubmitTakesNoLockWhenTeamIdIsUnresolved(): void
    {
        $teamIdentityRepo = self::createStub(TeamIdentityRepositoryInterface::class);
        $teamIdentityRepo->method('getTidFromTeamname')->willReturn(null);
        $teamLock = $this->createMock(WaiversTeamLockInterface::class);
        $teamLock->expects($this->never())->method('withTeamLock');

        [$service, $processor, $salaryCapRepo] = $this->buildServiceWithMocks(
            teamIdentityRepo: $teamIdentityRepo,
            teamLock: $teamLock
        );
        $salaryCapRepo->expects($this->never())->method('getTeamTotalSalary');
        $processor->expects($this->never())->method('processAdd');
        $processor->expects($this->never())->method('processDrop');

        $result = $service->submit(['Action' => 'add', 'Player_ID' => '42'], 'Chicago Bulls');

        $this->assertSame(['success' => false, 'error' => 'Unable to determine your team.'], $result);
    }

    public function testSubmitPropagatesLockFailureWithoutProcessing(): void
    {
        $teamLock = self::createStub(WaiversTeamLockInterface::class);
        $teamLock->method('withTeamLock')->willThrowException(
            new \mysqli_sql_exception('Lock wait timeout exceeded', 1205)
        );

        [$service, $processor, $salaryCapRepo] = $this->buildServiceWithMocks(teamLock: $teamLock);
        $salaryCapRepo->expects($this->never())->method('getTeamTotalSalary');
        $processor->expects($this->never())->method('processAdd');
        $processor->expects($this->never())->method('processDrop');

        $this->expectException(\mysqli_sql_exception::class);
        $this->expectExceptionCode(1205);

        $service->submit(['Action' => 'add', 'Player_ID' => '42'], 'Chicago Bulls');
    }

    /**
     * A pass-through lock that records when the locked region starts and ends.
     *
     * @param list<string> $events
     */
    private function eventRecordingLock(array &$events): WaiversTeamLockInterface
    {
        $teamLock = self::createStub(WaiversTeamLockInterface::class);
        $teamLock->method('withTeamLock')->willReturnCallback(
            static function (int $teamId, callable $fn) use (&$events): mixed {
                $events[] = 'lock:start';
                $result = $fn();
                $events[] = 'lock:end';

                return $result;
            }
        );

        return $teamLock;
    }

    private function counterReporting(int $open, int $healthy): WaiversRosterSlotCounterInterface
    {
        $counter = self::createStub(WaiversRosterSlotCounterInterface::class);
        $counter->method('countOpenSlots')->willReturn(['open' => $open, 'healthy' => $healthy]);

        return $counter;
    }

    /**
     * @return array{0: WaiversSubmissionService, 1: WaiversProcessorInterface&MockObject, 2: SalaryCapRepositoryInterface&MockObject, 3: Season&Stub, 4: WaiversRosterSlotCounterInterface, 5: TeamIdentityRepositoryInterface, 6: WaiversTeamLockInterface}
     */
    private function buildServiceWithMocks(
        bool $advancesContractYears = false,
        ?WaiversRosterSlotCounterInterface $slotCounter = null,
        ?TeamIdentityRepositoryInterface $teamIdentityRepo = null,
        ?WaiversTeamLockInterface $teamLock = null
    ): array {
        $processor = $this->createMock(WaiversProcessorInterface::class);
        $salaryCapRepo = $this->createMock(SalaryCapRepositoryInterface::class);
        $season = self::createStub(Season::class);
        $season->method('advancesContractYears')->willReturn($advancesContractYears);

        if ($slotCounter === null) {
            $slotCounter = $this->counterReporting(5, 5);
        }
        if ($teamIdentityRepo === null) {
            $teamIdentityRepo = self::createStub(TeamIdentityRepositoryInterface::class);
            $teamIdentityRepo->method('getTidFromTeamname')->willReturn(7);
        }

        if ($teamLock === null) {
            $teamLock = self::createStub(WaiversTeamLockInterface::class);
            $teamLock->method('withTeamLock')->willReturnCallback(
                static fn (int $teamId, callable $fn): mixed => $fn()
            );
        }

        $service = new WaiversSubmissionService($processor, $salaryCapRepo, $season, $slotCounter, $teamIdentityRepo, $teamLock);

        return [$service, $processor, $salaryCapRepo, $season, $slotCounter, $teamIdentityRepo, $teamLock];
    }
}
