<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;
use Updater\Steps\RefreshIblHistStep;

class RefreshIblHistStepTest extends TestCase
{
    public function testGetLabelReturnsExpectedLabel(): void
    {
        $stub = self::createStub(\mysqli::class);
        $this->assertSame('ibl_hist refreshed', (new RefreshIblHistStep($stub))->getLabel());
    }

    public function testExecuteReturnsSuccess(): void
    {
        $mockDb = new MockDatabase();
        $result = (new RefreshIblHistStep($mockDb))->execute();

        $this->assertTrue($result->success);
        $this->assertSame('ibl_hist refreshed', $result->label);
    }

    public function testExecuteWrapsInTransaction(): void
    {
        $mockDb = new MockDatabase();
        (new RefreshIblHistStep($mockDb))->execute();

        $log = $mockDb->getOperationLog();
        $beginIdx = array_search('BEGIN', $log, true);
        $commitIdx = array_search('COMMIT', $log, true);

        $this->assertNotFalse($beginIdx, 'Expected BEGIN in operation log');
        $this->assertNotFalse($commitIdx, 'Expected COMMIT in operation log');
        $this->assertLessThan($commitIdx, $beginIdx, 'BEGIN must precede COMMIT');
    }
}
