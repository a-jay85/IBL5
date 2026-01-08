<?php

declare(strict_types=1);

namespace Player\Views;

use Player\PlayerRepository;
use Player\Contracts\PlayerOneOnOneViewInterface;
use Utilities\HtmlSanitizer;

/**
 * PlayerOneOnOneView - Renders one-on-one game results
 * 
 * Shows win/loss record and individual game results.
 * Uses PlayerRepository for all database access.
 * 
 * @see PlayerOneOnOneViewInterface
 */
class PlayerOneOnOneView implements PlayerOneOnOneViewInterface
{
    private PlayerRepository $repository;

    public function __construct(PlayerRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see PlayerViewInterface::render()
     */
    public function render(): string
    {
        return '';
    }

    /**
     * @see PlayerOneOnOneViewInterface::renderOneOnOneResults()
     */
    public function renderOneOnOneResults(string $playerName): string
    {
        // Normalize player name (handle URL encoding)
        $playerName = str_replace('%20', ' ', $playerName);
        
        $wins = $this->repository->getOneOnOneWins($playerName);
        $losses = $this->repository->getOneOnOneLosses($playerName);

        $winCount = count($wins);
        $lossCount = count($losses);

        ob_start();
        ?>
<table class="oneonone-table">
    <tr>
        <td class="player-table-header">ONE-ON-ONE RESULTS</td>
    </tr>
    <tr>
        <td>
        <?php
        // Show wins
        foreach ($wins as $game) {
            $gameId = (int)$game['gameid'];
            $loser = HtmlSanitizer::safeHtmlOutput($game['loser']);
            $winScore = (int)$game['winscore'];
            $lossScore = (int)$game['lossscore'];
            echo "* def. {$loser}, {$winScore}-{$lossScore} (# {$gameId})<br>";
        }
        
        // Show losses
        foreach ($losses as $game) {
            $gameId = (int)$game['gameid'];
            $winner = HtmlSanitizer::safeHtmlOutput($game['winner']);
            $winScore = (int)$game['winscore'];
            $lossScore = (int)$game['lossscore'];
            echo "* lost to {$winner}, {$lossScore}-{$winScore} (# {$gameId})<br>";
        }
        ?>
        <div class="text-center text-bold">Record: <?= $winCount ?> - <?= $lossCount ?></div><br>
        </td>
    </tr>
</table>
        <?php
        return ob_get_clean();
    }
}
