<?php

declare(strict_types=1);

namespace Tests\SeriesRecords;

use PHPUnit\Framework\TestCase;
use SeriesRecords\SeriesRecordsController;
use SeriesRecords\Contracts\SeriesRecordsControllerInterface;

/**
 * SeriesRecordsControllerTest - Tests for SeriesRecordsController
 */
class SeriesRecordsControllerTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $interfaces = class_implements(SeriesRecordsController::class);
        self::assertContains(
            SeriesRecordsControllerInterface::class,
            $interfaces ? $interfaces : [],
        );
    }
}
