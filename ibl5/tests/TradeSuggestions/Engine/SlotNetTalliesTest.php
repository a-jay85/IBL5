<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TradeSuggestions\Engine\SlotNetTallies;

final class SlotNetTalliesTest extends TestCase
{
    /**
     * @return array<string, array{array<mixed>}>
     */
    public static function malformedGroupProvider(): array
    {
        return [
            'int slot key with an array of nets' => [[0 => []]],
            'string slot with a scalar instead of nets' => [['PG' => 5]],
            'string pid with an int net' => [['PG' => ['a' => 1]]],
            'int pid with a string net' => [['PG' => [1 => 'x']]],
        ];
    }

    /**
     * @param array<mixed> $pool
     */
    #[DataProvider('malformedGroupProvider')]
    public function testFromArrayRejectsAMalformedGroup(array $pool): void
    {
        $this->expectException(\UnexpectedValueException::class);

        SlotNetTallies::fromArray(['offset' => 0, 'pool' => $pool, 'starters' => [], 'bench' => []]);
    }

    public function testFromArrayRoundTripsWellFormedGroups(): void
    {
        $data = ['offset' => 2, 'pool' => ['PG' => [7 => 3, 9 => -3]], 'starters' => ['C' => [4 => 0]], 'bench' => []];

        self::assertSame($data, SlotNetTallies::fromArray($data)->toArray());
    }
}
