<?php

declare(strict_types=1);

namespace Tests\Boxscore;

use Boxscore\Boxscore;
use Boxscore\BoxscoreRepository;
use Boxscore\GameLineWriter;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * @covers \Boxscore\GameLineWriter
 */
class GameLineWriterTest extends TestCase
{
    /**
     * An all-spaces 2000-byte record has no player names, so write() returns 0
     * and issues no repository inserts.
     */
    public function testWriteReturnsZeroAndIssuesNoRepositoryWriteForAllSpacesRecord(): void
    {
        $mockDb = new MockDatabase();

        $repository = self::createMock(BoxscoreRepository::class);
        $repository->expects(self::never())->method('insertTeamBoxscore');
        $repository->expects(self::never())->method('insertPlayerBoxscore');

        $writer = new GameLineWriter($mockDb, $repository);
        $line = str_repeat(' ', 2000);
        $boxscore = Boxscore::withGameInfoLine(str_repeat(' ', 58), 2026, 'Regular Season/Playoffs');

        $result = $writer->write($line, $boxscore);

        self::assertSame(0, $result);
    }
}
