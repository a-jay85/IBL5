<?php

declare(strict_types=1);

namespace Tests\SimRecap;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimRecap\SimRecapContextRepository;

final class SimRecapDoubleTagTest extends TestCase
{
    /**
     * @param array{pts:int,reb:int,ast:int,stl:int,blk:int} $stats
     */
    #[DataProvider('lineProvider')]
    public function testDoubleTagCountsStatsAtTenOrMore(array $stats, ?string $expected): void
    {
        $line = ['pid' => 1, 'name' => 'X', 'min' => 30, 'fgm' => 0, 'fga' => 0, 'tpm' => 0, 'tpa' => 0] + $stats;

        self::assertSame($expected, SimRecapContextRepository::doubleTag($line));
    }

    /**
     * @return array<string, array{array{pts:int,reb:int,ast:int,stl:int,blk:int}, string|null}>
     */
    public static function lineProvider(): array
    {
        return [
            'nothing' => [['pts' => 33, 'reb' => 4, 'ast' => 9, 'stl' => 1, 'blk' => 0], null],
            'points and assists' => [['pts' => 33, 'reb' => 4, 'ast' => 10, 'stl' => 1, 'blk' => 0], 'double-double'],
            'chris paul 16-11-15' => [['pts' => 16, 'reb' => 11, 'ast' => 15, 'stl' => 0, 'blk' => 0], 'triple-double'],
            'exactly ten counts' => [['pts' => 10, 'reb' => 10, 'ast' => 10, 'stl' => 0, 'blk' => 0], 'triple-double'],
            'steals and blocks count' => [['pts' => 9, 'reb' => 10, 'ast' => 2, 'stl' => 0, 'blk' => 10], 'double-double'],
            'four categories' => [['pts' => 20, 'reb' => 12, 'ast' => 11, 'stl' => 10, 'blk' => 0], 'quadruple-double'],
            'all five' => [['pts' => 10, 'reb' => 10, 'ast' => 10, 'stl' => 10, 'blk' => 10], 'quintuple-double'],
        ];
    }
}
