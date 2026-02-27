<?php

declare(strict_types=1);

namespace Boxscore;

use Utilities\HtmlSanitizer;

/**
 * BoxscoreView - Renders parse results and All-Star rename UI for scoParser
 *
 * Handles all HTML rendering for the scoParser page.
 *
 * @see BoxscoreProcessor For the processing logic
 */
class BoxscoreView
{
    /**
     * Render parse results log
     *
     * @param array{success: bool, gamesInserted: int, gamesUpdated: int, gamesSkipped: int, linesProcessed: int, messages: list<string>, error?: string} $result
     * @return string HTML parse log
     */
    public function renderParseLog(array $result): string
    {
        /** @var int $gamesInserted */
        $gamesInserted = (int) $result['gamesInserted'];
        /** @var int $gamesUpdated */
        $gamesUpdated = (int) $result['gamesUpdated'];
        /** @var int $gamesSkipped */
        $gamesSkipped = (int) $result['gamesSkipped'];
        /** @var int $linesProcessed */
        $linesProcessed = (int) $result['linesProcessed'];

        ob_start();
        ?>
<div class="ibl-card sco-parse-result">
    <div class="ibl-card__header">
        <h2 class="ibl-card__title">Parse Results</h2>
    </div>
    <div class="ibl-card__body">
        <div class="sco-summary">
            <span class="ibl-badge ibl-badge--success"><?= $gamesInserted ?> Inserted</span>
            <span class="ibl-badge ibl-badge--info"><?= $gamesUpdated ?> Updated</span>
            <span class="ibl-badge ibl-badge--warning"><?= $gamesSkipped ?> Skipped</span>
            <span class="sco-summary__lines"><?= $linesProcessed ?> lines processed</span>
        </div>
        <?php if ($result['messages'] !== []): ?>
        <div class="sco-log">
            <?php foreach ($result['messages'] as $message): ?>
            <?php $safeMessage = HtmlSanitizer::safeHtmlOutput($message); ?>
            <p class="sco-log__message"><?= $safeMessage ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (isset($result['error']) && $result['error'] !== ''): ?>
        <?php $safeError = HtmlSanitizer::safeHtmlOutput($result['error']); ?>
        <div class="ibl-alert ibl-alert--error"><strong>Error:</strong> <?= $safeError ?></div>
        <?php endif; ?>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render All-Star game processing results
     *
     * @param array{success: bool, messages: list<string>, skipped?: string} $result
     * @return string HTML output
     */
    public function renderAllStarLog(array $result): string
    {
        ob_start();

        if ($result['messages'] !== []) {
            ?>
<div class="ibl-card sco-parse-result">
    <div class="ibl-card__header">
        <h2 class="ibl-card__title">All-Star Weekend</h2>
    </div>
    <div class="ibl-card__body">
        <div class="sco-log">
            <?php foreach ($result['messages'] as $message): ?>
            <?php $safeMessage = HtmlSanitizer::safeHtmlOutput($message); ?>
            <p class="sco-log__message"><?= $safeMessage ?></p>
            <?php endforeach; ?>
        </div>
    </div>
</div>
            <?php
        }

        return (string) ob_get_clean();
    }

    /**
     * Render the async rename UI for All-Star teams with default placeholder names
     *
     * @param list<array{id: int, date: string, name: string, seasonYear: int, teamLabel: string, players: list<string>}> $pendingRenames
     * @return string HTML output
     */
    public function renderAllStarRenameUI(array $pendingRenames): string
    {
        if ($pendingRenames === []) {
            return '';
        }

        ob_start();
        ?>
<div class="ibl-card sco-parse-result">
    <div class="ibl-card__header">
        <h2 class="ibl-card__title">All-Star Team Names</h2>
        <div class="ibl-card__subtitle">These All-Star teams need custom names</div>
    </div>
    <div class="ibl-card__body">
        <?php foreach ($pendingRenames as $entry): ?>
        <div class="all-star-rename" data-record-id="<?= (int) $entry['id'] ?>">
            <h3 class="all-star-rename__heading">
                <?= (int) $entry['seasonYear'] ?> All-Star Game &mdash;
                <?php $safeLabel = HtmlSanitizer::safeHtmlOutput($entry['teamLabel']); ?>
                <?= $safeLabel ?>
            </h3>
            <div class="all-star-rename__players">
                <?php foreach ($entry['players'] as $player): ?>
                <?php $safeName = HtmlSanitizer::safeHtmlOutput($player); ?>
                <span class="all-star-rename__chip"><?= $safeName ?></span>
                <?php endforeach; ?>
            </div>
            <div class="all-star-rename__form">
                <input type="text" class="all-star-rename__input" maxlength="16" placeholder="Team name&hellip;" />
                <button type="button" class="ibl-btn ibl-btn--primary all-star-rename__btn">Rename</button>
            </div>
            <div class="all-star-rename__status"></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<script>
(function () {
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    var cards = document.querySelectorAll('.all-star-rename');
    for (var i = 0; i < cards.length; i++) {
        (function (card) {
            var btn = card.querySelector('.all-star-rename__btn');
            var input = card.querySelector('.all-star-rename__input');
            var status = card.querySelector('.all-star-rename__status');
            var recordId = card.getAttribute('data-record-id');

            btn.addEventListener('click', function () {
                var name = input.value.trim();
                if (name === '') {
                    status.innerHTML = '<span class="all-star-rename__error">Please enter a team name.</span>';
                    return;
                }

                btn.disabled = true;
                status.innerHTML = '<span class="all-star-rename__saving">Saving&hellip;</span>';

                var fd = new FormData();
                fd.append('renameTeamId', recordId);
                fd.append('renameTeamName', name);

                fetch('/ibl5/scripts/allStarRename.php', { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success) {
                            card.classList.add('all-star-rename--done');
                            status.innerHTML = '<span class="all-star-rename__success">Renamed to &ldquo;' + escapeHtml(name) + '&rdquo;</span>';
                            btn.style.display = 'none';
                            input.disabled = true;
                        } else {
                            btn.disabled = false;
                            status.innerHTML = '<span class="all-star-rename__error">' + escapeHtml(data.error || 'Rename failed.') + '</span>';
                        }
                    })
                    .catch(function (err) {
                        btn.disabled = false;
                        status.innerHTML = '<span class="all-star-rename__error">' + escapeHtml(String(err)) + '</span>';
                    });
            });
        })(cards[i]);
    }
})();
</script>
        <?php
        return (string) ob_get_clean();
    }

}
