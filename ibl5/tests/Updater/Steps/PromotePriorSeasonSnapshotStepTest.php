<?php

declare(strict_types=1);

namespace Tests\Updater\Steps;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use PHPUnit\Framework\TestCase;
use PlrParser\Contracts\PlrParserRepositoryInterface;
use Updater\Steps\PromotePriorSeasonSnapshotStep;

/**
 * @covers \Updater\Steps\PromotePriorSeasonSnapshotStep
 */
class PromotePriorSeasonSnapshotStepTest extends TestCase
{
    public function testPromotesWhenPriorSeasonHasChampion(): void
    {
        /** @var JsbImportRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
        $stubJsbRepo = self::createStub(JsbImportRepositoryInterface::class);
        $stubJsbRepo->method('hasChampionForSeason')->willReturn(true);

        /** @var PlrParserRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject */
        $mockSnapshotRepo = self::createMock(PlrParserRepositoryInterface::class);
        $mockSnapshotRepo->expects($this->once())
            ->method('promotePriorSeasonSnapshots')
            ->with(2008)
            ->willReturn(722);

        $step = new PromotePriorSeasonSnapshotStep($mockSnapshotRepo, $stubJsbRepo, 2009);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('722', $result->detail);
    }

    public function testSkipsWhenPriorSeasonHasNoChampion(): void
    {
        /** @var JsbImportRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
        $stubJsbRepo = self::createStub(JsbImportRepositoryInterface::class);
        $stubJsbRepo->method('hasChampionForSeason')->willReturn(false);

        /** @var PlrParserRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject */
        $mockSnapshotRepo = self::createMock(PlrParserRepositoryInterface::class);
        $mockSnapshotRepo->expects($this->never())
            ->method('promotePriorSeasonSnapshots');

        $step = new PromotePriorSeasonSnapshotStep($mockSnapshotRepo, $stubJsbRepo, 2009);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('2008', $result->detail);
    }

    public function testReportsAlreadyPromotedWhenZeroRowsCopied(): void
    {
        /** @var JsbImportRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
        $stubJsbRepo = self::createStub(JsbImportRepositoryInterface::class);
        $stubJsbRepo->method('hasChampionForSeason')->willReturn(true);

        /** @var PlrParserRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
        $stubSnapshotRepo = self::createStub(PlrParserRepositoryInterface::class);
        $stubSnapshotRepo->method('promotePriorSeasonSnapshots')->willReturn(0);

        $step = new PromotePriorSeasonSnapshotStep($stubSnapshotRepo, $stubJsbRepo, 2009);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('already promoted', $result->detail);
    }

    public function testIgnoresCurrentSeasonChampionState(): void
    {
        /** @var JsbImportRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
        $stubJsbRepo = self::createStub(JsbImportRepositoryInterface::class);
        $stubJsbRepo->method('hasChampionForSeason')
            ->willReturnCallback(static fn (int $year): bool => $year === 2009);

        /** @var PlrParserRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject */
        $mockSnapshotRepo = self::createMock(PlrParserRepositoryInterface::class);
        $mockSnapshotRepo->expects($this->never())
            ->method('promotePriorSeasonSnapshots');

        $step = new PromotePriorSeasonSnapshotStep($mockSnapshotRepo, $stubJsbRepo, 2009);
        $result = $step->execute();

        // priorYear = 2009 - 1 = 2008, which has no champion; step must skip
        $this->assertTrue($result->success);
        $this->assertStringContainsString('No champion', $result->detail);
    }
}
