<?php

declare(strict_types=1);

namespace Tests\Migration;

use Migration\Contracts\MigrationRepositoryInterface;
use Migration\MigrationFileResolver;
use Migration\MigrationRunner;
use PHPUnit\Framework\TestCase;

final class MigrationRunnerTest extends TestCase
{
    private string $tempDir;
    private MigrationRepositoryInterface $stubRepository;
    private MigrationFileResolver $fileResolver;

    /** @var list<string> Simulates already-ran migrations */
    private array $ranMigrations = [];

    /** @var list<array{string, int}> Tracks recordMigration calls */
    private array $recordedMigrations = [];

    /** @var list<string> Tracks executeRawSql calls */
    private array $executedSql = [];

    private int $nextBatch = 1;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ibl5_runner_test_' . uniqid();
        mkdir($this->tempDir);

        $this->ranMigrations = [];
        $this->recordedMigrations = [];
        $this->executedSql = [];
        $this->nextBatch = 1;

        $this->stubRepository = $this->createStubRepository();
        $this->fileResolver = new MigrationFileResolver($this->tempDir);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tempDir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function testGetPendingReturnsAllWhenNoneRan(): void
    {
        file_put_contents($this->tempDir . '/001_first.sql', 'SELECT 1;');
        file_put_contents($this->tempDir . '/002_second.sql', 'SELECT 2;');

        $runner = new MigrationRunner($this->stubRepository, $this->fileResolver);
        $pending = $runner->getPending();

        $this->assertSame(['001_first.sql', '002_second.sql'], $pending);
    }

    public function testGetPendingExcludesAlreadyRan(): void
    {
        file_put_contents($this->tempDir . '/001_first.sql', 'SELECT 1;');
        file_put_contents($this->tempDir . '/002_second.sql', 'SELECT 2;');
        $this->ranMigrations = ['001_first.sql'];

        $runner = new MigrationRunner($this->stubRepository, $this->fileResolver);
        $pending = $runner->getPending();

        $this->assertSame(['002_second.sql'], $pending);
    }

    public function testGetPendingReturnsEmptyWhenAllRan(): void
    {
        file_put_contents($this->tempDir . '/001_first.sql', 'SELECT 1;');
        $this->ranMigrations = ['001_first.sql'];

        $runner = new MigrationRunner($this->stubRepository, $this->fileResolver);
        $pending = $runner->getPending();

        $this->assertSame([], $pending);
    }

    public function testRunPendingReturnsEmptyWhenNothingPending(): void
    {
        $runner = new MigrationRunner($this->stubRepository, $this->fileResolver);
        $executed = $runner->runPending();

        $this->assertSame([], $executed);
    }

    public function testRunPendingExecutesSqlMigrations(): void
    {
        file_put_contents($this->tempDir . '/001_first.sql', 'CREATE TABLE t1 (id INT);');

        $runner = new MigrationRunner($this->stubRepository, $this->fileResolver);
        $executed = $runner->runPending();

        $this->assertSame(['001_first.sql'], $executed);
        $this->assertCount(1, $this->executedSql);
        $this->assertSame('CREATE TABLE t1 (id INT);', $this->executedSql[0]);
    }

    public function testRunPendingRecordsEachMigration(): void
    {
        file_put_contents($this->tempDir . '/001_first.sql', 'SELECT 1;');
        file_put_contents($this->tempDir . '/002_second.sql', 'SELECT 2;');

        $runner = new MigrationRunner($this->stubRepository, $this->fileResolver);
        $runner->runPending();

        $this->assertCount(2, $this->recordedMigrations);
        $this->assertSame('001_first.sql', $this->recordedMigrations[0][0]);
        $this->assertSame(1, $this->recordedMigrations[0][1]);
        $this->assertSame('002_second.sql', $this->recordedMigrations[1][0]);
        $this->assertSame(1, $this->recordedMigrations[1][1]);
    }

    public function testRunPendingUsesCorrectBatchNumber(): void
    {
        $this->nextBatch = 5;
        file_put_contents($this->tempDir . '/001_first.sql', 'SELECT 1;');

        $runner = new MigrationRunner($this->stubRepository, $this->fileResolver);
        $runner->runPending();

        $this->assertSame(5, $this->recordedMigrations[0][1]);
    }

    public function testRunPendingThrowsOnMissingFile(): void
    {
        // Use a fake file resolver that reports a file that doesn't exist on disk
        $fakeResolver = $this->createStub(MigrationFileResolver::class);
        $fakeResolver->method('getAvailableMigrations')
            ->willReturn(['001_ghost.sql']);
        $fakeResolver->method('getFullPath')
            ->willReturn($this->tempDir . '/001_ghost.sql');

        $runner = new MigrationRunner($this->stubRepository, $fakeResolver);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Migration file not found');

        $runner->runPending();
    }

    public function testRunPendingHaltsOnSqlError(): void
    {
        file_put_contents($this->tempDir . '/001_first.sql', 'INVALID SQL;');
        file_put_contents($this->tempDir . '/002_second.sql', 'SELECT 2;');

        // Make executeRawSql throw on the first migration
        $failingRepo = $this->createStubRepository(failOnSql: true);
        $runner = new MigrationRunner($failingRepo, $this->fileResolver);

        try {
            $runner->runPending();
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException) {
            // First migration failed, second should not have been attempted
            $this->assertCount(1, $this->executedSql);
            $this->assertSame([], $this->recordedMigrations);
        }
    }

    public function testRunPendingDoesNotRecordFailedMigration(): void
    {
        file_put_contents($this->tempDir . '/001_first.sql', 'FAIL;');

        $failingRepo = $this->createStubRepository(failOnSql: true);
        $runner = new MigrationRunner($failingRepo, $this->fileResolver);

        try {
            $runner->runPending();
        } catch (\RuntimeException) {
            // Expected
        }

        $this->assertSame([], $this->recordedMigrations);
    }

    /**
     * Create a stub MigrationRepositoryInterface that tracks calls.
     */
    private function createStubRepository(bool $failOnSql = false): MigrationRepositoryInterface
    {
        $stub = $this->createStub(MigrationRepositoryInterface::class);

        $stub->method('getRanMigrations')
            ->willReturnCallback(fn(): array => $this->ranMigrations);

        $stub->method('getNextBatchNumber')
            ->willReturnCallback(fn(): int => $this->nextBatch);

        $stub->method('recordMigration')
            ->willReturnCallback(function (string $filename, int $batch): void {
                $this->recordedMigrations[] = [$filename, $batch];
            });

        $stub->method('executeRawSql')
            ->willReturnCallback(function (string $sql) use ($failOnSql): void {
                $this->executedSql[] = $sql;
                if ($failOnSql) {
                    throw new \RuntimeException('SQL execution failed');
                }
            });

        $stub->method('truncate')
            ->willReturnCallback(static function (): void {
                // no-op
            });

        return $stub;
    }
}
