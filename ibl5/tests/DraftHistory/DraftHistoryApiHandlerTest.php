<?php

declare(strict_types=1);

namespace Tests\DraftHistory;

use DraftHistory\DraftHistoryApiHandler;
use Tests\Integration\IntegrationTestCase;

/**
 * @covers \DraftHistory\DraftHistoryApiHandler
 */
class DraftHistoryApiHandlerTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // getLastDraftYear() queries: SELECT draftyear FROM ibl_plr ORDER BY draftyear DESC LIMIT 1
        $this->mockDb->onQuery('draftyear.*ORDER BY', [['draftyear' => 2024]]);

        // getDraftPicksByYear() — return empty array (no picks for any year)
        $this->mockDb->setMockData([]);
    }

    protected function tearDown(): void
    {
        $_GET = [];
        parent::tearDown();
    }

    public function testCanBeInstantiated(): void
    {
        $handler = new DraftHistoryApiHandler($GLOBALS['mysqli_db']);

        $this->assertInstanceOf(DraftHistoryApiHandler::class, $handler);
    }

    public function testHandleWithNoYearParamProducesOutput(): void
    {
        $_GET = [];
        $handler = new DraftHistoryApiHandler($GLOBALS['mysqli_db']);

        $output = $this->captureOutput(static fn () => $handler->handle());

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('draft-no-data', $output);
    }

    public function testHandleWithOutOfRangeYearProducesOutput(): void
    {
        $_GET = ['year' => '1800'];
        $handler = new DraftHistoryApiHandler($GLOBALS['mysqli_db']);

        $output = $this->captureOutput(static fn () => $handler->handle());

        $this->assertNotEmpty($output);
        // Falls back to endYear (2024), which also has no picks in mock
        $this->assertStringContainsString('draft-no-data', $output);
    }

    public function testHandleWithNonNumericYearProducesOutput(): void
    {
        $_GET = ['year' => 'abc'];
        $handler = new DraftHistoryApiHandler($GLOBALS['mysqli_db']);

        $output = $this->captureOutput(static fn () => $handler->handle());

        $this->assertNotEmpty($output);
        // (int)'abc' === 0, which is < startYear (1988), so falls back to endYear
        $this->assertStringContainsString('draft-no-data', $output);
    }
}
