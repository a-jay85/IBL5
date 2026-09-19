<?php

declare(strict_types=1);

namespace Tests\Updater\SeasonRollover;

use LeagueControlPanel\Contracts\LeagueControlPanelRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Updater\SeasonRollover\SeasonRolloverApplier;
use Updater\SeasonRollover\SeasonRolloverDecisionResult;

final class SeasonRolloverApplierTest extends TestCase
{
    public function testAdvanceWritesYearAndPhaseReturnsTrue(): void
    {
        $repo = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('updateSetting')
            ->with('Current Season Ending Year', '2026')
            ->willReturn(true);
        $repo->expects($this->once())
            ->method('setSeasonPhase')
            ->with('Preseason')
            ->willReturn(true);

        $applier = new SeasonRolloverApplier($repo);
        $result = SeasonRolloverDecisionResult::advance(2026, 'Preseason', 'reason');

        self::assertTrue($applier->apply($result));
    }

    public function testAdvancePassesPhaseVerbatimNotSlug(): void
    {
        $repo = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('updateSetting')
            ->with('Current Season Ending Year', '2026')
            ->willReturn(true);
        $repo->expects($this->once())
            ->method('setSeasonPhase')
            ->with('Regular Season')
            ->willReturn(true);

        $applier = new SeasonRolloverApplier($repo);
        $result = SeasonRolloverDecisionResult::advance(2026, 'Regular Season', 'reason');

        self::assertTrue($applier->apply($result));
    }

    public function testNoOpWritesNothingReturnsFalse(): void
    {
        $repo = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $repo->expects($this->never())->method('updateSetting');
        $repo->expects($this->never())->method('setSeasonPhase');

        $applier = new SeasonRolloverApplier($repo);
        $result = SeasonRolloverDecisionResult::noOp('no change');

        self::assertFalse($applier->apply($result));
    }

    public function testHaltWritesNothingReturnsFalse(): void
    {
        $repo = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $repo->expects($this->never())->method('updateSetting');
        $repo->expects($this->never())->method('setSeasonPhase');

        $applier = new SeasonRolloverApplier($repo);
        $result = SeasonRolloverDecisionResult::halt('something wrong');

        self::assertFalse($applier->apply($result));
    }
}
