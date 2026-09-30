<?php

declare(strict_types=1);

namespace Tests\Boxscore;

use Boxscore\Boxscore;
use Boxscore\GameLineWriter;
use Boxscore\GameUpsertResolver;
use Boxscore\RisingStarsGameProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Boxscore\RisingStarsGameProcessor
 */
class RisingStarsGameProcessorTest extends TestCase
{
    // month 01 (+10 = 11), day 04 (+1 = 05), game 02 (+1 = 3), visitor 05 (+1 = 6), home 07 (+1 = 8)
    private const LINE_PREFIX = '0104020507';

    private function makeLine(): string
    {
        return self::LINE_PREFIX . str_repeat(' ', 1990);
    }

    public function testSkipReturnsSkipMessageAndNeverWrites(): void
    {
        $resolver = self::createStub(GameUpsertResolver::class);
        $resolver->method('resolve')->willReturn('skip');
        $writer = self::createMock(GameLineWriter::class);
        $writer->expects(self::never())->method('write');

        $processor = new RisingStarsGameProcessor($resolver, $writer);
        $result = $processor->process($this->makeLine(), 2026, 'Regular Season/Playoffs', 'ibl');

        self::assertSame(
            ['action' => 'skip', 'linesProcessed' => 0, 'messages' => ['Rising Stars Game: already exists, skipped.']],
            $result
        );
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function writeOutcomeProvider(): array
    {
        return [
            'insert' => ['insert', 4, 'Rising Stars Game: inserted (4 lines).'],
            'update' => ['update', 6, 'Rising Stars Game: updated (6 lines).'],
            'insert single line boundary' => ['insert', 1, 'Rising Stars Game: inserted (1 lines).'],
        ];
    }

    #[DataProvider('writeOutcomeProvider')]
    public function testWriteReportsActionMessage(string $action, int $lines, string $expectedMessage): void
    {
        $line = $this->makeLine();
        $resolver = self::createStub(GameUpsertResolver::class);
        $resolver->method('resolve')->willReturn($action);
        $writer = self::createMock(GameLineWriter::class);
        $writer->expects(self::once())
            ->method('write')
            ->with(self::identicalTo($line), self::isInstanceOf(Boxscore::class), 'Rookies', 'Sophomores')
            ->willReturn($lines);

        $processor = new RisingStarsGameProcessor($resolver, $writer);
        $result = $processor->process($line, 2026, 'Regular Season/Playoffs', 'ibl');

        self::assertSame(['action' => $action, 'linesProcessed' => $lines, 'messages' => [$expectedMessage]], $result);
    }

    public function testZeroLinesWrittenProducesNoMessage(): void
    {
        $resolver = self::createStub(GameUpsertResolver::class);
        $resolver->method('resolve')->willReturn('insert');
        $writer = self::createMock(GameLineWriter::class);
        $writer->expects(self::once())->method('write')->willReturn(0);

        $processor = new RisingStarsGameProcessor($resolver, $writer);
        $result = $processor->process($this->makeLine(), 2026, 'Regular Season/Playoffs', 'ibl');

        self::assertSame(['action' => 'insert', 'linesProcessed' => 0, 'messages' => []], $result);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function ignoredPhaseLeagueProvider(): array
    {
        return [
            'regular season ibl' => ['Regular Season/Playoffs', 'ibl'],
            'preseason ibl' => ['Preseason', 'ibl'],
            'HEAT ibl' => ['HEAT', 'ibl'],
            'regular season olympics' => ['Regular Season/Playoffs', 'olympics'],
        ];
    }

    #[DataProvider('ignoredPhaseLeagueProvider')]
    public function testGameContextPinnedToRisingStarsSlotRegardlessOfPhaseAndLeague(string $phase, string $league): void
    {
        $captured = null;
        $resolver = self::createMock(GameUpsertResolver::class);
        $resolver->expects(self::once())
            ->method('resolve')
            ->willReturnCallback(static function (Boxscore $b) use (&$captured): string {
                $captured = $b;

                return 'skip';
            });
        $writer = self::createStub(GameLineWriter::class);

        $processor = new RisingStarsGameProcessor($resolver, $writer);
        $processor->process($this->makeLine(), 2026, $phase, $league);

        self::assertInstanceOf(Boxscore::class, $captured);
        self::assertSame('2026-02-02', $captured->gameDate);
        self::assertSame(40, $captured->visitor_teamid);
        self::assertSame(41, $captured->home_teamid);
        self::assertSame(1, $captured->game_of_that_day);
        // overrideGameContext leaves month/year alone, so they show what the parser saw
        self::assertSame('11', $captured->gameMonth);
        self::assertSame(2025, $captured->gameYear);
    }
}
