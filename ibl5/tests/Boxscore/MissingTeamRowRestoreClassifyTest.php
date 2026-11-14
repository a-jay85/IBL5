<?php

declare(strict_types=1);

namespace Tests\Boxscore;

use Boxscore\Legacy\MissingTeamRowRestore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @see MissingTeamRowRestore::classify()
 */
final class MissingTeamRowRestoreClassifyTest extends TestCase
{
    #[DataProvider('classifyProvider')]
    public function testClassify(int $teamRows, int $playerRows, string $expected): void
    {
        self::assertSame($expected, MissingTeamRowRestore::classify($teamRows, $playerRows));
    }

    /**
     * @return array<string, array{int, int, string}>
     */
    public static function classifyProvider(): array
    {
        return [
            'nothing at all is absent' => [0, 0, MissingTeamRowRestore::OUTCOME_ABSENT],
            'full pair is already paired' => [2, 24, MissingTeamRowRestore::OUTCOME_PAIRED],
            'pair without player rows is already paired' => [2, 0, MissingTeamRowRestore::OUTCOME_PAIRED],
            'one team row and 24 player rows restores' => [1, 24, MissingTeamRowRestore::OUTCOME_RESTORE],
            'one team row and 23 player rows aborts' => [1, 23, MissingTeamRowRestore::OUTCOME_ABORT],
            'one team row and 25 player rows aborts' => [1, 25, MissingTeamRowRestore::OUTCOME_ABORT],
            'player rows with no team row aborts' => [0, 24, MissingTeamRowRestore::OUTCOME_ABORT],
            'three team rows aborts' => [3, 24, MissingTeamRowRestore::OUTCOME_ABORT],
        ];
    }
}
