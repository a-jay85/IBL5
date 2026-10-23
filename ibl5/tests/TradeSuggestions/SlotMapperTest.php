<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\SlotMapper;
use TradeSuggestions\SuggestionPlayer;

final class SlotMapperTest extends TestCase
{
    public function testSlotsForPosMapsHybridPositions(): void
    {
        self::assertSame(['PG', 'SG'], SlotMapper::slotsForPos('G'));
        self::assertSame(['SF', 'PF'], SlotMapper::slotsForPos('F'));
        self::assertSame(['SG', 'SF'], SlotMapper::slotsForPos('GF'));
        self::assertSame(['C'], SlotMapper::slotsForPos('C'));
        self::assertSame([], SlotMapper::slotsForPos(''));
    }

    public function testValidChartUsesDepthOneStarters(): void
    {
        $players = [
            self::player(1, 'PG', ['PG' => 1]),
            self::player(2, 'SG', ['SG' => 1]),
            // A C charted at SF still starts there when the chart is valid.
            self::player(3, 'C', ['SF' => 1]),
            self::player(4, 'PF', ['PF' => 1]),
            self::player(5, 'C', ['C' => 1]),
            self::player(6, 'SF', ['SF' => 2], dcMinutes: 40),
        ];

        $depth = SlotMapper::depthFor($players);

        self::assertTrue($depth->chartReliable);
        self::assertSame(3, $depth->starterAt('SF')?->pid);
        self::assertSame('SF', $depth->slotOf(3));
        self::assertNull($depth->slotOf(6));
    }

    public function testChartWithDuplicateSlotStarterFallsBackToMinutes(): void
    {
        $players = [
            self::player(1, 'PG', ['PG' => 1], dcMinutes: 30),
            self::player(2, 'PG', ['PG' => 1], dcMinutes: 35),
            self::player(3, 'SG', ['SG' => 1], dcMinutes: 30),
            self::player(4, 'SF', ['SF' => 1], dcMinutes: 30),
            self::player(5, 'PF', ['PF' => 1], dcMinutes: 30),
            self::player(6, 'C', ['C' => 1], dcMinutes: 30),
        ];

        $depth = SlotMapper::depthFor($players);

        self::assertFalse($depth->chartReliable);
        self::assertSame(2, $depth->starterAt('PG')?->pid);
        self::assertSame(3, $depth->starterAt('SG')?->pid);
        self::assertSame(6, $depth->starterAt('C')?->pid);
    }

    public function testChartMissingCenterStarterIsUnreliable(): void
    {
        $players = [
            self::player(1, 'PG', ['PG' => 1], dcMinutes: 30),
            self::player(2, 'SG', ['SG' => 1], dcMinutes: 30),
            self::player(3, 'SF', ['SF' => 1], dcMinutes: 30),
            self::player(4, 'PF', ['PF' => 1], dcMinutes: 30),
            self::player(5, 'C', ['C' => 2], dcMinutes: 20),
        ];

        $depth = SlotMapper::depthFor($players);

        self::assertFalse($depth->chartReliable);
        self::assertSame(5, $depth->starterAt('C')?->pid);
    }

    public function testFallbackLeavesSlotEmptyWhenNoEligiblePlayer(): void
    {
        $players = [
            self::player(1, 'G', [], dcMinutes: 30),
            self::player(2, 'G', [], dcMinutes: 28),
            self::player(3, 'F', [], dcMinutes: 26),
            self::player(4, 'F', [], dcMinutes: 24),
        ];

        $depth = SlotMapper::depthFor($players);

        self::assertFalse($depth->chartReliable);
        self::assertSame(1, $depth->starterAt('PG')?->pid);
        self::assertSame(2, $depth->starterAt('SG')?->pid);
        self::assertSame(3, $depth->starterAt('SF')?->pid);
        self::assertSame(4, $depth->starterAt('PF')?->pid);
        self::assertNull($depth->starterAt('C'));
    }

    public function testRotationAtExcludesPlayersUnderRealMinutes(): void
    {
        $players = [
            self::player(1, 'C', [], dcMinutes: 15),
            self::player(2, 'C', [], dcMinutes: 14),
            self::player(3, 'PF', [], dcMinutes: 30),
        ];

        $rotation = SlotMapper::rotationAt($players, 'C');

        self::assertSame([1], array_map(static fn (SuggestionPlayer $p): int => $p->pid, $rotation));
    }

    /**
     * @param array<string, int> $depth
     */
    private static function player(int $pid, string $pos, array $depth, int $dcMinutes = 0): SuggestionPlayer
    {
        return new SuggestionPlayer(
            pid: $pid,
            name: 'Player ' . $pid,
            pos: $pos,
            age: 26,
            ovr: 0,
            injured: false,
            currentSalary: 1000,
            yearsRemaining: 1,
            futureSalary: 0,
            depth: $depth,
            dcMinutes: $dcMinutes,
        );
    }
}
