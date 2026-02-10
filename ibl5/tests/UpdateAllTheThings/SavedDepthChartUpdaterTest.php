<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings;

use Tests\Integration\IntegrationTestCase;
use Updater\SavedDepthChartUpdater;

/**
 * @covers \Updater\SavedDepthChartUpdater
 */
class SavedDepthChartUpdaterTest extends IntegrationTestCase
{
    public function testUpdateCallsRepositoryExtend(): void
    {
        $updater = new SavedDepthChartUpdater($this->mockDb);

        $result = $updater->update('2024-12-15', 42);

        $this->assertIsInt($result);
        $this->assertQueryExecuted('UPDATE');
    }

    public function testUpdatePassesParametersToQuery(): void
    {
        $updater = new SavedDepthChartUpdater($this->mockDb);

        $updater->update('2025-03-10', 15);

        $this->assertQueryExecuted('2025-03-10');
        $this->assertQueryExecuted('is_active');
    }
}
