<?php

declare(strict_types=1);

namespace Tests\HistArchiver;

use HistArchiver\Contracts\HistArchiverServiceInterface;
use HistArchiver\HistArchiveResult;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\ArchiveSeasonHistStep;

class ArchiveSeasonHistStepTest extends TestCase
{
    public function testImplementsPipelineStepInterface(): void
    {
        $service = $this->createStub(HistArchiverServiceInterface::class);
        $step = new ArchiveSeasonHistStep($service, 2026);

        $this->assertInstanceOf(PipelineStepInterface::class, $step);
    }

    public function testGetLabel(): void
    {
        $service = $this->createStub(HistArchiverServiceInterface::class);
        $step = new ArchiveSeasonHistStep($service, 2026);

        $this->assertSame('Season history archived', $step->getLabel());
    }

    public function testSkipsWhenNoChampion(): void
    {
        $service = $this->createStub(HistArchiverServiceInterface::class);
        $service->method('archiveSeason')->willReturn(
            HistArchiveResult::skipped(),
        );

        $step = new ArchiveSeasonHistStep($service, 2026);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('No champion', $result->detail);
    }

    public function testSuccessfulArchive(): void
    {
        $service = $this->createStub(HistArchiverServiceInterface::class);
        $service->method('archiveSeason')->willReturn(
            HistArchiveResult::completed(rowsUpserted: 150, playersArchived: 150, messages: []),
        );

        $step = new ArchiveSeasonHistStep($service, 2026);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertSame('150 players archived', $result->detail);
    }

    public function testArchiveWithWarnings(): void
    {
        $service = $this->createStub(HistArchiverServiceInterface::class);
        $service->method('archiveSeason')->willReturn(
            HistArchiveResult::completed(
                rowsUpserted: 148,
                playersArchived: 148,
                messages: [
                    'WARNING: Player ID 999 (Missing Player) not found in ibl_plr — skipped',
                    'WARNING: Player ID 998 (Another Missing) not found in ibl_plr — skipped',
                ],
            ),
        );

        $step = new ArchiveSeasonHistStep($service, 2026);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertSame('148 players archived', $result->detail);
        $this->assertCount(2, $result->messages);
        $this->assertSame(2, $result->messageErrorCount);
    }
}
