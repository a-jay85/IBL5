<?php

declare(strict_types=1);

namespace Tests\DepthChartEntry;

use PHPUnit\Framework\TestCase;
use DepthChartEntry\DepthChartEntrySubmissionHandler;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Tests\WideUnit\Mocks\MockDatabase;
use Tests\WideUnit\Mocks\MockDatabaseResult;
use Tests\WideUnit\Mocks\MockPreparedStatement;

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
    private object $mockMysqliDb;
    private TeamIdentityRepositoryInterface $stubCommonRepo;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->setupMockMysqliDb();
        $this->stubCommonRepo = $this->createStub(TeamIdentityRepositoryInterface::class);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mysqli_db']);
    }

    private function setupMockMysqliDb(): void
    {
        $mockDb = $this->mockDb;

        $this->mockMysqliDb = new class($mockDb) extends \mysqli {
            private MockDatabase $mockDb;
            public int $connect_errno = 0;
            public ?string $connect_error = null;

            public function __construct(MockDatabase $mockDb)
            {
                // Don't call parent::__construct() to avoid real DB connection
                $this->mockDb = $mockDb;
            }

            #[\ReturnTypeWillChange]
            public function prepare(string $query): MockPreparedStatement|false
            {
                return new MockPreparedStatement($this->mockDb, $query);
            }

            #[\ReturnTypeWillChange]
            public function query(string $query, int $resultMode = MYSQLI_STORE_RESULT): \mysqli_result|bool
            {
                $result = $this->mockDb->sql_query($query);
                if ($result instanceof MockDatabaseResult) {
                    return false;
                }
                return (bool) $result;
            }

            public function real_escape_string(string $string): string
            {
                return addslashes($string);
            }
        };

        $GLOBALS['mysqli_db'] = $this->mockMysqliDb;
    }

    // ============================================
    // CONSTRUCTOR / INTERFACE
    // ============================================

    public function testHandlerCanBeInstantiated(): void
    {
        $handler = new DepthChartEntrySubmissionHandler($this->mockMysqliDb, $this->stubCommonRepo);

        $this->assertInstanceOf(DepthChartEntrySubmissionHandler::class, $handler);
    }

    public function testHandlerImplementsCorrectInterface(): void
    {
        $handler = new DepthChartEntrySubmissionHandler($this->mockMysqliDb, $this->stubCommonRepo);

        $this->assertInstanceOf(
            \DepthChartEntry\Contracts\DepthChartEntrySubmissionHandlerInterface::class,
            $handler
        );
    }

    // ============================================
    // EMPTY TEAM NAME — returns failure result
    // ============================================

    public function testEmptyTeamNameReturnsFailureResult(): void
    {
        $handler = new DepthChartEntrySubmissionHandler($this->mockMysqliDb, $this->stubCommonRepo);

        $result = $handler->handleSubmission(['Team_Name' => '']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Missing required team information', $result['errorsHtml']);
        $this->assertSame(['Team_Name' => ''], $result['postData']);
        $this->assertArrayNotHasKey('_ibl_depth_chart_flash', $_SESSION ?? []);
    }

    public function testMissingTeamNameReturnsFailureResult(): void
    {
        $handler = new DepthChartEntrySubmissionHandler($this->mockMysqliDb, $this->stubCommonRepo);

        $postData = ['pg1' => '1', 'sg1' => '0'];
        $result = $handler->handleSubmission($postData);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Missing required team information', $result['errorsHtml']);
        $this->assertSame($postData, $result['postData']);
        $this->assertArrayNotHasKey('_ibl_depth_chart_flash', $_SESSION ?? []);
    }

    public function testHandlerEmitsNoOutputOnFailure(): void
    {
        $handler = new DepthChartEntrySubmissionHandler($this->mockMysqliDb, $this->stubCommonRepo);

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
        $handler1 = new DepthChartEntrySubmissionHandler($this->mockMysqliDb, $this->stubCommonRepo);
        $handler2 = new DepthChartEntrySubmissionHandler($this->mockMysqliDb, $this->stubCommonRepo);

        $this->assertInstanceOf(DepthChartEntrySubmissionHandler::class, $handler1);
        $this->assertInstanceOf(DepthChartEntrySubmissionHandler::class, $handler2);
        $this->assertNotSame($handler1, $handler2);
    }
}
