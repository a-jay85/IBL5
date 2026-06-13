<?php

declare(strict_types=1);

namespace TradeBlock;

use Team\Team;
use TradeBlock\Contracts\TradeBlockViewInterface;

/**
 * @see TradeBlockViewInterface
 *
 * @phpstan-import-type BrowseData from \TradeBlock\Contracts\TradeBlockServiceInterface
 */
class TradeBlockView implements TradeBlockViewInterface
{
    /**
     * @see TradeBlockViewInterface::renderBrowse()
     *
     * @param BrowseData $data
     */
    public function renderBrowse(array $data): string
    {
        $teams = $data['teams'];

        if ($teams === []) {
            return $this->renderEmptyBrowse();
        }

        ob_start();
        ?>
        <div class="trade-block-page">
            <h2 class="ibl-title">Trade Block</h2>
            <p class="text-center">
                <a href="modules.php?name=TradeBlock&amp;op=edit">Edit your team's trade block &raquo;</a>
            </p>
            <?php foreach ($teams as $team): ?>
            <div class="ibl-card">
                <div class="ibl-card__header">
                    <h2 class="ibl-card__title">
                        <?= \Security\HtmlSanitizer::e($team['team_city']) ?> <?= \Security\HtmlSanitizer::e($team['team_name']) ?>
                    </h2>
                </div>
                <div class="ibl-card__body">
                    <?php if ($team['seekingNote'] !== ''): ?>
                    <p class="trade-block-seeking"><strong>Seeking:</strong> <?= \Security\HtmlSanitizer::e($team['seekingNote']) ?></p>
                    <?php endif; ?>
                    <table class="ibl-data-table">
                        <thead>
                            <tr><th>Available Player</th><th>Note</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($team['players'] as $player): ?>
                            <tr>
                                <td><?= \Security\HtmlSanitizer::e($player['name']) ?></td>
                                <td><?= \Security\HtmlSanitizer::e($player['note']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function renderEmptyBrowse(): string
    {
        return '<div class="trade-block-page">'
            . '<h2 class="ibl-title">Trade Block</h2>'
            . '<p class="text-center"><a href="modules.php?name=TradeBlock&amp;op=edit">Edit your team\'s trade block &raquo;</a></p>'
            . '<p>No players are currently on the trade block.</p>'
            . '</div>';
    }

    /**
     * @see TradeBlockViewInterface::renderEditForm()
     *
     * @param list<array{pid: int, name: string, ...<string, mixed>}> $roster
     * @param array<int, string> $blockPids
     */
    public function renderEditForm(
        Team $team,
        array $roster,
        array $blockPids,
        string $seekingNote,
        ?string $result = null,
        ?string $error = null
    ): string {
        ob_start();
        ?>
        <div class="trade-block-page">
            <h2 class="ibl-title">My Trade Block</h2>
            <?= \UI\AlertRenderer::fromCode($result, [
                'block_updated' => ['class' => 'ibl-alert--success', 'message' => 'Your trade block has been updated.'],
            ], $error) ?>
            <form name="Trade_Block" method="post" action="" class="ibl-form-container">
                <?= \Security\CsrfGuard::generateToken('tradeblock') ?>
                <input type="hidden" name="op" value="edit">
                <input type="hidden" name="Action" value="save">
                <div class="text-center">
                    <img src="images/logo/<?= \Security\HtmlSanitizer::e($team->teamid) ?>.jpg" alt="Team Logo" class="team-logo-banner">
                    <div class="ibl-card">
                        <div class="ibl-card__header">
                            <h2 class="ibl-card__title"><?= \Security\HtmlSanitizer::e($team->city) ?> <?= \Security\HtmlSanitizer::e($team->name) ?></h2>
                        </div>
                        <div class="ibl-card__body">
                            <?php if ($roster === []): ?>
                            <p>You have no rostered players to make available.</p>
                            <?php else: ?>
                            <table class="ibl-data-table">
                                <thead>
                                    <tr><th>On Block</th><th>Player</th><th>Note</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($roster as $player): ?>
                                    <?php
                                    $pid = (int) ($player['pid'] ?? 0);
                                    $name = is_scalar($player['name'] ?? null) ? (string) $player['name'] : '';
                                    $checked = array_key_exists($pid, $blockPids);
                                    $note = $blockPids[$pid] ?? '';
                                    ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="on_block[]" value="<?= \Security\HtmlSanitizer::e($pid) ?>"<?= $checked ? ' checked' : '' ?>>
                                        </td>
                                        <td><?= \Security\HtmlSanitizer::e($name) ?></td>
                                        <td>
                                            <input type="text" name="note[<?= \Security\HtmlSanitizer::e($pid) ?>]" value="<?= \Security\HtmlSanitizer::e($note) ?>" maxlength="255" class="ibl-input">
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                            <div class="ibl-form-group">
                                <label for="seeking_note">What are you seeking?</label>
                                <textarea name="seeking_note" id="seeking_note" maxlength="255" class="ibl-input" rows="3"><?= \Security\HtmlSanitizer::e($seekingNote) ?></textarea>
                            </div>
                            <button type="submit" class="ibl-btn ibl-btn--primary ibl-btn--block">Save Trade Block</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
