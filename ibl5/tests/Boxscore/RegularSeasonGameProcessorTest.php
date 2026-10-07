<?php

declare(strict_types=1);

namespace Tests\Boxscore;

use Boxscore\Boxscore;
use Boxscore\GameLineWriter;
use Boxscore\GameUpsertResolver;
use Boxscore\RegularSeasonGameProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Boxscore\RegularSeasonGameProcessor
 */
class RegularSeasonGameProcessorTest extends TestCase
{
    // month 01 (+10 = 11), day 04 (+1 = 05), game 02 (+1 = 3), visitor 05 (+1 = 6), home 07 (+1 = 8)
    private const LINE_PREFIX = '0104020507';

    private function makeLine(): string
    {
        return self::LINE_PREFIX . str_repeat(' ', 1990);
    }

    public function testSkipReturnsZeroLinesAndNeverWrites(): void
    {
        $resolver = self::createStub(GameUpsertResolver::class);
        $resolver->method('resolve')->willReturn('skip');
        $writer = self::createMock(GameLineWriter::class);
        $writer->expects(self::never())->method('write');

        $processor = new RegularSeasonGameProcessor($resolver, $writer);
        $result = $processor->process($this->makeLine(), 2026, 'Regular Season/Playoffs', 'ibl');

        self::assertSame(['action' => 'skip', 'linesProcessed' => 0, 'messages' => []], $result);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function upsertActionProvider(): array
    {
        return [
            'insert' => ['insert', 3],
            'update' => ['update', 7],
        ];
    }

    #[DataProvider('upsertActionProvider')]
    public function testWriteActionAndLineCountPassThrough(string $action, int $lines): void
    {
        $line = $this->makeLine();
        $resolver = self::createStub(GameUpsertResolver::class);
        $resolver->method('resolve')->willReturn($action);
        $writer = self::createMock(GameLineWriter::class);
        $writer->expects(self::once())
            ->method('write')
            ->with(self::identicalTo($line), self::isInstanceOf(Boxscore::class))
            ->willReturn($lines);

        $processor = new RegularSeasonGameProcessor($resolver, $writer);
        $result = $processor->process($line, 2026, 'Regular Season/Playoffs', 'ibl');

        self::assertSame(['action' => $action, 'linesProcessed' => $lines, 'messages' => []], $result);
    }

    /**
     * @return array<string, array{string, string, string, int}>
     */
    public static function phaseLeagueProvider(): array
    {
        return [
            'regular season keeps November' => ['Regular Season/Playoffs', 'ibl', '2025-11-05', 2025],
            'preseason moves to September' => ['Preseason', 'ibl', '2025-09-05', 2025],
            'HEAT moves to October' => ['HEAT', 'ibl', '2025-10-05', 2025],
            'olympics league forces August of ending year' => ['Regular Season/Playoffs', 'olympics', '2026-08-05', 2026],
        ];
    }

    #[DataProvider('phaseLeagueProvider')]
    public function testSeasonPhaseAndLeagueDriveParsedGameDate(string $phase, string $league, string $expectedDate, int $expectedYear): void
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

        $processor = new RegularSeasonGameProcessor($resolver, $writer);
        $processor->process($this->makeLine(), 2026, $phase, $league);

        self::assertInstanceOf(Boxscore::class, $captured);
        self::assertSame($expectedDate, $captured->gameDate);
        self::assertSame($expectedYear, $captured->gameYear);
    }

    public function testGameInfoFieldsParsedFromLinePrefix(): void
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

        $processor = new RegularSeasonGameProcessor($resolver, $writer);
        $processor->process($this->makeLine(), 2026, 'Regular Season/Playoffs', 'ibl');

        self::assertInstanceOf(Boxscore::class, $captured);
        self::assertSame(3, $captured->game_of_that_day);
        self::assertSame(6, $captured->visitor_teamid);
        self::assertSame(8, $captured->home_teamid);
        self::assertSame('05', $captured->gameDay);
    }
}
