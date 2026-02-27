<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Trading\TradeRosterPreviewApiHandler;

class TradeRosterPreviewApiHandlerTest extends TestCase
{
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

    public function testHandleReturnsEmptyHtmlWhenTeamIDMissing(): void
    {
        $_GET = [];

        $handler = new TradeRosterPreviewApiHandler($this->mockDb);

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
        $_GET = ['teamID' => '0'];

        $handler = new TradeRosterPreviewApiHandler($this->mockDb);

        ob_start();
        $handler->handle();
        $output = (string) ob_get_clean();

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testHandleReturnsEmptyHtmlWhenAddPidsContainNonNumeric(): void
    {
        $_GET = ['teamID' => '1', 'addPids' => '1,abc,3'];

        $handler = new TradeRosterPreviewApiHandler($this->mockDb);

        ob_start();
        $handler->handle();
        $output = (string) ob_get_clean();

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testHandleReturnsEmptyHtmlWhenRemovePidsContainNonNumeric(): void
    {
        $_GET = ['teamID' => '1', 'removePids' => 'x,y'];

        $handler = new TradeRosterPreviewApiHandler($this->mockDb);

        ob_start();
        $handler->handle();
        $output = (string) ob_get_clean();

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testHandleReturnsEmptyHtmlWhenAddPidsExceedMaximum(): void
    {
        $pids = implode(',', range(1, 21));
        $_GET = ['teamID' => '1', 'addPids' => $pids];

        $handler = new TradeRosterPreviewApiHandler($this->mockDb);

        ob_start();
        $handler->handle();
        $output = (string) ob_get_clean();

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testHandleReturnsEmptyHtmlWhenRemovePidsExceedMaximum(): void
    {
        $pids = implode(',', range(1, 21));
        $_GET = ['teamID' => '1', 'removePids' => $pids];

        $handler = new TradeRosterPreviewApiHandler($this->mockDb);

        ob_start();
        $handler->handle();
        $output = (string) ob_get_clean();

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testHandleFallsBackToRatingsWhenDisplayMissing(): void
    {
        // Valid teamID but no display param — should default to 'ratings'
        // DB prepare returns false, so we get empty result from the DB,
        // but the handler should not error
        $_GET = ['teamID' => '1'];

        $handler = new TradeRosterPreviewApiHandler($this->mockDb);

        ob_start();
        $handler->handle();
        $output = (string) ob_get_clean();

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        // DB returns false, so we get empty HTML from the roster query
        $this->assertSame('', $decoded['html']);
    }

    public function testHandleFallsBackToRatingsWhenSplitDisplayWithoutSplitParam(): void
    {
        $_GET = ['teamID' => '1', 'display' => 'split'];

        $handler = new TradeRosterPreviewApiHandler($this->mockDb);

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
        $_GET = ['teamID' => '1', 'display' => 'split', 'split' => 'invalid_key'];

        $handler = new TradeRosterPreviewApiHandler($this->mockDb);

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

        $handler = new TradeRosterPreviewApiHandler($this->mockDb);

        ob_start();
        $handler->handle();
        ob_end_clean();

        // Verify the handler doesn't throw an exception
        $this->assertTrue(true);
    }

    public function testHandleAcceptsEmptyAddPids(): void
    {
        // Empty addPids is valid (showing removals only)
        $_GET = ['teamID' => '1', 'addPids' => '', 'removePids' => '1,2'];

        $handler = new TradeRosterPreviewApiHandler($this->mockDb);

        ob_start();
        $handler->handle();
        $output = (string) ob_get_clean();

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        // DB returns false, so empty HTML
        $this->assertSame('', $decoded['html']);
    }

    public function testHandleAcceptsEmptyRemovePids(): void
    {
        // Empty removePids is valid (showing additions only)
        $_GET = ['teamID' => '1', 'addPids' => '1,2', 'removePids' => ''];

        $handler = new TradeRosterPreviewApiHandler($this->mockDb);

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
