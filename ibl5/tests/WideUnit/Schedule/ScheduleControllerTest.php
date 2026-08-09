<?php

declare(strict_types=1);

namespace Tests\WideUnit\Schedule;

use League\LeagueContext;
use LeagueSchedule\LeagueScheduleRepository;
use Repositories\TeamIdentityRepository;
use Schedule\Contracts\ScheduleControllerInterface;
use Schedule\ScheduleController;
use Standings\StandingsRepository;
use TeamSchedule\TeamScheduleRepository;
use Tests\WideUnit\Mocks\TestDataFactory;
use Tests\WideUnit\WideUnitTestCase;

/**
 * Tests Schedule\ScheduleController directly (not via the module entry point).
 *
 * @see \Tests\Module\EntryPoints\ScheduleEntryPointTest For entry-point / $_GET boundary coverage
 */
class ScheduleControllerTest extends WideUnitTestCase
{
    public function testImplementsScheduleControllerInterface(): void
    {
        $this->assertTrue(
            (new \ReflectionClass(ScheduleController::class))->implementsInterface(ScheduleControllerInterface::class)
        );
    }

    public function testRenderWithValidTeamIdShowsTeamSchedule(): void
    {
        $this->mockDb->setMockTeamData([TestDataFactory::createTeam(['teamid' => 1])]);
        $this->mockDb->setMockData([]);

        $leagueContext = new LeagueContext();
        $controller = new ScheduleController(
            $this->mockDb,
            $leagueContext,
            new TeamIdentityRepository($this->mockDb),
            new StandingsRepository($this->mockDb, $leagueContext),
            new TeamScheduleRepository($this->mockDb, $leagueContext),
            new LeagueScheduleRepository($this->mockDb, $leagueContext)
        );
        $output = $controller->render(1);

        $this->assertStringContainsString('schedule-container--team', $output);
    }

    public function testRenderWithZeroTeamIdShowsLeagueSchedule(): void
    {
        $this->mockDb->setMockData([]);

        $leagueContext = new LeagueContext();
        $controller = new ScheduleController(
            $this->mockDb,
            $leagueContext,
            new TeamIdentityRepository($this->mockDb),
            new StandingsRepository($this->mockDb, $leagueContext),
            new TeamScheduleRepository($this->mockDb, $leagueContext),
            new LeagueScheduleRepository($this->mockDb, $leagueContext)
        );
        $output = $controller->render(0);

        $this->assertStringContainsString('<h1 class="ibl-title">Schedule</h1>', $output);
    }

    public function testRenderWithUnknownTeamIdThrowsRuntimeException(): void
    {
        // Team::initialize() throws when no matching row is found — the
        // controller reproduces the pre-refactor module's behavior verbatim
        // (no try/catch), so an invalid teamid propagates the exception
        // rather than falling back to the league branch.
        $this->mockDb->setMockTeamData([]);
        $this->mockDb->setMockData([]);

        $leagueContext = new LeagueContext();
        $controller = new ScheduleController(
            $this->mockDb,
            $leagueContext,
            new TeamIdentityRepository($this->mockDb),
            new StandingsRepository($this->mockDb, $leagueContext),
            new TeamScheduleRepository($this->mockDb, $leagueContext),
            new LeagueScheduleRepository($this->mockDb, $leagueContext)
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Team not found: 99999');
        $controller->render(99999);
    }
}
