<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\RefreshTeamSeasonRecordsStep;

class RefreshTeamSeasonRecordsStepTest extends TestCase
{
    public function testImplementsPipelineStepInterface(): void
    {
        self::assertContains(
            PipelineStepInterface::class,
            (array) class_implements(RefreshTeamSeasonRecordsStep::class)
        );
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $stub = self::createStub(\mysqli::class);
        $this->assertSame(
            'team season records refreshed',
            (new RefreshTeamSeasonRecordsStep($stub))->getLabel(),
        );
    }

    public function testExecuteReturnsSuccess(): void
    {
        $mockDb = new MockDatabase();
        $result = (new RefreshTeamSeasonRecordsStep($mockDb))->execute();

        $this->assertTrue($result->success);
        $this->assertSame('team season records refreshed', $result->label);
    }

    public function testExecuteWrapsInTransaction(): void
    {
        $mockDb = new MockDatabase();
        (new RefreshTeamSeasonRecordsStep($mockDb))->execute();

        $log = $mockDb->getOperationLog();
        $beginIdx = array_search('BEGIN', $log, true);
        $commitIdx = array_search('COMMIT', $log, true);

        $this->assertNotFalse($beginIdx, 'Expected BEGIN in operation log');
        $this->assertNotFalse($commitIdx, 'Expected COMMIT in operation log');
        $this->assertLessThan($commitIdx, $beginIdx, 'BEGIN must precede COMMIT');
    }

    public function testExecuteRunsBothInserts(): void
    {
        $mockDb = new MockDatabase();
        (new RefreshTeamSeasonRecordsStep($mockDb))->execute();

        $insertQueries = array_filter(
            $mockDb->getExecutedQueries(),
            static fn (string $q): bool => stripos($q, 'INSERT') === 0
                && str_contains($q, 'ibl_team_season_records'),
        );

        $this->assertCount(2, array_values($insertQueries), 'Expected two INSERTs into ibl_team_season_records');
    }
}
