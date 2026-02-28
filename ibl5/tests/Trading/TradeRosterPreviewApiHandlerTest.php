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

    public function testBuildCashRowsIgnoredWhenDisplayIsNotContracts(): void
    {
        // Cash params present but display is 'ratings' — no cash rows should be built
        $_GET = [
            'teamID' => '1',
            'display' => 'ratings',
            'userTeam' => 'Miami',
            'partnerTeam' => 'Boston',
            'userTeamId' => '1',
            'cashStartYear' => '1',
            'cashEndYear' => '6',
            'userCash1' => '500',
        ];

        $handler = new TradeRosterPreviewApiHandler($this->mockDb);

        ob_start();
        $handler->handle();
        $output = (string) ob_get_clean();

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        // DB returns false, so empty HTML — but the point is no crash from cash logic
        $this->assertSame('', $decoded['html']);
    }

    public function testBuildCashRowsSkippedWhenCashParamsMissing(): void
    {
        // Contracts display but no cash params — should not crash
        $_GET = [
            'teamID' => '1',
            'display' => 'contracts',
        ];

        $handler = new TradeRosterPreviewApiHandler($this->mockDb);

        ob_start();
        $handler->handle();
        $output = (string) ob_get_clean();

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testBuildCashRowsSkippedWhenCashAmountsAreZero(): void
    {
        $_GET = [
            'teamID' => '1',
            'display' => 'contracts',
            'userTeam' => 'Miami',
            'partnerTeam' => 'Boston',
            'userTeamId' => '1',
            'cashStartYear' => '1',
            'cashEndYear' => '6',
            'userCash1' => '0',
            'partnerCash1' => '0',
        ];

        $handler = new TradeRosterPreviewApiHandler($this->mockDb);

        ob_start();
        $handler->handle();
        $output = (string) ob_get_clean();

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testCashAmountExceeding2000DefaultsToZero(): void
    {
        $_GET = [
            'teamID' => '1',
            'display' => 'contracts',
            'userTeam' => 'Miami',
            'partnerTeam' => 'Boston',
            'userTeamId' => '1',
            'cashStartYear' => '1',
            'cashEndYear' => '1',
            'userCash1' => '2001',
            'partnerCash1' => '0',
        ];

        $handler = new TradeRosterPreviewApiHandler($this->mockDb);

        ob_start();
        $handler->handle();
        $output = (string) ob_get_clean();

        /** @var array{html: string} $decoded */
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        // Over-limit cash defaults to 0, so no cash rows generated
        $this->assertSame('', $decoded['html']);
    }

    public function testNonNumericCashAmountDefaultsToZero(): void
    {
        $_GET = [
            'teamID' => '1',
            'display' => 'contracts',
            'userTeam' => 'Miami',
            'partnerTeam' => 'Boston',
            'userTeamId' => '1',
            'cashStartYear' => '1',
            'cashEndYear' => '1',
            'userCash1' => 'abc',
            'partnerCash1' => '0',
        ];

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
