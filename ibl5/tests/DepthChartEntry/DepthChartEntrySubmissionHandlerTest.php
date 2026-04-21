<?php

declare(strict_types=1);

namespace Tests\DepthChartEntry;

use PHPUnit\Framework\TestCase;
use DepthChartEntry\DepthChartEntrySubmissionHandler;

/**
 * DepthChartEntrySubmissionHandlerTest
 *
 * The handler no longer emits HTML — on failure it stashes
 * `$_SESSION['_ibl_depth_chart_flash']` for the PRG GET to consume, and
 * returns a bool indicating success. These tests exercise the failure
 * paths that don't require a real DB (success paths need a real DB due
 * to MockDatabase's `insert_id` limitation — covered by E2E instead).
 */
class DepthChartEntrySubmissionHandlerTest extends TestCase
{
    private \MockDatabase $mockDb;
    private object $mockMysqliDb;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
        $this->setupMockMysqliDb();

        // Start with a clean session for every test so flash assertions
        // aren't polluted by earlier runs.
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mysqli_db']);
        $_SESSION = [];
    }

    private function setupMockMysqliDb(): void
    {
        $mockDb = $this->mockDb;

        $this->mockMysqliDb = new class($mockDb) extends \mysqli {
            private \MockDatabase $mockDb;
            public int $connect_errno = 0;
            public ?string $connect_error = null;

            public function __construct(\MockDatabase $mockDb)
            {
                // Don't call parent::__construct() to avoid real DB connection
                $this->mockDb = $mockDb;
            }

            #[\ReturnTypeWillChange]
            public function prepare(string $query): \MockPreparedStatement|false
            {
                return new \MockPreparedStatement($this->mockDb, $query);
            }

            #[\ReturnTypeWillChange]
            public function query(string $query, int $resultMode = MYSQLI_STORE_RESULT): \mysqli_result|bool
            {
                $result = $this->mockDb->sql_query($query);
                if ($result instanceof \MockDatabaseResult) {
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
        $handler = new DepthChartEntrySubmissionHandler($this->mockMysqliDb);

        $this->assertInstanceOf(DepthChartEntrySubmissionHandler::class, $handler);
    }

    public function testHandlerImplementsCorrectInterface(): void
    {
        $handler = new DepthChartEntrySubmissionHandler($this->mockMysqliDb);

        $this->assertInstanceOf(
            \DepthChartEntry\Contracts\DepthChartEntrySubmissionHandlerInterface::class,
            $handler
        );
    }

    // ============================================
    // EMPTY TEAM NAME — stashes flash, returns false
    // ============================================

    public function testEmptyTeamNameReturnsFalseAndStashesFlash(): void
    {
        $handler = new DepthChartEntrySubmissionHandler($this->mockMysqliDb);

        $success = $handler->handleSubmission(['Team_Name' => '']);

        $this->assertFalse($success);
        $this->assertArrayHasKey('_ibl_depth_chart_flash', $_SESSION);
        $flash = $_SESSION['_ibl_depth_chart_flash'];
        $this->assertIsArray($flash);
        $this->assertArrayHasKey('errors_html', $flash);
        $this->assertIsString($flash['errors_html']);
        $this->assertStringContainsString('Missing required team information', $flash['errors_html']);
        $this->assertArrayHasKey('post_data', $flash);
        $this->assertSame(['Team_Name' => ''], $flash['post_data']);
    }

    public function testMissingTeamNameReturnsFalseAndStashesFlash(): void
    {
        $handler = new DepthChartEntrySubmissionHandler($this->mockMysqliDb);

        $postData = ['pg1' => '1', 'sg1' => '0'];
        $success = $handler->handleSubmission($postData);

        $this->assertFalse($success);
        $this->assertArrayHasKey('_ibl_depth_chart_flash', $_SESSION);
        $flash = $_SESSION['_ibl_depth_chart_flash'];
        $this->assertIsArray($flash);
        $this->assertStringContainsString('Missing required team information', $flash['errors_html']);
        // The caller's POST is echoed back into the flash so the
        // redirected GET can re-populate the form.
        $this->assertSame($postData, $flash['post_data']);
    }

    public function testHandlerEmitsNoOutputOnFailure(): void
    {
        $handler = new DepthChartEntrySubmissionHandler($this->mockMysqliDb);

        ob_start();
        $success = $handler->handleSubmission(['Team_Name' => '']);
        $output = (string) ob_get_clean();

        // The handler now communicates exclusively via return value + session
        // flash — no premature output that would break PRG headers.
        $this->assertFalse($success);
        $this->assertSame('', $output);
    }

    // ============================================
    // MULTIPLE INSTANCES
    // ============================================

    public function testMultipleHandlersCanBeInstantiated(): void
    {
        $handler1 = new DepthChartEntrySubmissionHandler($this->mockMysqliDb);
        $handler2 = new DepthChartEntrySubmissionHandler($this->mockMysqliDb);

        $this->assertInstanceOf(DepthChartEntrySubmissionHandler::class, $handler1);
        $this->assertInstanceOf(DepthChartEntrySubmissionHandler::class, $handler2);
        $this->assertNotSame($handler1, $handler2);
    }
}
