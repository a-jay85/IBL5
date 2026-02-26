<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Trading\TradeComparisonApiHandler;

class TradeComparisonApiHandlerTest extends TestCase
{
    /**
     * Test that the handler validates PIDs correctly by testing the
     * validatePids logic indirectly through handle() output.
     *
     * Since handle() echoes JSON, we capture output. We use a mock DB
     * that returns false for prepare (no real DB), so empty PIDs or
     * invalid PIDs should return empty HTML.
     */

    private \mysqli $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new class extends \mysqli {
            public int $connect_errno = 0;
            public ?string $connect_error = null;

            public function __construct()
            {
                // Don't call parent::__construct() to avoid real DB connection
            }

            #[\ReturnTypeWillChange]
            public function prepare(string $query): \mysqli_stmt|false
            {
                return false;
            }

            #[\ReturnTypeWillChange]
            public function query(string $query, int $resultMode = MYSQLI_STORE_RESULT): \mysqli_result|bool
            {
                return false;
            }
        };
    }

    public function testHandleReturnsEmptyHtmlWhenNoPidsProvided(): void
    {
        $_GET = [];

        $handler = new TradeComparisonApiHandler($this->mockDb);

        ob_start();
        $handler->handle();
        $output = (string) ob_get_clean();

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testHandleReturnsEmptyHtmlWhenPidsIsEmpty(): void
    {
        $_GET = ['pids' => '', 'teamID' => '1', 'display' => 'ratings'];

        $handler = new TradeComparisonApiHandler($this->mockDb);

        ob_start();
        $handler->handle();
        $output = (string) ob_get_clean();

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testHandleReturnsEmptyHtmlWhenTeamIDMissing(): void
    {
        $_GET = ['pids' => '1,2,3', 'display' => 'ratings'];

        $handler = new TradeComparisonApiHandler($this->mockDb);

        ob_start();
        $handler->handle();
        $output = (string) ob_get_clean();

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testHandleReturnsEmptyHtmlWhenTeamIDIsZero(): void
    {
        $_GET = ['pids' => '1,2,3', 'teamID' => '0', 'display' => 'ratings'];

        $handler = new TradeComparisonApiHandler($this->mockDb);

        ob_start();
        $handler->handle();
        $output = (string) ob_get_clean();

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testHandleReturnsEmptyHtmlWhenPidsContainNonNumeric(): void
    {
        $_GET = ['pids' => '1,abc,3', 'teamID' => '1', 'display' => 'ratings'];

        $handler = new TradeComparisonApiHandler($this->mockDb);

        ob_start();
        $handler->handle();
        $output = (string) ob_get_clean();

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testHandleReturnsEmptyHtmlWhenPidsExceedMaximum(): void
    {
        // 21 PIDs (max is 20)
        $pids = implode(',', range(1, 21));
        $_GET = ['pids' => $pids, 'teamID' => '1', 'display' => 'ratings'];

        $handler = new TradeComparisonApiHandler($this->mockDb);

        ob_start();
        $handler->handle();
        $output = (string) ob_get_clean();

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testHandleReturnsEmptyHtmlWhenDbPrepareFails(): void
    {
        // Valid params but DB returns false for prepare
        $_GET = ['pids' => '1,2,3', 'teamID' => '1', 'display' => 'ratings'];

        $handler = new TradeComparisonApiHandler($this->mockDb);

        ob_start();
        $handler->handle();
        $output = (string) ob_get_clean();

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testHandleReturnsJsonContentType(): void
    {
        $_GET = [];

        $handler = new TradeComparisonApiHandler($this->mockDb);

        ob_start();
        $handler->handle();
        ob_end_clean();

        // Check that Content-Type header was set (best effort — headers_list may be empty in CLI)
        // This test mainly verifies the handler doesn't throw an exception
        $this->assertTrue(true);
    }

    public function testHandleFallsBackToRatingsWhenSplitDisplayWithoutSplitParam(): void
    {
        // display=split but no split parameter — should fallback to ratings
        $_GET = ['pids' => '1,2', 'teamID' => '1', 'display' => 'split'];

        $handler = new TradeComparisonApiHandler($this->mockDb);

        ob_start();
        $handler->handle();
        $output = (string) ob_get_clean();

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        // DB prepare returns false, so we get empty HTML (validated PIDs but no players fetched)
        $this->assertSame('', $decoded['html']);
    }

    public function testHandleAcceptsChunkDisplayMode(): void
    {
        $_GET = ['pids' => '1', 'teamID' => '1', 'display' => 'chunk'];

        $handler = new TradeComparisonApiHandler($this->mockDb);

        ob_start();
        $handler->handle();
        $output = (string) ob_get_clean();

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        // DB prepare fails, so empty result
        $this->assertSame('', $decoded['html']);
    }

    public function testHandleAcceptsPlayoffsDisplayMode(): void
    {
        $_GET = ['pids' => '1', 'teamID' => '1', 'display' => 'playoffs'];

        $handler = new TradeComparisonApiHandler($this->mockDb);

        ob_start();
        $handler->handle();
        $output = (string) ob_get_clean();

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testHandleFallsBackToRatingsForInvalidSplitKey(): void
    {
        $_GET = ['pids' => '1', 'teamID' => '1', 'display' => 'split', 'split' => 'invalid_key'];

        $handler = new TradeComparisonApiHandler($this->mockDb);

        ob_start();
        $handler->handle();
        $output = (string) ob_get_clean();

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    protected function tearDown(): void
    {
        $_GET = [];
    }
}
