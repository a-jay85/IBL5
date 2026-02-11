<?php

declare(strict_types=1);

namespace Boxscore;

use Utilities\HtmlSanitizer;

/**
 * BoxscoreView - Renders .sco file upload form and parse log
 *
 * Handles all HTML rendering for the scoParser page.
 *
 * @see BoxscoreProcessor For the processing logic
 */
class BoxscoreView
{
    /**
     * Render the .sco file upload form with folder picker and per-file season detection
     *
     * @return string HTML form markup
     */
    public function renderUploadForm(): string
    {
        ob_start();
        ?>
<div class="sco-parser">
    <div class="ibl-card">
        <div class="ibl-card__header">
            <h1 class="ibl-card__title">.sco File Parser</h1>
            <div class="ibl-card__subtitle">Jump Shot Basketball Score Importer</div>
        </div>
        <div class="ibl-card__body" style="text-align: center;">
            <p class="sco-parser__description">Select a folder containing subfolders with <code>.sco</code> files.<br>
                Season and phase are auto-detected from folder names<br>
                (e.g. <code>0506 Preseason</code>, <code>9900 HEAT</code>, <code>0506 Sim 1</code>).</p>
            <label for="folderPicker" class="ibl-btn ibl-btn--secondary" style="cursor: pointer;">Choose Folder</label>
            <input type="file" id="folderPicker" webkitdirectory style="display: none;" />
        </div>
    </div>
    <div id="file-preview"></div>
    <div id="results"></div>
</div>
<script>
(function () {
    var folderInput = document.getElementById('folderPicker');
    var previewDiv = document.getElementById('file-preview');
    var resultsDiv = document.getElementById('results');

    /**
     * Parse a folder name for season ending year and phase.
     * Returns { year: number|null, phase: string|null }
     */
    function parseFolderName(name) {
        var year = null;
        var phase = null;

        // Year: first 4-digit sequence split into two 2-digit parts
        var yearMatch = name.match(/(\d{2})(\d{2})/);
        if (yearMatch) {
            var endPart = parseInt(yearMatch[2], 10);
            year = endPart >= 50 ? 1900 + endPart : 2000 + endPart;
        }

        // Phase: keyword search (priority order)
        var lower = name.toLowerCase();
        if (lower.indexOf('preseason') !== -1) {
            phase = 'Preseason';
        } else if (lower.indexOf('heat') !== -1) {
            phase = 'HEAT';
        } else if (lower.indexOf('playoff') !== -1) {
            phase = 'Regular Season/Playoffs';
        } else if (lower.indexOf('finals') !== -1) {
            phase = 'Regular Season/Playoffs';
        } else if (/\bsim\b/i.test(name)) {
            phase = 'Regular Season/Playoffs';
        }

        return { year: year, phase: phase };
    }

    /**
     * Check if all rows have both year and phase filled.
     */
    function updateSubmitState() {
        var btn = document.getElementById('sco-submit-btn');
        if (!btn) return;
        var rows = document.querySelectorAll('.sco-file-row');
        var allValid = true;
        for (var i = 0; i < rows.length; i++) {
            var yearInput = rows[i].querySelector('.sco-year-input');
            var phaseSelect = rows[i].querySelector('.sco-phase-select');
            if (!yearInput.value || !phaseSelect.value) {
                allValid = false;
                break;
            }
        }
        btn.disabled = !allValid;
    }

    folderInput.addEventListener('change', function () {
        var allFiles = Array.from(folderInput.files);
        var scoFiles = allFiles.filter(function (f) {
            return f.name.toLowerCase().endsWith('.sco');
        });

        if (scoFiles.length === 0) {
            previewDiv.innerHTML = '<p class="sco-no-files">No .sco files found in the selected folder.</p>';
            return;
        }

        /**
         * Submit files with their per-file season settings via fetch.
         */
        function submitFiles(files, years, phases) {
            var formData = new FormData();
            for (var k = 0; k < files.length; k++) {
                formData.append('scoFiles[]', files[k]);
                formData.append('seasonEndingYears[]', String(years[k]));
                formData.append('seasonPhases[]', phases[k]);
            }

            previewDiv.innerHTML = '<div class="sco-processing"><span class="sco-processing__spinner"></span> Processing\u2026</div>';

            fetch('/ibl5/scripts/scoParser.php', {
                method: 'POST',
                body: formData
            })
            .then(function (response) { return response.text(); })
            .then(function (html) {
                previewDiv.innerHTML = '';
                resultsDiv.innerHTML = html;
            })
            .catch(function (err) {
                previewDiv.innerHTML = '';
                resultsDiv.innerHTML = '<div class="ibl-alert ibl-alert--error"><strong>Upload Failed:</strong> ' + escapeHtml(String(err)) + '</div>';
            });
        }

        // For each file, parse folder name for year + phase
        var fileEntries = [];
        for (var i = 0; i < scoFiles.length; i++) {
            var file = scoFiles[i];
            var parts = file.webkitRelativePath.split('/');
            var folder = parts.length >= 2 ? parts[parts.length - 2] : '';
            fileEntries.push({ file: file, folder: folder, parsed: parseFolderName(folder), index: i });
        }

        // Single file with unambiguous detection: submit immediately
        if (scoFiles.length === 1 && fileEntries[0].parsed.year !== null && fileEntries[0].parsed.phase !== null) {
            submitFiles([scoFiles[0]], [fileEntries[0].parsed.year], [fileEntries[0].parsed.phase]);
            return;
        }

        // Multiple files or ambiguous detection: show preview table
        var html = '<table class="sco-preview-table">';
        html += '<thead><tr><th>Folder</th><th>File</th><th>Season Ending Year</th><th>Season Phase</th></tr></thead>';
        html += '<tbody>';

        for (var j = 0; j < fileEntries.length; j++) {
            var entry = fileEntries[j];
            var ambiguous = entry.parsed.year === null || entry.parsed.phase === null;
            var rowClass = 'sco-file-row' + (ambiguous ? ' sco-row--ambiguous' : '');

            html += '<tr class="' + rowClass + '" data-index="' + entry.index + '">';
            html += '<td class="sco-folder-name">' + escapeHtml(entry.folder) + '</td>';
            html += '<td class="sco-file-name">' + escapeHtml(entry.file.name) + '</td>';
            html += '<td><input type="number" class="sco-year-input" min="1950" max="2099" '
                + (entry.parsed.year !== null ? 'value="' + entry.parsed.year + '"' : '') + ' /></td>';
            html += '<td><select class="sco-phase-select">';
            html += '<option value=""' + (entry.parsed.phase === null ? ' selected' : '') + '>-- Select --</option>';
            html += '<option value="Preseason"' + (entry.parsed.phase === 'Preseason' ? ' selected' : '') + '>Preseason</option>';
            html += '<option value="HEAT"' + (entry.parsed.phase === 'HEAT' ? ' selected' : '') + '>HEAT</option>';
            html += '<option value="Regular Season/Playoffs"' + (entry.parsed.phase === 'Regular Season/Playoffs' ? ' selected' : '') + '>Regular Season</option>';
            html += '</select></td>';
            html += '</tr>';
        }

        html += '</tbody></table>';
        html += '<button type="button" id="sco-submit-btn" class="ibl-btn ibl-btn--primary" disabled>Process All Files</button>';

        previewDiv.innerHTML = html;

        // Attach change listeners for validation
        var inputs = previewDiv.querySelectorAll('.sco-year-input, .sco-phase-select');
        for (var m = 0; m < inputs.length; m++) {
            inputs[m].addEventListener('change', updateSubmitState);
            inputs[m].addEventListener('input', updateSubmitState);
        }
        updateSubmitState();

        // Submit handler for multi-file preview
        document.getElementById('sco-submit-btn').addEventListener('click', function () {
            var rows = document.querySelectorAll('.sco-file-row');
            var files = [];
            var years = [];
            var phases = [];
            for (var n = 0; n < rows.length; n++) {
                var idx = parseInt(rows[n].getAttribute('data-index'), 10);
                files.push(scoFiles[idx]);
                years.push(rows[n].querySelector('.sco-year-input').value);
                phases.push(rows[n].querySelector('.sco-phase-select').value);
            }
            submitFiles(files, years, phases);
        });
    });

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }
})();

</script>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render file upload error message
     *
     * @param int $errorCode PHP file upload error code
     * @return string HTML error message
     */
    public function renderUploadError(int $errorCode): string
    {
        return '<div class="ibl-alert ibl-alert--error"><strong>Upload Error:</strong> File upload failed (error code ' . $errorCode . ').</div>';
    }

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
            <?php /** @var string $safeMessage */ $safeMessage = HtmlSanitizer::safeHtmlOutput($message); ?>
            <p class="sco-log__message"><?= $safeMessage ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (isset($result['error']) && $result['error'] !== ''): ?>
        <?php /** @var string $safeError */ $safeError = HtmlSanitizer::safeHtmlOutput($result['error']); ?>
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
            <?php /** @var string $safeMessage */ $safeMessage = HtmlSanitizer::safeHtmlOutput($message); ?>
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
                <?php /** @var string $safeLabel */ $safeLabel = HtmlSanitizer::safeHtmlOutput($entry['teamLabel']); ?>
                <?= $safeLabel ?>
            </h3>
            <div class="all-star-rename__players">
                <?php foreach ($entry['players'] as $player): ?>
                <?php /** @var string $safeName */ $safeName = HtmlSanitizer::safeHtmlOutput($player); ?>
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

                fetch('/ibl5/scripts/scoParser.php', { method: 'POST', body: fd })
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
