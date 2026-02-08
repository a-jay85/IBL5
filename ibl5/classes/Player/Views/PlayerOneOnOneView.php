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
            /** @var array{gameid: int, winner: string, loser: string, winscore: int, lossscore: int, loser_pid: int|null} $game */
            $gameId = $game['gameid'];
            /** @var string $loser */
            $loser = HtmlSanitizer::safeHtmlOutput($game['loser']);
            $loserPid = $game['loser_pid'];
            $winScore = $game['winscore'];
            $lossScore = $game['lossscore'];

            // Create game link
            $gameLink = "modules.php?name=OneOnOneGame&amp;gameid={$gameId}";

            // Create opponent link if we have a player ID
            /** @var string $opponentLink */
            $opponentLink = $loserPid !== null
                ? "<a href=\"modules.php?name=Player&amp;pa=showpage&amp;pid={$loserPid}\" style=\"font-family: inherit; font-size: inherit;\">{$loser}</a>"
                : $loser;

            echo "* def. {$opponentLink}, {$winScore}-{$lossScore} (<a href=\"{$gameLink}\" style=\"font-family: inherit; font-size: inherit;\">Game #{$gameId}</a>)<br>";
        }

        // Show losses
        foreach ($losses as $game) {
            /** @var array{gameid: int, winner: string, loser: string, winscore: int, lossscore: int, winner_pid: int|null} $game */
            $gameId = $game['gameid'];
            /** @var string $winner */
            $winner = HtmlSanitizer::safeHtmlOutput($game['winner']);
            $winnerPid = $game['winner_pid'];
            $winScore = $game['winscore'];
            $lossScore = $game['lossscore'];

            // Create game link
            $gameLink = "modules.php?name=OneOnOneGame&amp;gameid={$gameId}";

            // Create opponent link if we have a player ID
            /** @var string $opponentLink */
            $opponentLink = $winnerPid !== null
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
        return (string) ob_get_clean();
    }
}
