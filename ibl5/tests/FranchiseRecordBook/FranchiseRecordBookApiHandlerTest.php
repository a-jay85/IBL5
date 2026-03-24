<?php

declare(strict_types=1);

namespace Tests\FranchiseRecordBook;

use FranchiseRecordBook\FranchiseRecordBookApiHandler;
use Tests\Integration\IntegrationTestCase;

/**
 * @covers \FranchiseRecordBook\FranchiseRecordBookApiHandler
 */
class FranchiseRecordBookApiHandlerTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // All record book queries return empty arrays (no records)
        $this->mockDb->setMockData([]);
    }

    protected function tearDown(): void
    {
        $_GET = [];
        parent::tearDown();
    }

    public function testCanBeInstantiated(): void
    {
        $handler = new FranchiseRecordBookApiHandler($GLOBALS['mysqli_db']);

        $this->assertInstanceOf(FranchiseRecordBookApiHandler::class, $handler);
    }

    public function testHandleWithNoTeamIdRendersLeagueWideView(): void
    {
        $_GET = [];
        $handler = new FranchiseRecordBookApiHandler($GLOBALS['mysqli_db']);

        $output = $this->captureOutput(static fn () => $handler->handle());

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('League-Wide Record Book', $output);
    }

    public function testHandleWithInvalidTeamIdFallsBackToLeagueWide(): void
    {
        $_GET = ['teamid' => '9999'];
        $handler = new FranchiseRecordBookApiHandler($GLOBALS['mysqli_db']);

        $output = $this->captureOutput(static fn () => $handler->handle());

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('League-Wide Record Book', $output);
    }

    public function testHandleWithValidTeamIdRendersTeamView(): void
    {
        // teamid=1 passes League::isRealFranchise() check
        // getTeamInfo() queries "ibl_team_info ... WHERE teamid = ?" — use \s to match newlines
        $this->mockDb->onQuery('ibl_team_info[\s\S]*WHERE teamid =', [['teamid' => 1, 'team_name' => 'Test Team', 'color1' => '000000', 'color2' => 'FFFFFF']]);

        $_GET = ['teamid' => '1'];
        $handler = new FranchiseRecordBookApiHandler($GLOBALS['mysqli_db']);

        $output = $this->captureOutput(static fn () => $handler->handle());

        $this->assertNotEmpty($output);
        // Team view should not contain "League-Wide"
        $this->assertStringNotContainsString('League-Wide', $output);
        $this->assertStringContainsString('ibl-title', $output);
    }
}
