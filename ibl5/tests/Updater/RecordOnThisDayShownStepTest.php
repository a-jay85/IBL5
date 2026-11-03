<?php

declare(strict_types=1);

namespace Tests\Updater;

use OnThisDay\Contracts\OnThisDayServiceInterface;
use OnThisDay\Contracts\ShownMomentHistoryInterface;
use OnThisDay\Moment;
use OnThisDay\MomentKind;
use OnThisDay\OnThisDaySelection;
use PHPUnit\Framework\TestCase;
use Updater\Steps\RecordOnThisDayShownStep;

/**
 * @covers \Updater\Steps\RecordOnThisDayShownStep
 */
final class RecordOnThisDayShownStepTest extends TestCase
{
    public function testRecordsEveryShownKeyForTheSim(): void
    {
        $selection = $this->makeSelection([$this->makeMoment('A'), $this->makeMoment('B')]);

        $history = self::createMock(ShownMomentHistoryInterface::class);
        $history->expects($this->once())
            ->method('recordShown')
            ->with(20, 2031, ['A', 'B'])
            ->willReturn(2);

        $result = (new RecordOnThisDayShownStep($this->makeService($selection), $history))->execute();

        $this->assertTrue($result->success);
        $this->assertFalse($result->skipped);
        $this->assertStringContainsString('Recorded 2 moment(s) for sim 20', $result->detail);
    }

    public function testEmptySelectionRecordsNothing(): void
    {
        $selection = $this->makeSelection([]);

        $history = self::createMock(ShownMomentHistoryInterface::class);
        $history->expects($this->never())->method('recordShown');

        $result = (new RecordOnThisDayShownStep($this->makeService($selection), $history))->execute();

        $this->assertTrue($result->skipped);
        $this->assertStringContainsString('No moment selected for sim 20', $result->detail);
    }

    public function testHistoryFailureSkipsWithoutThrowing(): void
    {
        $selection = $this->makeSelection([$this->makeMoment('A')]);

        $history = self::createStub(ShownMomentHistoryInterface::class);
        $history->method('recordShown')->willThrowException(new \mysqli_sql_exception('gone', 1146));

        $result = (new RecordOnThisDayShownStep($this->makeService($selection), $history))->execute();

        $this->assertTrue($result->skipped);
        $this->assertStringContainsString('gone', $result->detail);
    }

    /**
     * @param list<Moment> $moments
     */
    private function makeSelection(array $moments): OnThisDaySelection
    {
        return new OnThisDaySelection(
            moments: $moments,
            currentSeasonYear: 2031,
            centerDate: '2031-03-17',
            radiusUsed: 0,
            sim: 20,
        );
    }

    private function makeService(OnThisDaySelection $selection): OnThisDayServiceInterface
    {
        $service = self::createStub(OnThisDayServiceInterface::class);
        $service->method('getMomentsForSim')->willReturn($selection);

        return $service;
    }

    private function makeMoment(string $key): Moment
    {
        return new Moment(
            kind: MomentKind::FiftyPointGame,
            key: $key,
            date: '2029-03-17',
            seasonYear: 2029,
            pid: 1,
            playerName: 'Test Player',
            teamId: 1,
            teamName: 'Hawks',
            opponentTeamId: 2,
            opponentName: 'Celtics',
            facts: ['points' => 52],
        );
    }
}
