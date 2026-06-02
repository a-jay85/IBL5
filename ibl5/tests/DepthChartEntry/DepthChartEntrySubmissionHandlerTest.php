<?php

declare(strict_types=1);

namespace Tests\DepthChartEntry;

use PHPUnit\Framework\TestCase;
use DepthChartEntry\DepthChartEntrySubmissionHandler;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * DepthChartEntrySubmissionHandlerTest
 *
 * The handler returns a result array on all paths — success and failure.
 * It never writes to $_SESSION directly; that responsibility belongs to the
 * controller. These tests exercise the failure paths that don't require a
 * real DB (success paths need a real DB due to MockDatabase's `insert_id`
 * limitation — covered by E2E instead).
 */
class DepthChartEntrySubmissionHandlerTest extends TestCase
{
    private MockDatabase $mockDb;
    private TeamIdentityRepositoryInterface $stubCommonRepo;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $GLOBALS['mysqli_db'] = $this->mockDb;
        $this->stubCommonRepo = self::createStub(TeamIdentityRepositoryInterface::class);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mysqli_db']);
    }

    // ============================================
    // EMPTY TEAM NAME — returns failure result
    // ============================================

    public function testEmptyTeamNameReturnsFailureResult(): void
    {
        $handler = new DepthChartEntrySubmissionHandler($this->mockDb, $this->stubCommonRepo);

        $result = $handler->handleSubmission(['Team_Name' => '']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Missing required team information', $result['errorsHtml']);
        $this->assertSame(['Team_Name' => ''], $result['postData']);
        $this->assertArrayNotHasKey('_ibl_depth_chart_flash', $_SESSION ?? []);
    }

    public function testMissingTeamNameReturnsFailureResult(): void
    {
        $handler = new DepthChartEntrySubmissionHandler($this->mockDb, $this->stubCommonRepo);

        $postData = ['pg1' => '1', 'sg1' => '0'];
        $result = $handler->handleSubmission($postData);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Missing required team information', $result['errorsHtml']);
        $this->assertSame($postData, $result['postData']);
        $this->assertArrayNotHasKey('_ibl_depth_chart_flash', $_SESSION ?? []);
    }

    public function testHandlerEmitsNoOutputOnFailure(): void
    {
        $handler = new DepthChartEntrySubmissionHandler($this->mockDb, $this->stubCommonRepo);

        ob_start();
        $result = $handler->handleSubmission(['Team_Name' => '']);
        $output = (string) ob_get_clean();

        $this->assertFalse($result['success']);
        $this->assertSame('', $output);
    }

    // ============================================
    // MULTIPLE INSTANCES
    // ============================================

    public function testMultipleHandlersCanBeInstantiated(): void
    {
        $handler1 = new DepthChartEntrySubmissionHandler($this->mockDb, $this->stubCommonRepo);
        $handler2 = new DepthChartEntrySubmissionHandler($this->mockDb, $this->stubCommonRepo);

        $this->assertNotSame($handler1, $handler2);
    }
}
