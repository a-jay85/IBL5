<?php

declare(strict_types=1);

namespace Watchlist;

use BasketballStats\StatsFormatter;
use Security\HtmlSanitizer;
use Watchlist\Contracts\WatchlistViewInterface;

/**
 * @see WatchlistViewInterface
 *
 * @phpstan-import-type WatchlistRow from \Watchlist\Contracts\WatchlistRepositoryInterface
 */
class WatchlistView implements WatchlistViewInterface
{
    /**
     * @see WatchlistViewInterface::renderWatchlistPage()
     *
     * @param list<WatchlistRow> $rows
     */
    public function renderWatchlistPage(
        array $rows,
        ?string $result,
        ?string $error,
        string $rawToken,
        bool $hasTeam = true
    ): string {
        ob_start();
        ?>
        <div class="watchlist-page">
        <h2 class="ibl-title">My Watchlist</h2>
        <?= \UI\AlertRenderer::fromCode($result, [
            'watched'    => ['class' => 'ibl-alert--success', 'message' => 'Player added to your watchlist.'],
            'unwatched'  => ['class' => 'ibl-alert--success', 'message' => 'Player removed from your watchlist.'],
            'note_saved' => ['class' => 'ibl-alert--success', 'message' => 'Scouting note saved.'],
        ], $error) ?>
        <?php if (!$hasTeam): ?>
            <div class="ibl-alert ibl-alert--info">You must own a team to use the watchlist.</div>
        <?php elseif ($rows === []): ?>
            <div class="ibl-alert ibl-alert--info">You aren't watching any players yet.</div>
        <?php else: ?>
            <table class="ibl-data-table">
                <thead>
                    <tr>
                        <th>Player</th>
                        <th>Pos</th>
                        <th>Team</th>
                        <th>PPG</th>
                        <th>RPG</th>
                        <th>APG</th>
                        <th>Note</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $pid = (int) $row['pid'];
                    $points = StatsFormatter::calculatePoints($row['stats_fgm'], $row['stats_ftm'], $row['stats_3gm']);
                    $rebounds = (int) ($row['stats_orb'] ?? 0) + (int) ($row['stats_drb'] ?? 0);
                    $ppg = StatsFormatter::formatPerGameAverage($points, $row['stats_gm']);
                    $rpg = StatsFormatter::formatPerGameAverage($rebounds, $row['stats_gm']);
                    $apg = StatsFormatter::formatPerGameAverage($row['stats_ast'], $row['stats_gm']);
                    ?>
                    <tr>
                        <td>
                            <a href="modules.php?name=Player&amp;pa=showpage&amp;pid=<?= HtmlSanitizer::e($pid) ?>">
                                <?= HtmlSanitizer::e($row['name'] ?? '') ?>
                            </a>
                        </td>
                        <td><?= HtmlSanitizer::e($row['pos'] ?? '') ?></td>
                        <td><?= HtmlSanitizer::e($row['team_name'] ?? '') ?></td>
                        <td><?= HtmlSanitizer::e($ppg) ?></td>
                        <td><?= HtmlSanitizer::e($rpg) ?></td>
                        <td><?= HtmlSanitizer::e($apg) ?></td>
                        <td>
                            <form method="post" action="modules.php?name=Watchlist&amp;op=savenote" class="ibl-form-group">
                                <input type="hidden" name="csrf_token" value="<?= HtmlSanitizer::e($rawToken) ?>">
                                <input type="hidden" name="pid" value="<?= HtmlSanitizer::e($pid) ?>">
                                <textarea name="note" rows="2" class="ibl-input" aria-label="Scouting note"><?= HtmlSanitizer::e($row['note'] ?? '') ?></textarea>
                                <button type="submit" class="ibl-btn ibl-btn--secondary">Save Note</button>
                            </form>
                        </td>
                        <td>
                            <form method="post" action="modules.php?name=Watchlist&amp;op=remove">
                                <input type="hidden" name="csrf_token" value="<?= HtmlSanitizer::e($rawToken) ?>">
                                <input type="hidden" name="pid" value="<?= HtmlSanitizer::e($pid) ?>">
                                <button type="submit" class="ibl-btn ibl-btn--danger">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @see WatchlistViewInterface::renderToggleButton()
     */
    public function renderToggleButton(int $pid, bool $isWatched, string $rawToken): string
    {
        $tokenEscaped = HtmlSanitizer::e($rawToken);
        $pidEscaped = HtmlSanitizer::e($pid);
        $button = $isWatched
            ? '<button type="submit" class="ibl-btn ibl-btn--primary">Unwatch</button>'
            : '<button type="submit" class="ibl-btn ibl-btn--primary">&#9733; Watch</button>';

        return '<form method="post" action="modules.php?name=Watchlist&amp;op=toggle" class="player-button">'
            . '<input type="hidden" name="csrf_token" value="' . $tokenEscaped . '">'
            . '<input type="hidden" name="pid" value="' . $pidEscaped . '">'
            . $button
            . '</form>';
    }
}
