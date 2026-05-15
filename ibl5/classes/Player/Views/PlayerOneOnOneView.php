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
            ?>
* <?php if ($game['loser_pid'] !== null): ?><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=<?= HtmlSanitizer::e($game['loser_pid']) ?>"><?= HtmlSanitizer::e($game['loser']) ?></a><?php else: ?><?= HtmlSanitizer::e($game['loser']) ?><?php endif; ?>, <?= (int) $game['winscore'] ?>-<?= (int) $game['lossscore'] ?> (<a href="modules.php?name=OneOnOneGame&amp;gameid=<?= (int) $game['gameid'] ?>">Game #<?= (int) $game['gameid'] ?></a>)<br>
        <?php
        }

        // Show losses
        foreach ($losses as $game) {
            /** @var array{gameid: int, winner: string, loser: string, winscore: int, lossscore: int, winner_pid: int|null} $game */
            ?>
* lost to <?php if ($game['winner_pid'] !== null): ?><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=<?= HtmlSanitizer::e($game['winner_pid']) ?>"><?= HtmlSanitizer::e($game['winner']) ?></a><?php else: ?><?= HtmlSanitizer::e($game['winner']) ?><?php endif; ?>, <?= (int) $game['lossscore'] ?>-<?= (int) $game['winscore'] ?> (<a href="modules.php?name=OneOnOneGame&amp;gameid=<?= (int) $game['gameid'] ?>">Game #<?= (int) $game['gameid'] ?></a>)<br>
        <?php
        }
        ?>
        <div class="text-center font-bold">Record: <?= HtmlSanitizer::e($winCount) ?> - <?= HtmlSanitizer::e($lossCount) ?></div><br>
        </td>
    </tr>
</table>
        <?php
        return (string) ob_get_clean();
    }
}
