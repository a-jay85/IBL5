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
    public function testCanBeInstantiated(): void
    {
        $handler = new FranchiseRecordBookApiHandler($GLOBALS['mysqli_db']);

        $this->assertInstanceOf(FranchiseRecordBookApiHandler::class, $handler);
    }
}
