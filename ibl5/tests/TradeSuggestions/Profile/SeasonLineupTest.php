<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Profile;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Fixtures\ProjectionFactory;
use TradeSuggestions\Profile\LineupAdapter;
use TradeSuggestions\Projection\PlayerProjection;
use TradeSuggestions\SlotMapper;

final class SeasonLineupTest extends TestCase
{
    public function testOnlyPlayersUnderContractAtOffsetAreInLineup(): void
    {
        $expiring = ProjectionFactory::player(pid: 5, salaryByYear: [1 => 500, 2 => 500, 3 => 0, 4 => 0, 5 => 0, 6 => 0]);
        $long = ProjectionFactory::player(pid: 6, pos: 'C', salaryByYear: [1 => 500, 2 => 500, 3 => 500, 4 => 500, 5 => 0, 6 => 0]);

        $atOne = ProjectionFactory::lineupFor(1, 1, [$expiring, $long]);
        $atTwo = ProjectionFactory::lineupFor(1, 2, [$expiring, $long]);

        self::assertSame([5, 6], array_keys($atOne->players));
        self::assertSame([6], array_keys($atTwo->players));
        self::assertSame(5, $atOne->starterPid('PG'));
        self::assertNull($atTwo->starterPid('PG'));
    }

    public function testEmptyRosterYieldsNullStartersAndNoException(): void
    {
        $lineup = ProjectionFactory::lineupFor(3, 0, []);

        foreach (SlotMapper::SLOTS as $slot) {
            self::assertNull($lineup->starterPid($slot));
            self::assertSame([], $lineup->rotation[$slot]);
            self::assertSame([], $lineup->benchPids($slot));
        }
        self::assertSame([], $lineup->players);
        self::assertSame(3, $lineup->tid);
    }

    public function testUnreliableChartFallsBackToMinutesOrder(): void
    {
        $lineup = ProjectionFactory::lineupFor(1, 0, [
            ProjectionFactory::player(pid: 1, dcDepth: ['PG' => 1], dcMinutes: 20),
            ProjectionFactory::player(pid: 2, dcDepth: ['PG' => 1], dcMinutes: 30),
        ]);

        self::assertFalse($lineup->chartReliable);
        self::assertSame(2, $lineup->starterPid('PG'));
        self::assertSame([2, 1], $lineup->rotation['PG']);
        self::assertSame([1], $lineup->benchPids('PG'));
    }

    public function testMinutesWeightUsesDcMinutesWhenReliableAndPerGameOtherwise(): void
    {
        $reliable = [];
        foreach (SlotMapper::SLOTS as $i => $slot) {
            $reliable[] = ProjectionFactory::player(pid: 10 + $i, pos: $slot, dcDepth: [$slot => 1], dcMinutes: 30);
        }
        $reliableLineup = ProjectionFactory::lineupFor(1, 0, $reliable);

        self::assertTrue($reliableLineup->chartReliable);
        self::assertSame(30.0, $reliableLineup->minutesWeight[10]);

        // Default production line: 1000 minutes over 40 games.
        $fallback = ProjectionFactory::lineupFor(2, 0, [
            ProjectionFactory::player(pid: 20, dcMinutes: 30),
            ProjectionFactory::player(pid: 21, pos: 'C', dcMinutes: 30, noProduction: true),
        ]);

        self::assertFalse($fallback->chartReliable);
        self::assertSame(25.0, $fallback->minutesWeight[20]);
        self::assertSame(0.0, $fallback->minutesWeight[21]);
    }

    public function testAdapterMarksInjuredOnlyAtOffsetZero(): void
    {
        $player = ProjectionFactory::player(injuredDays: 10);
        $seasons = PlayerProjection::project($player, ProjectionFactory::scale());

        $now = LineupAdapter::toSuggestionPlayer($player, $seasons[0]);
        $next = LineupAdapter::toSuggestionPlayer($player, $seasons[1]);

        self::assertTrue($now->injured);
        self::assertFalse($next->injured);
        self::assertSame(27, $next->age);
        self::assertSame(500, $now->currentSalary);
        self::assertSame(3, $now->yearsRemaining);
        self::assertSame([500, 500], $now->laterSalaries);
        self::assertSame(500, $now->futureSalary);
    }
}
