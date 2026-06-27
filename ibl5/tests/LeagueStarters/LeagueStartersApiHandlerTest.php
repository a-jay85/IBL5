<?php

declare(strict_types=1);

namespace Tests\LeagueStarters;

use Auth\Contracts\AuthServiceInterface;
use LeagueStarters\LeagueStartersApiHandler;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Tests\WideUnit\Mocks\TestDataFactory;
use Tests\WideUnit\WideUnitTestCase;

/**
 * @covers \LeagueStarters\LeagueStartersApiHandler
 */
class LeagueStartersApiHandlerTest extends WideUnitTestCase
{

    protected function tearDown(): void
    {
        unset($_GET['display']);
        parent::tearDown();
    }

    public function testValidDisplayModesContainsAllExpectedModes(): void
    {
        $reflection = new \ReflectionClass(LeagueStartersApiHandler::class);
        $constant = $reflection->getReflectionConstant('VALID_DISPLAY_MODES');
        $this->assertNotFalse($constant);

        /** @var list<string> $modes */
        $modes = $constant->getValue();

        $expected = ['ratings', 'total_s', 'avg_s', 'per36mins'];
        sort($expected);
        sort($modes);

        $this->assertSame($expected, $modes);
    }

    public function testHandleRendersAllPositionTables(): void
    {
        $_GET['display'] = 'ratings';
        $handler = $this->buildHandler();

        $output = $this->captureOutput(fn () => $handler->handle());

        $this->assertStringContainsString('Point Guards', $output);
        $this->assertStringContainsString('Centers', $output);
    }

    public function testHandleFallsBackToRatingsForInvalidDisplay(): void
    {
        $_GET['display'] = 'not-a-mode';
        $handler = $this->buildHandler();

        $output = $this->captureOutput(fn () => $handler->handle());

        $this->assertStringContainsString('Point Guards', $output);
        $this->assertNotEmpty($output);
    }

    private function buildHandler(): LeagueStartersApiHandler
    {
        $db = $this->mockDb;
        self::assertNotNull($db);

        $auth = self::createStub(AuthServiceInterface::class);
        $auth->method('getUsername')->willReturn('testuser');

        $commonRepo = self::createStub(TeamIdentityRepositoryInterface::class);
        $commonRepo->method('getTeamnameFromUsername')->willReturn('Test Team');

        $season = self::createStub(\Season\Season::class);
        $season->lastSimEndDate = '2025-01-01';

        $db->setMockTeamData([TestDataFactory::createTeam([
            'teamid' => 1,
            'team_name' => 'Test Team',
            'league_record' => '0-0',
        ])]);
        $db->onQuery('teamid BETWEEN', []);

        return new LeagueStartersApiHandler($db, $commonRepo, $auth, $season);
    }
}
