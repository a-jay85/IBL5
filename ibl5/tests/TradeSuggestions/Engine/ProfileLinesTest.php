<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TradeSuggestions\Engine\ProfileDelta;
use TradeSuggestions\Engine\ProfileLines;

final class ProfileLinesTest extends TestCase
{
    /**
     * @param array<string, array{before: int, after: int}> $dims
     * @param array<string, array{before: ?int, after: ?int}> $starters
     */
    private static function delta(array $dims = [], array $starters = [], int $offset = 0): ProfileDelta
    {
        return new ProfileDelta(1, $offset, $dims, $starters, ['before' => 10, 'after' => 10], ['before' => 0, 'after' => 0]);
    }

    public function testTwoPlaceMoveFormatted(): void
    {
        $lines = ProfileLines::forUser([0 => self::delta(['r_drb' => ['before' => 24, 'after' => 11]])]);

        self::assertSame(['Defensive rebounding 24th -> 11th'], $lines);
    }

    public function testOnePlaceMoveOmitted(): void
    {
        self::assertSame([], ProfileLines::forUser([0 => self::delta(['r_drb' => ['before' => 10, 'after' => 9]])]));
        self::assertSame(
            ['Defensive rebounding 10th -> 8th'],
            ProfileLines::forUser([0 => self::delta(['r_drb' => ['before' => 10, 'after' => 8]])]),
        );
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function ordinalProvider(): array
    {
        $cases = [];
        foreach ([1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th', 11 => '11th', 12 => '12th', 13 => '13th', 21 => '21st', 22 => '22nd', 23 => '23rd', 111 => '111th', 803 => '803rd'] as $n => $text) {
            $cases[$text] = [$n, $text];
        }

        return $cases;
    }

    #[DataProvider('ordinalProvider')]
    public function testOrdinalSuffixes(int $rank, string $expected): void
    {
        // Start far enough away that the move always clears the minimum.
        $lines = ProfileLines::forUser([0 => self::delta(['r_drb' => ['before' => $rank + 50, 'after' => $rank]])]);

        self::assertCount(1, $lines);
        self::assertStringEndsWith('-> ' . $expected, $lines[0]);
    }

    public function testMaxLinesCap(): void
    {
        $delta = self::delta(
            [
                'r_drb' => ['before' => 20, 'after' => 5],
                'r_orb' => ['before' => 20, 'after' => 6],
                'r_stl' => ['before' => 20, 'after' => 7],
            ],
            [
                'PG' => ['before' => 20, 'after' => 5],
                'SG' => ['before' => 20, 'after' => 5],
            ],
        );

        $lines = ProfileLines::forUser([0 => $delta]);

        self::assertCount(ProfileLines::MAX_LINES, $lines);
        self::assertSame('PG starter 20th -> 5th', $lines[3]);
    }

    public function testMaxDimensionLinesCap(): void
    {
        $delta = self::delta([
            'r_drb' => ['before' => 20, 'after' => 18],
            'r_orb' => ['before' => 20, 'after' => 10],
            'r_stl' => ['before' => 20, 'after' => 15],
            'r_blk' => ['before' => 20, 'after' => 5],
            'r_ast' => ['before' => 20, 'after' => 17],
        ]);

        self::assertSame(
            [
                'Blocks 20th -> 5th',
                'Offensive rebounding 20th -> 10th',
                'Steals 20th -> 15th',
            ],
            ProfileLines::forUser([0 => $delta]),
        );
    }

    public function testStarterLineFormat(): void
    {
        $lines = ProfileLines::forUser([0 => self::delta([], ['PG' => ['before' => 20, 'after' => 5]])]);

        self::assertSame(['PG starter 20th -> 5th'], $lines);
    }

    public function testNoOffsetZeroGivesNoLines(): void
    {
        self::assertSame([], ProfileLines::forUser([1 => self::delta(['r_drb' => ['before' => 24, 'after' => 11]], [], 1)]));
    }

    public function testEmptyDeltaGivesNoLines(): void
    {
        self::assertSame([], ProfileLines::forUser([0 => self::delta()]));
        self::assertSame([], ProfileLines::forUser([]));
    }

    public function testLabelFallbackForUnknownDimension(): void
    {
        $lines = ProfileLines::forUser([0 => self::delta(['mystery_rank' => ['before' => 20, 'after' => 5]])]);

        self::assertSame(['Mystery_rank 20th -> 5th'], $lines);
    }

    public function testNullStarterRankIsSkipped(): void
    {
        $lines = ProfileLines::forUser([0 => self::delta([], [
            'PG' => ['before' => null, 'after' => 5],
            'SG' => ['before' => 20, 'after' => null],
            'SF' => ['before' => 20, 'after' => 5],
        ])]);

        self::assertSame(['SF starter 20th -> 5th'], $lines);
    }

    public function testWorseningMovesAreShownToo(): void
    {
        $lines = ProfileLines::forUser([0 => self::delta(['r_drb' => ['before' => 8, 'after' => 15]])]);

        self::assertSame(['Defensive rebounding 8th -> 15th'], $lines);
    }

    public function testStarterMoveNeedsTwoPlaces(): void
    {
        $lines = ProfileLines::forUser([0 => self::delta([], [
            'PG' => ['before' => 10, 'after' => 9],
            'SG' => ['before' => 10, 'after' => 8],
            'SF' => ['before' => 8, 'after' => 9],
        ])]);

        self::assertSame(['SG starter 10th -> 8th'], $lines);
    }

    public function testStarterLinesFillUpToMaxLines(): void
    {
        $lines = ProfileLines::forUser([0 => self::delta([], [
            'PG' => ['before' => 20, 'after' => 5],
            'SG' => ['before' => 20, 'after' => 6],
            'SF' => ['before' => 20, 'after' => 7],
            'PF' => ['before' => 20, 'after' => 8],
            'C' => ['before' => 20, 'after' => 9],
        ])]);

        self::assertSame([
            'PG starter 20th -> 5th',
            'SG starter 20th -> 6th',
            'SF starter 20th -> 7th',
            'PF starter 20th -> 8th',
        ], $lines);
    }
}
