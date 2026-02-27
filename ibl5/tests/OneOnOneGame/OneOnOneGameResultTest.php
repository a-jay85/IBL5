<?php

declare(strict_types=1);

namespace Tests\OneOnOneGame;

use PHPUnit\Framework\TestCase;
use OneOnOneGame\OneOnOneGameResult;
use OneOnOneGame\OneOnOneGamePlayerStats;

/**
 * Tests for OneOnOneGameResult DTO
 */
final class OneOnOneGameResultTest extends TestCase
{
    public function testInitializesWithDefaultValues(): void
    {
        $result = new OneOnOneGameResult();

        $this->assertEquals(0, $result->gameId);
        $this->assertEquals('', $result->player1Name);
        $this->assertEquals('', $result->player2Name);
        $this->assertEquals(0, $result->player1Score);
        $this->assertEquals(0, $result->player2Score);
        $this->assertEquals('', $result->playByPlay);
        $this->assertEquals('', $result->owner);
        $this->assertInstanceOf(OneOnOneGamePlayerStats::class, $result->player1Stats);
        $this->assertInstanceOf(OneOnOneGamePlayerStats::class, $result->player2Stats);
    }

    public function testGetWinnerNameReturnsPlayer1WhenPlayer1Wins(): void
    {
        $result = new OneOnOneGameResult();
        $result->player1Name = 'Player One';
        $result->player2Name = 'Player Two';
        $result->player1Score = 21;
        $result->player2Score = 15;

        $this->assertEquals('Player One', $result->getWinnerName());
    }

    public function testGetWinnerNameReturnsPlayer2WhenPlayer2Wins(): void
    {
        $result = new OneOnOneGameResult();
        $result->player1Name = 'Player One';
        $result->player2Name = 'Player Two';
        $result->player1Score = 18;
        $result->player2Score = 21;

        $this->assertEquals('Player Two', $result->getWinnerName());
    }

    public function testGetLoserNameReturnsPlayer2WhenPlayer1Wins(): void
    {
        $result = new OneOnOneGameResult();
        $result->player1Name = 'Player One';
        $result->player2Name = 'Player Two';
        $result->player1Score = 21;
        $result->player2Score = 15;

        $this->assertEquals('Player Two', $result->getLoserName());
    }

    public function testGetLoserNameReturnsPlayer1WhenPlayer2Wins(): void
    {
        $result = new OneOnOneGameResult();
        $result->player1Name = 'Player One';
        $result->player2Name = 'Player Two';
        $result->player1Score = 18;
        $result->player2Score = 21;

        $this->assertEquals('Player One', $result->getLoserName());
    }

    public function testGetWinnerScoreReturnsHigherScore(): void
    {
        $result = new OneOnOneGameResult();
        $result->player1Score = 21;
        $result->player2Score = 18;

        $this->assertEquals(21, $result->getWinnerScore());

        $result->player1Score = 15;
        $result->player2Score = 21;

        $this->assertEquals(21, $result->getWinnerScore());
    }

    public function testGetLoserScoreReturnsLowerScore(): void
    {
        $result = new OneOnOneGameResult();
        $result->player1Score = 21;
        $result->player2Score = 18;

        $this->assertEquals(18, $result->getLoserScore());
    }

    public function testIsCloseGameReturnsTrueWhenMarginIs3OrLess(): void
    {
        $result = new OneOnOneGameResult();
        $result->player1Score = 21;
        $result->player2Score = 18;

        $this->assertTrue($result->isCloseGame());

        $result->player1Score = 21;
        $result->player2Score = 19;

        $this->assertTrue($result->isCloseGame());

        $result->player1Score = 21;
        $result->player2Score = 21;

        $this->assertTrue($result->isCloseGame());
    }

    public function testIsCloseGameReturnsFalseWhenMarginIsGreaterThan3(): void
    {
        $result = new OneOnOneGameResult();
        $result->player1Score = 21;
        $result->player2Score = 17;

        $this->assertFalse($result->isCloseGame());

        $result->player1Score = 21;
        $result->player2Score = 10;

        $this->assertFalse($result->isCloseGame());
    }

    public function testDidPlayer1WinReturnsTrueWhenPlayer1Wins(): void
    {
        $result = new OneOnOneGameResult();
        $result->player1Score = 21;
        $result->player2Score = 18;

        $this->assertTrue($result->didPlayer1Win());
    }

    public function testDidPlayer1WinReturnsFalseWhenPlayer2Wins(): void
    {
        $result = new OneOnOneGameResult();
        $result->player1Score = 18;
        $result->player2Score = 21;

        $this->assertFalse($result->didPlayer1Win());
    }
}
