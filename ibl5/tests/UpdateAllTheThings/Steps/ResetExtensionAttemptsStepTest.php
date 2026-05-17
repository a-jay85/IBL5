<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use PHPUnit\Framework\TestCase;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\ResetExtensionAttemptsStep;
use Tests\WideUnit\Mocks\MockDatabase;
use Tests\WideUnit\Mocks\MockPreparedStatement;

class ResetExtensionAttemptsStepTest extends TestCase
{
    public function testImplementsPipelineStepInterface(): void
    {
        $mockDb = new MockDatabase();
        $db = $this->createMockMysqli($mockDb);
        $step = new ResetExtensionAttemptsStep($db);

        $this->assertInstanceOf(PipelineStepInterface::class, $step);
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $mockDb = new MockDatabase();
        $db = $this->createMockMysqli($mockDb);
        $step = new ResetExtensionAttemptsStep($db);

        $this->assertSame('Extension attempts reset', $step->getLabel());
    }

    public function testExecuteReturnsSuccess(): void
    {
        $mockDb = new MockDatabase();
        $db = $this->createMockMysqli($mockDb);
        $step = new ResetExtensionAttemptsStep($db);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertSame('Extension attempts reset', $result->label);
    }

    private function createMockMysqli(MockDatabase $mockDb): \mysqli
    {
        return new class($mockDb) extends \mysqli {
            private MockDatabase $mockDb;

            public function __construct(MockDatabase $mockDb)
            {
                $this->mockDb = $mockDb;
            }

            #[\ReturnTypeWillChange]
            public function prepare(string $query): MockPreparedStatement|false
            {
                return new MockPreparedStatement($this->mockDb, $query);
            }
        };
    }
}
