<?php

declare(strict_types=1);

namespace BigBoard;

use BigBoard\Contracts\BigBoardViewInterface;
use Security\HtmlSanitizer;

/**
 * @see BigBoardViewInterface
 *
 * @phpstan-import-type BigBoardRow from \BigBoard\Contracts\BigBoardRepositoryInterface
 * @phpstan-import-type AddableProspect from \BigBoard\Contracts\BigBoardRepositoryInterface
 * @phpstan-import-type MockResultRow from \BigBoard\Contracts\MockDraftServiceInterface
 */
class BigBoardView implements BigBoardViewInterface
{
    /**
     * @see BigBoardViewInterface::renderBigBoardPage()
     *
     * @param list<BigBoardRow> $rows
     * @param list<AddableProspect> $addable
     */
    public function renderBigBoardPage(
        array $rows,
        array $addable,
        ?string $result,
        ?string $error,
        string $rawToken,
        bool $hasTeam = true
    ): string {
        ob_start();
        ?>
        <div class="big-board-page">
        <h2 class="ibl-title">My Big Board</h2>
        <?= \UI\AlertRenderer::fromCode($result, [
            'added'      => ['class' => 'ibl-alert--success', 'message' => 'Prospect added to your big board.'],
            'removed'    => ['class' => 'ibl-alert--success', 'message' => 'Prospect removed from your big board.'],
            'rank_saved' => ['class' => 'ibl-alert--success', 'message' => 'Rank updated.'],
            'note_saved' => ['class' => 'ibl-alert--success', 'message' => 'Scouting note saved.'],
            'duplicate'  => ['class' => 'ibl-alert--error', 'message' => 'That prospect is already on your big board.'],
            'no_team'    => ['class' => 'ibl-alert--error', 'message' => 'You must own a team to use the big board.'],
        ], $error) ?>
        <?php if (!$hasTeam): ?>
            <div class="ibl-alert ibl-alert--info">You must own a team to use the big board.</div>
        <?php else: ?>
            <p><a href="modules.php?name=BigBoard&amp;op=mock" class="ibl-btn ibl-btn--secondary">View Mock Draft &raquo;</a></p>
            <?php if ($rows === []): ?>
                <div class="ibl-alert ibl-alert--info">Your big board is empty.</div>
            <?php else: ?>
                <table class="ibl-data-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Prospect</th>
                            <th>Pos</th>
                            <th>Status</th>
                            <th>Note</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $entryId = (int) $row['id'];
                        $isDrafted = (int) $row['drafted'] === 1;
                        ?>
                        <tr>
                            <td>
                                <form method="post" action="modules.php?name=BigBoard&amp;op=setrank" class="ibl-form-group">
                                    <input type="hidden" name="_csrf_token" value="<?= HtmlSanitizer::e($rawToken) ?>">
                                    <input type="hidden" name="entry_id" value="<?= HtmlSanitizer::e($entryId) ?>">
                                    <input type="number" name="rank" value="<?= HtmlSanitizer::e((int) $row['rank']) ?>" class="ibl-input" aria-label="Rank" style="width:5rem">
                                    <button type="submit" class="ibl-btn ibl-btn--secondary">Save</button>
                                </form>
                            </td>
                            <td><?= HtmlSanitizer::e($row['name'] ?? '') ?></td>
                            <td><?= HtmlSanitizer::e($row['pos'] ?? '') ?></td>
                            <td><?= $isDrafted ? 'Drafted' : 'Available' ?></td>
                            <td>
                                <form method="post" action="modules.php?name=BigBoard&amp;op=setnote" class="ibl-form-group">
                                    <input type="hidden" name="_csrf_token" value="<?= HtmlSanitizer::e($rawToken) ?>">
                                    <input type="hidden" name="entry_id" value="<?= HtmlSanitizer::e($entryId) ?>">
                                    <textarea name="note" rows="2" class="ibl-input" aria-label="Scouting note"><?= HtmlSanitizer::e($row['note'] ?? '') ?></textarea>
                                    <button type="submit" class="ibl-btn ibl-btn--secondary">Save Note</button>
                                </form>
                            </td>
                            <td>
                                <form method="post" action="modules.php?name=BigBoard&amp;op=remove">
                                    <input type="hidden" name="_csrf_token" value="<?= HtmlSanitizer::e($rawToken) ?>">
                                    <input type="hidden" name="entry_id" value="<?= HtmlSanitizer::e($entryId) ?>">
                                    <button type="submit" class="ibl-btn ibl-btn--danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if ($addable !== []): ?>
                <div class="ibl-card">
                    <div class="ibl-card__header"><h3 class="ibl-card__title">Add a prospect</h3></div>
                    <div class="ibl-card__body">
                        <form method="post" action="modules.php?name=BigBoard&amp;op=add" class="ibl-form-container">
                            <input type="hidden" name="_csrf_token" value="<?= HtmlSanitizer::e($rawToken) ?>">
                            <div class="ibl-form-group">
                                <select name="prospect_id" class="ibl-select" aria-label="Select prospect" required>
                                    <option value="">Select prospect...</option>
                                    <?php foreach ($addable as $prospect): ?>
                                        <option value="<?= HtmlSanitizer::e((int) $prospect['id']) ?>"><?= HtmlSanitizer::e(($prospect['name'] ?? '') . ' (' . ($prospect['pos'] ?? '') . ')') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="ibl-form-group">
                                <input type="number" name="rank" value="0" class="ibl-input" aria-label="Rank" style="width:5rem">
                            </div>
                            <div class="ibl-form-group">
                                <input type="text" name="note" maxlength="255" class="ibl-input" placeholder="Scouting note (optional)" aria-label="Scouting note">
                            </div>
                            <button type="submit" class="ibl-btn ibl-btn--primary">Add to Big Board</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @see BigBoardViewInterface::renderMockDraftPage()
     *
     * @param list<MockResultRow> $picks
     */
    public function renderMockDraftPage(array $picks, bool $hasTeam = true): string
    {
        ob_start();
        ?>
        <div class="mock-draft-page">
        <h2 class="ibl-title">Mock Draft</h2>
        <p><a href="modules.php?name=BigBoard" class="ibl-btn ibl-btn--secondary">&laquo; Back to Big Board</a></p>
        <?php if (!$hasTeam): ?>
            <div class="ibl-alert ibl-alert--info">You must own a team to use the mock draft.</div>
        <?php elseif ($picks === []): ?>
            <div class="ibl-alert ibl-alert--info">Your team owns no picks in the current projected draft order.</div>
        <?php else: ?>
            <table class="ibl-data-table">
                <thead>
                    <tr>
                        <th>Round</th>
                        <th>Pick</th>
                        <th>Suggested Prospect</th>
                        <th>Pos</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($picks as $pick): ?>
                    <tr>
                        <td><?= HtmlSanitizer::e((int) $pick['round']) ?></td>
                        <td><?= HtmlSanitizer::e((int) $pick['pick']) ?></td>
                        <?php if ($pick['suggestion'] === null): ?>
                            <td colspan="3"><em>No prospects left on your board</em></td>
                        <?php else: ?>
                            <td><?= HtmlSanitizer::e($pick['suggestion']['name']) ?></td>
                            <td><?= HtmlSanitizer::e($pick['suggestion']['pos']) ?></td>
                            <td><?= HtmlSanitizer::e($pick['suggestion']['note']) ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
