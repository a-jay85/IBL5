<?php

declare(strict_types=1);

namespace OneOnOneGame;

/**
 * OneOnOneGameResult - Complete result of a One-on-One game
 * 
 * Data transfer object containing all information about a completed
 * One-on-One game, including scores, stats, and play-by-play.
 */
class OneOnOneGameResult
{
    public int $gameId = 0;
    public string $player1Name = '';
    public string $player2Name = '';
    public int $player1Score = 0;
    public int $player2Score = 0;
    public OneOnOneGamePlayerStats $player1Stats;
    public OneOnOneGamePlayerStats $player2Stats;
    public string $playByPlay = '';
    public string $owner = '';
    public string $coinFlipResult = '';

    public function __construct()
    {
        $this->player1Stats = new OneOnOneGamePlayerStats();
        $this->player2Stats = new OneOnOneGamePlayerStats();
    }

    /**
     * Get the winner's name
     * 
     * @return string The name of the winning player
     */
    public function getWinnerName(): string
    {
        return $this->player1Score > $this->player2Score 
            ? $this->player1Name 
            : $this->player2Name;
    }

    /**
     * Get the loser's name
     * 
     * @return string The name of the losing player
     */
    public function getLoserName(): string
    {
        return $this->player1Score > $this->player2Score 
            ? $this->player2Name 
            : $this->player1Name;
    }

    /**
     * Get the winner's score
     * 
     * @return int The winning score
     */
    public function getWinnerScore(): int
    {
        return max($this->player1Score, $this->player2Score);
    }

    /**
     * Get the loser's score
     * 
     * @return int The losing score
     */
    public function getLoserScore(): int
    {
        return min($this->player1Score, $this->player2Score);
    }

    /**
     * Check if the game was close (decided by 3 points or less)
     * 
     * @return bool True if margin was 3 or less
     */
    public function isCloseGame(): bool
    {
        return abs($this->player1Score - $this->player2Score) <= 3;
    }

    /**
     * Check if player 1 won
     * 
     * @return bool True if player 1 won
     */
    public function didPlayer1Win(): bool
    {
        return $this->player1Score > $this->player2Score;
    }
}
