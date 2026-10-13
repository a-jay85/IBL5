<?php

declare(strict_types=1);

namespace Tests\PlrParser;

use PHPUnit\Framework\TestCase;
use PlrParser\Contracts\PlrParserRepositoryInterface;
use PlrParser\PlrParserRepository;
use Tests\WideUnit\Mocks\MockDatabase;

class PlrParserRepositoryTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $interfaces = class_implements(PlrParserRepository::class);
        $this->assertIsArray($interfaces);
        $this->assertArrayHasKey(PlrParserRepositoryInterface::class, $interfaces);
    }

    public function testPromotePriorSeasonSnapshotsBindsPriorYearAsInteger(): void
    {
        $stmt = $this->getMockBuilder(\mysqli_stmt::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['bind_param', 'execute', 'close'])
            ->getMock();
        $stmt->expects($this->once())
            ->method('bind_param')
            ->with('i', 2008)
            ->willReturn(true);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('close')->willReturn(true);

        $db = self::createStub(\mysqli::class);
        $db->method('prepare')->willReturn($stmt);

        $repository = new class ($db) extends PlrParserRepository {
            protected function getAffectedRows(object $stmt): int
            {
                return 7;
            }
        };

        $this->assertSame(7, $repository->promotePriorSeasonSnapshots(2008));
    }

    public function testUpsertSnapshotBindsOneValuePerSnapshotColumn(): void
    {
        $mockDb = new MockDatabase();
        $repository = new PlrParserRepository($mockDb);

        $data = [];
        foreach (PlrParserRepository::SNAPSHOT_COLUMNS as $column) {
            $data[$column] = 1;
        }

        $repository->upsertSnapshot($data);

        $this->assertCount(count(PlrParserRepository::SNAPSHOT_COLUMNS), $mockDb->getLastBoundParams());
        $preparedQueries = $mockDb->getPreparedQueries();
        $this->assertCount(1, $preparedQueries);
        $this->assertSame(count(PlrParserRepository::SNAPSHOT_COLUMNS), substr_count($preparedQueries[0], '?'));
    }
}
