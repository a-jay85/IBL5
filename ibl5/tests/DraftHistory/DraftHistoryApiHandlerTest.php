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
    public function testCanBeInstantiated(): void
    {
        $handler = new DraftHistoryApiHandler($GLOBALS['mysqli_db']);

        $this->assertInstanceOf(DraftHistoryApiHandler::class, $handler);
    }
}
