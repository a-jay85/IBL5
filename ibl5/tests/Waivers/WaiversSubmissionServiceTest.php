<?php

declare(strict_types=1);

namespace Tests\Waivers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Log\LoggerInterface;
use Mail\Contracts\MailServiceInterface;
use Repositories\Contracts\PlayerLookupRepositoryInterface;
use Topics\News\NewsRepository;
use Validation\ValidationResult;
use Waivers\WaiversProcessor;
use Waivers\Contracts\WaiversRepositoryInterface;
use Waivers\Contracts\WaiversValidatorInterface;
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
        $processor->expects($this->never())->method('finishCommittedMove');

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
     * Owner decision on PR #3700: mail and Discord run after the lock is released. The real
     * processor runs here, so a notification sent from inside the callback lands before lock:end.
     */
    public function testSubmitAddSendsMailThenDiscordOnlyAfterLockReturns(): void
    {
        $events = [];
        $mailArgs = [];
        $discordArgs = [];
        $warnings = [];
        $audits = [];
        $service = $this->buildServiceWithRealProcessor(
            $events,
            mailerFactory: $this->recordingMailerFactory($events, $mailArgs),
            discordPoster: $this->recordingDiscordPoster($events, $discordArgs),
            warnings: $warnings,
            audits: $audits
        );

        $result = $service->submit(['Action' => 'add', 'Player_ID' => '42'], 'Chicago <Bulls>');

        $this->assertSame(['lock:start', 'write', 'lock:end', 'audit', 'mail', 'discord'], $events);
        $this->assertSame(['success' => true, 'result' => 'player_added'], $result);
        $this->assertSame([], $warnings);

        $salary = \League\ContractRules::getVeteranMinimumSalary(5);
        $this->assertSame(
            [['player_signed_from_waivers', [
                'action' => 'player_signed_from_waivers',
                'player_id' => 42,
                'player_name' => 'Test Player',
                'team_name' => 'Chicago <Bulls>',
                'salary' => $salary,
            ]]],
            $audits
        );
        $hometext = "The Chicago &lt;Bulls&gt; sign Test Player from waivers for {$salary}.";
        $this->assertSame(
            [['ibldepthcharts@gmail.com', 'Chicago &lt;Bulls&gt; make waiver additions', $hometext, 'waivers@iblhoops.net']],
            $mailArgs
        );
        $this->assertSame([['#waiver-wire', $hometext]], $discordArgs);
    }

    public function testSubmitWaiveSendsDiscordOnlyAfterLockReturnsAndNoMail(): void
    {
        $events = [];
        $mailArgs = [];
        $discordArgs = [];
        $audits = [];
        $service = $this->buildServiceWithRealProcessor(
            $events,
            mailerFactory: $this->recordingMailerFactory($events, $mailArgs),
            discordPoster: $this->recordingDiscordPoster($events, $discordArgs),
            audits: $audits
        );

        $result = $service->submit(['Action' => 'waive', 'Player_ID' => '42'], 'Chicago <Bulls>');

        $this->assertSame(['lock:start', 'write', 'lock:end', 'audit', 'discord'], $events);
        $this->assertSame(['success' => true, 'result' => 'player_dropped'], $result);
        $this->assertSame(
            [['player_waived', [
                'action' => 'player_waived',
                'player_id' => 42,
                'player_name' => 'Test Player',
                'team_name' => 'Chicago <Bulls>',
            ]]],
            $audits
        );
        $this->assertSame([], $mailArgs);
        $this->assertSame([['#waiver-wire', 'The Chicago &lt;Bulls&gt; cut Test Player to waivers.']], $discordArgs);
    }

    #[DataProvider('rejectedMoveProvider')]
    public function testSubmitSendsNothingWhenTheMoveFails(string $action, bool $valid, bool $writes): void
    {
        $events = [];
        $mailArgs = [];
        $discordArgs = [];
        $service = $this->buildServiceWithRealProcessor(
            $events,
            mailerFactory: $this->recordingMailerFactory($events, $mailArgs),
            discordPoster: $this->recordingDiscordPoster($events, $discordArgs),
            valid: $valid,
            writes: $writes
        );

        $result = $service->submit(['Action' => $action, 'Player_ID' => '42'], 'Chicago Bulls');

        $this->assertFalse($result['success']);
        $this->assertArrayNotHasKey('notification', $result);
        $this->assertSame([], $mailArgs);
        $this->assertSame([], $discordArgs);
        $this->assertSame('lock:end', $events[array_key_last($events)]);
    }

    /**
     * @return array<string, array{0: string, 1: bool, 2: bool}>
     */
    public static function rejectedMoveProvider(): array
    {
        return [
            'add rejected by validation' => ['add', false, true],
            'add write fails' => ['add', true, false],
            'waive rejected by validation' => ['waive', false, true],
            'waive write fails' => ['waive', true, false],
        ];
    }

    public function testSubmitSendsNothingWhenTheLockedWriteThrows(): void
    {
        $events = [];
        $mailArgs = [];
        $discordArgs = [];
        $service = $this->buildServiceWithRealProcessor(
            $events,
            mailerFactory: $this->recordingMailerFactory($events, $mailArgs),
            discordPoster: $this->recordingDiscordPoster($events, $discordArgs),
            writeError: new \RuntimeException('write failed')
        );

        try {
            $service->submit(['Action' => 'add', 'Player_ID' => '42'], 'Chicago Bulls');
            self::fail('The write error must reach the caller.');
        } catch (\RuntimeException $e) {
            $this->assertSame('write failed', $e->getMessage());
        }

        $this->assertNotContains('audit', $events);
        $this->assertSame([], $mailArgs);
        $this->assertSame([], $discordArgs);
    }

    public function testSubmitSendsNothingWhenCommitFailsAfterTheCallback(): void
    {
        $events = [];
        $mailArgs = [];
        $discordArgs = [];
        $teamLock = self::createStub(WaiversTeamLockInterface::class);
        $teamLock->method('withTeamLock')->willReturnCallback(
            static function (int $teamId, callable $fn): mixed {
                $fn();

                throw new \mysqli_sql_exception('commit failed');
            }
        );
        $service = $this->buildServiceWithRealProcessor(
            $events,
            mailerFactory: $this->recordingMailerFactory($events, $mailArgs),
            discordPoster: $this->recordingDiscordPoster($events, $discordArgs),
            teamLock: $teamLock
        );

        try {
            $service->submit(['Action' => 'add', 'Player_ID' => '42'], 'Chicago Bulls');
            self::fail('The commit error must reach the caller.');
        } catch (\mysqli_sql_exception $e) {
            $this->assertSame('commit failed', $e->getMessage());
        }

        $this->assertSame(['write'], $events, 'A rolled-back move must leave no audit entry.');
        $this->assertSame([], $mailArgs);
        $this->assertSame([], $discordArgs);
    }

    public function testSubmitIgnoresNotificationOnAFailedResult(): void
    {
        [$service, $processor, $salaryCapRepo] = $this->buildServiceWithMocks();
        $salaryCapRepo->expects($this->once())->method('getTeamTotalSalary')->willReturn(5000);
        $processor->expects($this->once())->method('processAdd')->willReturn([
            'success' => false,
            'error' => 'rejected',
            'notification' => ['mailSubject' => 'subject', 'message' => 'message'],
        ]);
        $processor->expects($this->never())->method('finishCommittedMove');

        $result = $service->submit(['Action' => 'add', 'Player_ID' => '42'], 'Chicago Bulls');

        $this->assertSame(['success' => false, 'error' => 'rejected'], $result);
    }

    /**
     * A mail failure comes after commit: the signing stands, the GM sees success, Discord still posts.
     *
     * @param \Closure(): MailServiceInterface $failingFactory
     */
    #[DataProvider('failingMailerProvider')]
    public function testMailFailureKeepsTheSigningAndStillPostsDiscord(\Closure $failingFactory): void
    {
        $events = [];
        $discordArgs = [];
        $warnings = [];
        $service = $this->buildServiceWithRealProcessor(
            $events,
            mailerFactory: $failingFactory,
            discordPoster: $this->recordingDiscordPoster($events, $discordArgs),
            warnings: $warnings
        );

        $result = $service->submit(['Action' => 'add', 'Player_ID' => '42'], 'Chicago Bulls');

        $this->assertSame(['success' => true, 'result' => 'player_added'], $result);
        $this->assertSame(['lock:start', 'write', 'lock:end', 'audit', 'discord'], $events);
        $this->assertSame([['waiver_mail_notification_failed', 'mail down']], $warnings);
    }

    /**
     * MailService::send() catches SMTP errors and returns false, so false must log like a throw does.
     */
    public function testMailSendReturningFalseLogsAWarningAndStillPostsDiscord(): void
    {
        $events = [];
        $discordArgs = [];
        $warnings = [];
        $mailer = self::createStub(MailServiceInterface::class);
        $mailer->method('send')->willReturn(false);
        $service = $this->buildServiceWithRealProcessor(
            $events,
            mailerFactory: static fn (): MailServiceInterface => $mailer,
            discordPoster: $this->recordingDiscordPoster($events, $discordArgs),
            warnings: $warnings
        );

        $result = $service->submit(['Action' => 'add', 'Player_ID' => '42'], 'Chicago Bulls');

        $this->assertSame(['success' => true, 'result' => 'player_added'], $result);
        $this->assertSame(['lock:start', 'write', 'lock:end', 'audit', 'discord'], $events);
        $this->assertSame([['waiver_mail_notification_failed', 'The mail service reported a failed send.']], $warnings);
    }

    public function testAuditWriteFailureWarnsAndStillSends(): void
    {
        $events = [];
        $mailArgs = [];
        $discordArgs = [];
        $warnings = [];
        $service = $this->buildServiceWithRealProcessor(
            $events,
            mailerFactory: $this->recordingMailerFactory($events, $mailArgs),
            discordPoster: $this->recordingDiscordPoster($events, $discordArgs),
            warnings: $warnings,
            auditFails: true
        );

        $result = $service->submit(['Action' => 'add', 'Player_ID' => '42'], 'Chicago Bulls');

        $this->assertSame(['success' => true, 'result' => 'player_added'], $result);
        $this->assertSame(['lock:start', 'write', 'lock:end', 'audit', 'mail', 'discord'], $events);
        $this->assertSame([['waiver_audit_log_failed', 'log down']], $warnings);
    }

    /**
     * The GM must never see an error for a saved move, and a dead log must not stop the Discord post.
     *
     * @param \Closure(): MailServiceInterface $failingFactory
     */
    #[DataProvider('failingMailerProvider')]
    public function testLogFailuresAfterCommitNeverEscapeWhenMailFails(\Closure $failingFactory): void
    {
        $events = [];
        $discordArgs = [];
        $service = $this->buildServiceWithRealProcessor(
            $events,
            mailerFactory: $failingFactory,
            discordPoster: $this->recordingDiscordPoster($events, $discordArgs),
            auditFails: true,
            warningFails: true
        );

        $result = $service->submit(['Action' => 'add', 'Player_ID' => '42'], 'Chicago Bulls');

        $this->assertSame(['success' => true, 'result' => 'player_added'], $result);
        $this->assertSame(['lock:start', 'write', 'lock:end', 'audit', 'discord'], $events);
    }

    public function testLogFailureAfterCommitNeverEscapesWhenDiscordFails(): void
    {
        $events = [];
        $mailArgs = [];
        $service = $this->buildServiceWithRealProcessor(
            $events,
            mailerFactory: $this->recordingMailerFactory($events, $mailArgs),
            discordPoster: static function (string $channel, string $message): void {
                throw new \RuntimeException('discord down');
            },
            warningFails: true
        );

        $result = $service->submit(['Action' => 'add', 'Player_ID' => '42'], 'Chicago Bulls');

        $this->assertSame(['success' => true, 'result' => 'player_added'], $result);
        $this->assertCount(1, $mailArgs);
    }

    public function testMailSendReturningFalseWithALogFailureNeverEscapes(): void
    {
        $events = [];
        $discordArgs = [];
        $mailer = self::createStub(MailServiceInterface::class);
        $mailer->method('send')->willReturn(false);
        $service = $this->buildServiceWithRealProcessor(
            $events,
            mailerFactory: static fn (): MailServiceInterface => $mailer,
            discordPoster: $this->recordingDiscordPoster($events, $discordArgs),
            warningFails: true
        );

        $result = $service->submit(['Action' => 'add', 'Player_ID' => '42'], 'Chicago Bulls');

        $this->assertSame(['success' => true, 'result' => 'player_added'], $result);
        $this->assertCount(1, $discordArgs);
    }

    /**
     * @return array<string, array{0: \Closure}>
     */
    public static function failingMailerProvider(): array
    {
        return [
            'send throws' => [
                static fn (): MailServiceInterface => new class implements MailServiceInterface {
                    public function send(string $to, string $subject, string $body, string $fromEmail, string $fromName = ''): bool
                    {
                        throw new \RuntimeException('mail down');
                    }

                    public function isDeliveryEnabled(): bool
                    {
                        return true;
                    }
                },
            ],
            'mail config error' => [
                static function (): MailServiceInterface {
                    throw new \RuntimeException('mail down');
                },
            ],
        ];
    }

    public function testDiscordFailureIsLoggedAndTheMoveStillSucceeds(): void
    {
        $events = [];
        $mailArgs = [];
        $warnings = [];
        $service = $this->buildServiceWithRealProcessor(
            $events,
            mailerFactory: $this->recordingMailerFactory($events, $mailArgs),
            discordPoster: static function (string $channel, string $message): void {
                throw new \RuntimeException('discord down');
            },
            warnings: $warnings
        );

        $result = $service->submit(['Action' => 'add', 'Player_ID' => '42'], 'Chicago Bulls');

        $this->assertSame(['success' => true, 'result' => 'player_added'], $result);
        $this->assertCount(1, $mailArgs);
        $this->assertSame([['waiver_discord_notification_failed', 'discord down']], $warnings);
    }

    /**
     * Service wired to a real WaiversProcessor, so these tests see where it actually sends.
     *
     * @param list<string> $events
     * @param \Closure(): MailServiceInterface $mailerFactory
     * @param \Closure(string, string): void $discordPoster
     * @param list<array{0: string, 1: mixed}> $warnings
     * @param list<array{0: string, 1: array<string, mixed>}> $audits
     */
    private function buildServiceWithRealProcessor(
        array &$events,
        \Closure $mailerFactory,
        \Closure $discordPoster,
        bool $valid = true,
        bool $writes = true,
        ?\Throwable $writeError = null,
        ?WaiversTeamLockInterface $teamLock = null,
        array &$warnings = [],
        array &$audits = [],
        bool $auditFails = false,
        bool $warningFails = false
    ): WaiversSubmissionService {
        $write = static function () use (&$events, $writes, $writeError): bool {
            $events[] = 'write';
            if ($writeError !== null) {
                throw $writeError;
            }

            return $writes;
        };
        $repository = self::createStub(WaiversRepositoryInterface::class);
        $repository->method('signPlayerFromWaivers')->willReturnCallback($write);
        $repository->method('dropPlayerToWaivers')->willReturnCallback($write);

        $teamIdentityRepo = self::createStub(TeamIdentityRepositoryInterface::class);
        $teamIdentityRepo->method('getTidFromTeamname')->willReturn(7);
        $teamIdentityRepo->method('getTeamByName')->willReturn(['team_name' => 'Chicago Bulls', 'teamid' => 7]);

        $playerLookupRepo = self::createStub(PlayerLookupRepositoryInterface::class);
        $playerLookupRepo->method('getPlayerByID')->willReturn([
            'name' => 'Test Player', 'pid' => 42, 'salary_yr1' => 0, 'exp' => 5,
        ]);

        $verdict = $valid ? ValidationResult::success() : ValidationResult::failure('Not allowed');
        $validator = self::createStub(WaiversValidatorInterface::class);
        $validator->method('validateAdd')->willReturn($verdict);
        $validator->method('validateDrop')->willReturn($verdict);

        $logger = self::createStub(LoggerInterface::class);
        $logger->method('info')->willReturnCallback(
            static function (string|\Stringable $message, array $context = []) use (&$events, &$audits, $auditFails): void {
                $events[] = 'audit';
                if ($auditFails) {
                    throw new \RuntimeException('log down');
                }
                $audits[] = [(string) $message, $context];
            }
        );
        $logger->method('warning')->willReturnCallback(
            static function (string|\Stringable $message, array $context = []) use (&$warnings, $warningFails): void {
                if ($warningFails) {
                    throw new \RuntimeException('log down');
                }
                $warnings[] = [(string) $message, $context['error'] ?? null];
            }
        );

        $season = self::createStub(Season::class);
        $season->method('advancesContractYears')->willReturn(false);
        $season->method('isOffseasonPhase')->willReturn(false);

        $processor = new WaiversProcessor(
            $repository,
            $teamIdentityRepo,
            $playerLookupRepo,
            $validator,
            self::createStub(NewsRepository::class),
            self::createStub(\mysqli::class),
            $logger,
            $season,
            null,
            $mailerFactory,
            $discordPoster
        );

        return new WaiversSubmissionService(
            $processor,
            self::createStub(SalaryCapRepositoryInterface::class),
            $season,
            $this->counterReporting(5, 5),
            $teamIdentityRepo,
            $teamLock ?? $this->eventRecordingLock($events)
        );
    }

    /**
     * @param list<string> $events
     * @param list<array{0: string, 1: string, 2: string, 3: string}> $mailArgs
     * @return \Closure(): MailServiceInterface
     */
    private function recordingMailerFactory(array &$events, array &$mailArgs): \Closure
    {
        $mailer = self::createStub(MailServiceInterface::class);
        $mailer->method('send')->willReturnCallback(
            static function (string $to, string $subject, string $body, string $fromEmail) use (&$events, &$mailArgs): bool {
                $events[] = 'mail';
                $mailArgs[] = [$to, $subject, $body, $fromEmail];

                return true;
            }
        );

        return static fn (): MailServiceInterface => $mailer;
    }

    /**
     * @param list<string> $events
     * @param list<array{0: string, 1: string}> $discordArgs
     * @return \Closure(string, string): void
     */
    private function recordingDiscordPoster(array &$events, array &$discordArgs): \Closure
    {
        return static function (string $channel, string $message) use (&$events, &$discordArgs): void {
            $events[] = 'discord';
            $discordArgs[] = [$channel, $message];
        };
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
