<?php

declare(strict_types=1);

namespace Tests\Boxscore;

use Boxscore\AllStarGameProcessor;
use Boxscore\BoxscoreProcessor;
use Boxscore\BoxscoreRepository;
use Boxscore\GameLineWriter;
use Boxscore\GameUpsertResolver;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Boxscore\AllStarGameProcessor
 */
class AllStarGameProcessorTest extends TestCase
{
    /**
     * When findAllStarTeamNames() returns null (Outcome C), the processor must call
     * write() with DEFAULT_AWAY_NAME / DEFAULT_HOME_NAME constants as name overrides.
     * This is the mutation-killer for the null boundary in the moved code.
     */
    public function testOutcomeCWritesWithDefaultNamesWhenFindAllStarTeamNamesReturnsNull(): void
    {
        // findAllStarTeamNames() returns null → Outcome C
        $repository = self::createStub(BoxscoreRepository::class);
        $repository->method('findAllStarTeamNames')->willReturn(null);

        // resolver returns 'insert' so the write path is taken
        $resolver = self::createStub(GameUpsertResolver::class);
        $resolver->method('resolve')->willReturn('insert');

        // writer must be called exactly once with DEFAULT_AWAY_NAME / DEFAULT_HOME_NAME
        $writer = self::createMock(GameLineWriter::class);
        $writer->expects(self::once())
            ->method('write')
            ->with(
                self::anything(),
                self::anything(),
                BoxscoreProcessor::DEFAULT_AWAY_NAME,
                BoxscoreProcessor::DEFAULT_HOME_NAME,
            )
            ->willReturn(2);

        $processor = new AllStarGameProcessor($resolver, $writer, $repository);

        $line = str_repeat(' ', 2000);
        $result = $processor->process($line, 2026, 'Regular Season/Playoffs', 'ibl');

        self::assertSame('insert', $result['action']);
        self::assertSame(2, $result['linesProcessed']);
        self::assertContains('All-Star Game: inserted (2 lines).', $result['messages']);
    }
}
