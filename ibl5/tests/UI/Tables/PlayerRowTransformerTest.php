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
        $db = $this->createStub(\mysqli::class);

        $result = PlayerRowTransformer::resolveWithStats($db, [], '');

        $this->assertSame([], $result);
    }

    public function testResolvePlayersReturnsEmptyForEmptyInput(): void
    {
        $db = $this->createStub(\mysqli::class);

        $result = PlayerRowTransformer::resolvePlayers($db, [], '');

        $this->assertSame([], $result);
    }

    public function testResolvePlayersFiltersOutPipeNames(): void
    {
        $db = $this->createStub(\mysqli::class);

        $player = $this->createStub(Player::class);
        $player->name = '|Placeholder';

        $result = PlayerRowTransformer::resolvePlayers($db, [$player], '');

        $this->assertSame([], $result);
    }

    public function testResolvePlayersAcceptsPlayerInstances(): void
    {
        $db = $this->createStub(\mysqli::class);

        $player = $this->createStub(Player::class);
        $player->name = 'John Doe';

        $result = PlayerRowTransformer::resolvePlayers($db, [$player], '');

        $this->assertCount(1, $result);
        $this->assertSame($player, $result[0]);
    }

    public function testResolveWithStatsSkipsNonArrayNonPlayerForCurrentSeason(): void
    {
        $db = $this->createStub(\mysqli::class);

        // Pass an iterable with a non-array/non-Player element
        $result = PlayerRowTransformer::resolveWithStats($db, [new \stdClass()], '');

        $this->assertSame([], $result);
    }

    public function testResolvePlayersSkipsNonArrayForHistorical(): void
    {
        $db = $this->createStub(\mysqli::class);

        $player = $this->createStub(Player::class);

        // For historical ($yr !== ""), non-array items should be skipped
        $result = PlayerRowTransformer::resolvePlayers($db, [$player], '2024');

        $this->assertSame([], $result);
    }

    public function testResolveWithStatsSkipsNonArrayForHistorical(): void
    {
        $db = $this->createStub(\mysqli::class);

        $player = $this->createStub(Player::class);

        // For historical ($yr !== ""), non-array items should be skipped
        $result = PlayerRowTransformer::resolveWithStats($db, [$player], '2024');

        $this->assertSame([], $result);
    }
}
