<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\SuggestionPlayer;

class SuggestionPlayerTest extends TestCase
{
    /**
     * @param array<string, int> $depth
     */
    private function player(array $depth): SuggestionPlayer
    {
        return new SuggestionPlayer(
            pid: 1,
            name: 'Test Player',
            pos: 'PG',
            age: 25,
            injured: false,
            currentSalary: 500,
            yearsRemaining: 2,
            futureSalary: 500,
            depth: $depth,
        );
    }

    public function testStarterSlotIsNullWithoutDepthData(): void
    {
        self::assertNull($this->player([])->starterSlot());
    }

    public function testStarterSlotIsNullWhenPlayerOnlyBacksUp(): void
    {
        self::assertNull($this->player(['PG' => 2, 'SG' => 3, 'C' => 0])->starterSlot());
    }

    public function testStarterSlotIgnoresSlotsMissingFromDepth(): void
    {
        self::assertSame('SF', $this->player(['SF' => 1])->starterSlot());
    }

    public function testStarterSlotReturnsFirstStartingSlotInOrder(): void
    {
        self::assertSame('SG', $this->player(['C' => 1, 'SG' => 1])->starterSlot());
    }
}
