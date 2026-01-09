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
<table class="sortable player-table">
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
            $loserPid = isset($game['loser_pid']) ? (int)$game['loser_pid'] : null;
            $winScore = (int)$game['winscore'];
            $lossScore = (int)$game['lossscore'];
            
            // Create game link
            $gameLink = "modules.php?name=One-on-One&amp;gameid={$gameId}";
            
            // Create opponent link if we have a player ID
            $opponentLink = $loserPid 
                ? "<a href=\"modules.php?name=Player&amp;pa=showpage&amp;pid={$loserPid}\" style=\"font-family: inherit; font-size: inherit;\">{$loser}</a>"
                : $loser;
            
            echo "* def. {$opponentLink}, {$winScore}-{$lossScore} (<a href=\"{$gameLink}\" style=\"font-family: inherit; font-size: inherit;\">Game #{$gameId}</a>)<br>";
        }
        
        // Show losses
        foreach ($losses as $game) {
            $gameId = (int)$game['gameid'];
            $winner = HtmlSanitizer::safeHtmlOutput($game['winner']);
            $winnerPid = isset($game['winner_pid']) ? (int)$game['winner_pid'] : null;
            $winScore = (int)$game['winscore'];
            $lossScore = (int)$game['lossscore'];
            
            // Create game link
            $gameLink = "modules.php?name=One-on-One&amp;gameid={$gameId}";
            
            // Create opponent link if we have a player ID
            $opponentLink = $winnerPid 
                ? "<a href=\"modules.php?name=Player&amp;pa=showpage&amp;pid={$winnerPid}\" style=\"font-family: inherit; font-size: inherit;\">{$winner}</a>"
                : $winner;
            
            echo "* lost to {$opponentLink}, {$lossScore}-{$winScore} (<a href=\"{$gameLink}\" style=\"font-family: inherit; font-size: inherit;\">Game #{$gameId}</a>)<br>";
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
