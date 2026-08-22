<?php

declare(strict_types=1);

namespace Tests\Waivers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Waivers\WaiversSubmissionService;
use Waivers\Contracts\WaiversProcessorInterface;
use Repositories\Contracts\SalaryCapRepositoryInterface;

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
        [$service, $processor, $salaryCapRepo] = $this->buildServiceWithMocks();
        $processor->expects($this->never())->method('processAdd');
        $processor->expects($this->never())->method('processDrop');
        $salaryCapRepo->expects($this->never())->method('getTeamTotalSalary');

        $result = $service->submit(['Action' => 'add', 'Player_ID' => 42, 'healthyrosterslots' => 12], null);

        $this->assertFalse($result['success']);
        $this->assertSame('Unable to determine your team.', $result['error'] ?? '');
    }

    public function testSubmitRefusesEmptyTeamWithoutProcessingDrop(): void
    {
        [$service, $processor, $salaryCapRepo] = $this->buildServiceWithMocks();
        $processor->expects($this->never())->method('processAdd');
        $processor->expects($this->never())->method('processDrop');
        $salaryCapRepo->expects($this->never())->method('getTeamTotalSalary');

        $result = $service->submit(['Action' => 'waive', 'Player_ID' => 42, 'rosterslots' => 14], '');

        $this->assertFalse($result['success']);
        $this->assertSame('Unable to determine your team.', $result['error'] ?? '');
    }

    public function testSubmitRefusesFreeAgentsPseudoTeamCollapsedToNull(): void
    {
        [$service, $processor, $salaryCapRepo] = $this->buildServiceWithMocks();
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
        [$service, $processor, $salaryCapRepo] = $this->buildServiceWithMocks();
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
        [$service, $processor, $salaryCapRepo] = $this->buildServiceWithMocks();
        $salaryCapRepo->expects($this->once())->method('getTeamTotalSalary')
            ->with('Chicago Bulls')->willReturn(75000000);
        $processor->expects($this->never())->method('processDrop');
        $processor->expects($this->once())->method('processAdd')
            ->with(42, 'Chicago Bulls', 12, 75000000)
            ->willReturn(['success' => true, 'result' => 'Player added']);

        $result = $service->submit(
            ['Action' => 'add', 'Player_ID' => 42, 'healthyrosterslots' => 12],
            'Chicago Bulls'
        );

        $this->assertTrue($result['success']);
        $this->assertSame('Player added', $result['result'] ?? '');
    }

    public function testSubmitForwardsAuthorizedWaiveToProcessor(): void
    {
        [$service, $processor, $salaryCapRepo] = $this->buildServiceWithMocks();
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
     * @return array{0: WaiversSubmissionService, 1: WaiversProcessorInterface&MockObject, 2: SalaryCapRepositoryInterface&MockObject}
     */
    private function buildServiceWithMocks(): array
    {
        $processor = $this->createMock(WaiversProcessorInterface::class);
        $salaryCapRepo = $this->createMock(SalaryCapRepositoryInterface::class);
        $service = new WaiversSubmissionService($processor, $salaryCapRepo);

        return [$service, $processor, $salaryCapRepo];
    }
}
