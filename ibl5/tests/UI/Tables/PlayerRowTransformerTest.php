<?php

declare(strict_types=1);

namespace Tests\UI\Tables;

use PHPUnit\Framework\TestCase;
use Player\Player;
use UI\Tables\PlayerRowTransformer;

/**
 * @covers \UI\Tables\PlayerRowTransformer
 */
class PlayerRowTransformerTest extends TestCase
{
    public function testResolveWithStatsReturnsEmptyForEmptyInput(): void
    {
        $db = self::createStub(\mysqli::class);

        $result = PlayerRowTransformer::resolveWithStats($db, [], '');

        $this->assertSame([], $result);
    }

    public function testResolvePlayersReturnsEmptyForEmptyInput(): void
    {
        $db = self::createStub(\mysqli::class);

        $result = PlayerRowTransformer::resolvePlayers($db, [], '');

        $this->assertSame([], $result);
    }

    public function testResolvePlayersFiltersOutPipeNames(): void
    {
        $db = self::createStub(\mysqli::class);

        $player = self::createStub(Player::class);
        $player->method('getName')->willReturn('|Placeholder');

        $result = PlayerRowTransformer::resolvePlayers($db, [$player], '');

        $this->assertSame([], $result);
    }

    public function testResolvePlayersAcceptsPlayerInstances(): void
    {
        $db = self::createStub(\mysqli::class);

        $player = self::createStub(Player::class);
        $player->method('getName')->willReturn('John Doe');

        $result = PlayerRowTransformer::resolvePlayers($db, [$player], '');

        $this->assertCount(1, $result);
        $this->assertSame($player, $result[0]);
    }

    public function testResolveWithStatsSkipsNonArrayNonPlayerForCurrentSeason(): void
    {
        $db = self::createStub(\mysqli::class);

        // Pass an iterable with a non-array/non-Player element. The stdClass argument.type
        // mismatch is a documented baseline defer, not a defect to "fix" by swapping in a
        // real Player — that would delete the non-Player skip path this test exists to prove.
        $result = PlayerRowTransformer::resolveWithStats($db, [new \stdClass()], '');

        $this->assertSame([], $result);
    }

    public function testResolvePlayersSkipsNonArrayForHistorical(): void
    {
        $db = self::createStub(\mysqli::class);

        $player = self::createStub(Player::class);

        // For historical ($yr !== ""), non-array items should be skipped
        $result = PlayerRowTransformer::resolvePlayers($db, [$player], '2024');

        $this->assertSame([], $result);
    }

    public function testResolveWithStatsSkipsNonArrayForHistorical(): void
    {
        $db = self::createStub(\mysqli::class);

        $player = self::createStub(Player::class);

        // For historical ($yr !== ""), non-array items should be skipped
        $result = PlayerRowTransformer::resolveWithStats($db, [$player], '2024');

        $this->assertSame([], $result);
    }
}
