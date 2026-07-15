<?php

declare(strict_types=1);

namespace Tests\GameBoxscore;

use GameBoxscore\Contracts\GameBoxscoreRepositoryInterface;
use GameBoxscore\GameBoxscoreService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GameBoxscore\GameBoxscoreService
 */
class GameBoxscoreServiceTest extends TestCase
{
    public function testValidGameSplitsPlayersAndComputesTotals(): void
    {
        $repo = self::createStub(GameBoxscoreRepositoryInterface::class);
        $repo->method('getGameInfo')->willReturn([
            'awayTeamId' => 1,
            'homeTeamId' => 2,
            'awayScore' => 105,
            'homeScore' => 98,
            'awayTeamName' => 'Metros',
            'awayTeamCity' => 'New York',
            'awayColor1' => '003DA5',
            'awayColor2' => 'FF5733',
            'homeTeamName' => 'Stars',
            'homeTeamCity' => 'Los Angeles',
            'homeColor1' => '552583',
            'homeColor2' => 'FDB927',
        ]);
        $repo->method('getPlayerRows')->willReturn([
            $this->makePlayerRow(['pid' => 1, 'isAwayPlayer' => 1, 'pts' => 30]),
            $this->makePlayerRow(['pid' => 2, 'isAwayPlayer' => 1, 'pts' => 20]),
            $this->makePlayerRow(['pid' => 3, 'isAwayPlayer' => 0, 'pts' => 25]),
            $this->makePlayerRow(['pid' => 4, 'isAwayPlayer' => 0, 'pts' => 20]),
            $this->makePlayerRow(['pid' => 5, 'isAwayPlayer' => 0, 'pts' => 18]),
        ]);
        $service = new GameBoxscoreService($repo);

        $result = $service->getBoxscore('2026-02-20', 1);

        $this->assertTrue($result['found']);
        $this->assertCount(2, $result['awayPlayers']);
        $this->assertCount(3, $result['homePlayers']);
        $this->assertSame(105, $result['awayTeam']['score']);
        $this->assertSame(98, $result['homeTeam']['score']);
        $this->assertSame(50, $result['awayTotals']['pts']);
        $this->assertSame(63, $result['homeTotals']['pts']);
    }

    public function testMalformedDateReturnsNotFoundWithoutQuery(): void
    {
        foreach (['2026/02/20', '26-2-2', '', 123, null] as $rawDate) {
            $repo = $this->createMock(GameBoxscoreRepositoryInterface::class);
            $repo->expects($this->never())->method('getGameInfo');
            $service = new GameBoxscoreService($repo);

            $result = $service->getBoxscore($rawDate, 1);

            $this->assertFalse($result['found']);
        }
    }

    public function testImpossibleCalendarDateReturnsNotFound(): void
    {
        foreach (['2026-02-30', '2026-13-01', '2026-00-10'] as $rawDate) {
            $repo = $this->createMock(GameBoxscoreRepositoryInterface::class);
            $repo->expects($this->never())->method('getGameInfo');
            $service = new GameBoxscoreService($repo);

            $result = $service->getBoxscore($rawDate, 1);

            $this->assertFalse($result['found']);
        }
    }

    public function testInvalidGameNumberReturnsNotFound(): void
    {
        foreach ([0, -1, '0', 'abc', '1.5', '', null] as $rawGame) {
            $repo = $this->createMock(GameBoxscoreRepositoryInterface::class);
            $repo->expects($this->never())->method('getGameInfo');
            $service = new GameBoxscoreService($repo);

            $result = $service->getBoxscore('2026-02-20', $rawGame);

            $this->assertFalse($result['found']);
        }
    }

    public function testUnknownGameReturnsNotFound(): void
    {
        $repo = $this->createMock(GameBoxscoreRepositoryInterface::class);
        $repo->method('getGameInfo')->willReturn(null);
        $repo->expects($this->never())->method('getPlayerRows');
        $service = new GameBoxscoreService($repo);

        $result = $service->getBoxscore('2026-02-20', 1);

        $this->assertFalse($result['found']);
    }

    public function testValidGameWithNoPlayersIsFoundButEmpty(): void
    {
        $repo = self::createStub(GameBoxscoreRepositoryInterface::class);
        $repo->method('getGameInfo')->willReturn([
            'awayTeamId' => 1,
            'homeTeamId' => 2,
            'awayScore' => 105,
            'homeScore' => 98,
            'awayTeamName' => 'Metros',
            'awayTeamCity' => 'New York',
            'awayColor1' => '003DA5',
            'awayColor2' => 'FF5733',
            'homeTeamName' => 'Stars',
            'homeTeamCity' => 'Los Angeles',
            'homeColor1' => '552583',
            'homeColor2' => 'FDB927',
        ]);
        $repo->method('getPlayerRows')->willReturn([]);
        $service = new GameBoxscoreService($repo);

        $result = $service->getBoxscore('2026-02-20', 1);

        $this->assertTrue($result['found']);
        $this->assertSame([], $result['awayPlayers']);
        $this->assertSame([], $result['homePlayers']);
        $this->assertSame(0, $result['awayTotals']['pts']);
        $this->assertSame(0, $result['homeTotals']['pts']);
        $this->assertSame(105, $result['awayTeam']['score']);
    }

    public function testAppliesPosNameAndColorDefaults(): void
    {
        $repo = self::createStub(GameBoxscoreRepositoryInterface::class);
        $repo->method('getGameInfo')->willReturn([
            'awayTeamId' => 1,
            'homeTeamId' => 2,
            'awayScore' => 105,
            'homeScore' => 98,
            'awayTeamName' => 'Metros',
            'awayTeamCity' => 'New York',
            'awayColor1' => null,
            'awayColor2' => null,
            'homeTeamName' => 'Stars',
            'homeTeamCity' => 'Los Angeles',
            'homeColor1' => '552583',
            'homeColor2' => 'FDB927',
        ]);
        $repo->method('getPlayerRows')->willReturn([
            $this->makePlayerRow(['pid' => 1, 'isAwayPlayer' => 1, 'pos' => '', 'name' => '']),
        ]);
        $service = new GameBoxscoreService($repo);

        $result = $service->getBoxscore('2026-02-20', 1);

        $this->assertSame('N/A', $result['awayPlayers'][0]['pos']);
        $this->assertSame('Unknown', $result['awayPlayers'][0]['name']);
        $this->assertSame('FFFFFF', $result['awayTeam']['color1']);
        $this->assertSame('000000', $result['awayTeam']['color2']);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, int|float|string|null>
     */
    private function makePlayerRow(array $overrides = []): array
    {
        $defaults = [
            'name' => 'Test Player',
            'pos' => 'PG',
            'pid' => 1,
            'isAwayPlayer' => 1,
            'min' => 30,
            'fgm' => 5,
            'fga' => 12,
            'ftm' => 2,
            'fta' => 3,
            'tpm' => 1,
            'tpa' => 4,
            'orb' => 1,
            'reb' => 5,
            'ast' => 3,
            'stl' => 1,
            'blk' => 0,
            'tov' => 1,
            'pf' => 2,
            'pts' => 13,
        ];

        /** @var array<string, int|float|string|null> */
        return array_merge($defaults, $overrides);
    }
}
