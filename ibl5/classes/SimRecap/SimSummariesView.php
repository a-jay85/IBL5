<?php

declare(strict_types=1);

namespace SimRecap;

use PageLayout\PageLayout;
use Security\HtmlSanitizer;

/**
 * Admin viewer for the sim recap queue — index table plus one optional recap panel.
 *
 * Every dynamic byte is escaped with HtmlSanitizer::e(). `trusted()` is used for
 * exactly one thing here: PageLayout::renderFontPreconnectLinks(), markup this
 * codebase pre-renders itself. Nothing that came out of `ibl_sim_summaries` ever
 * goes through it — `recap_text` is LLM-generated prose and untrusted by provenance.
 *
 * The class emits no headers and never calls exit; the `format=txt` export lives in
 * the top-level page, where BanDirectHeaderCallRule and BanDieExitInProductionRule
 * do not apply.
 */
class SimSummariesView
{
    /**
     * @param list<array<string, mixed>> $rows         From SimSummaryRepository::listAll()
     * @param array<string, mixed>|null  $recap        From SimSummaryRepository::find(), or null
     * @param list<array<string, mixed>> $gameRecaps   From SimSummaryRepository::findDisplayableGameRecaps(), sorted by sort_order
     * @param string|null                $error        'malformed' | 'notfound' | null
     * @param int|null                   $requestedSim The validated sim behind a 'notfound' notice
     */
    public function render(array $rows, ?array $recap, array $gameRecaps, ?string $error, ?int $requestedSim = null): string
    {
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sim Recaps</title>
    <?= HtmlSanitizer::trusted(PageLayout::renderFontPreconnectLinks()) // @phpstan-ignore ibl.trustedVariable (three literal <link> tags built from string constants in PageLayout — no parameters, no interpolation, no request or DB data) ?>
    <link rel="stylesheet" href="/ibl5/themes/IBL/style/style.css">
</head>
<body>
<div class="updater">
    <h1 class="updater__title">Sim Recaps</h1>

<?php if ($error === 'malformed'): ?>
    <p class="ibl-card" id="recap-error">Invalid sim number.</p>
<?php elseif ($error === 'notfound'): ?>
    <p class="ibl-card" id="recap-error">No recap stored for sim <?= HtmlSanitizer::e($requestedSim ?? '') ?>.</p>
<?php endif; ?>

    <table class="ibl-data-table">
        <thead>
            <tr>
                <th>Sim</th>
                <th>Status</th>
                <th>Attempts</th>
                <th>Generated</th>
                <th>Created</th>
                <th>Body</th>
            </tr>
        </thead>
        <tbody>
<?php if ($rows === []): ?>
            <tr>
                <td colspan="6">No sim recaps have been generated yet.</td>
            </tr>
<?php else: ?>
<?php foreach ($rows as $row): ?>
            <tr>
                <td><a href="simSummaries.php?sim=<?= HtmlSanitizer::e($row['sim'] ?? '') ?>"><?= HtmlSanitizer::e($row['sim'] ?? '') ?></a></td>
                <td><?= HtmlSanitizer::e($row['status'] ?? '') ?></td>
                <td><?= HtmlSanitizer::e($row['attempts'] ?? '') ?></td>
                <td><?= HtmlSanitizer::e($row['generated_at'] ?? '—') ?></td>
                <td><?= HtmlSanitizer::e($row['created_at'] ?? '—') ?></td>
                <td><?= HtmlSanitizer::e($this->formatBodyLength($row)) ?></td>
            </tr>
<?php endforeach; ?>
<?php endif; ?>
        </tbody>
    </table>

<?php if ($recap !== null): ?>
    <section class="ibl-card" id="recap-panel">
        <h2 class="ibl-title">Sim <?= HtmlSanitizer::e($recap['sim'] ?? '') ?></h2>
        <p>Status: <?= HtmlSanitizer::e($recap['status'] ?? '') ?> · Attempts: <?= HtmlSanitizer::e($recap['attempts'] ?? '') ?> · Generated: <?= HtmlSanitizer::e($recap['generated_at'] ?? '—') ?></p>
        <p id="recap-themes">Themes: <?= HtmlSanitizer::e($this->themeSummary($recap)) ?></p>
<?php $body = $recap['recap_text'] ?? null; ?>
<?php if (!is_string($body)): ?>
        <p id="recap-missing">No recap text stored yet — status: <?= HtmlSanitizer::e($recap['status'] ?? '') ?>.</p>
<?php else: ?>
<?php $intro = $recap['intro_text'] ?? null; ?>
<?php if (is_string($intro) && $intro !== ''): ?>
        <p id="recap-intro"><?= HtmlSanitizer::e($intro) ?></p>
<?php endif; ?>
<?php if ($gameRecaps !== []): ?>
        <ol id="recap-games">
<?php foreach ($gameRecaps as $game): ?>
<?php
            $gDate = $game['game_date'] ?? null;
            $gVid  = $game['visitor_teamid'] ?? null;
            $gHid  = $game['home_teamid'] ?? null;
?>
            <li class="recap-game">
                <span class="recap-game__meta"><?= HtmlSanitizer::e(is_string($gDate) ? $gDate : '') ?> · team <?= HtmlSanitizer::e(is_scalar($gVid) ? (string) $gVid : '') ?> at team <?= HtmlSanitizer::e(is_scalar($gHid) ? (string) $gHid : '') ?></span>
                <p class="recap-game__text"><?= HtmlSanitizer::e($game['recap_text'] ?? '') ?></p>
            </li>
<?php endforeach; ?>
        </ol>
<?php endif; ?>
<?php $outro = $recap['outro_text'] ?? null; ?>
<?php if (is_string($outro) && $outro !== ''): ?>
        <p id="recap-outro"><?= HtmlSanitizer::e($outro) ?></p>
<?php endif; ?>
        <textarea id="recap-body" readonly rows="24" cols="100"><?= HtmlSanitizer::e($body) ?></textarea>
        <p>
            <button type="button" id="recap-copy">Copy</button>
            <span id="recap-copied" hidden>Copied</span>
            <a id="recap-download" href="simSummaries.php?sim=<?= HtmlSanitizer::e($recap['sim'] ?? '') ?>&amp;format=txt">Download raw text</a>
        </p>
        <script>
            document.getElementById('recap-copy').addEventListener('click', function () {
                navigator.clipboard.writeText(document.getElementById('recap-body').value);
                document.getElementById('recap-copied').hidden = false;
            });
        </script>
<?php endif; ?>
    </section>
<?php endif; ?>

    <a href="/ibl5/index.php" class="updater__return underline">Return to IBL</a>
</div>
</body>
</html>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * The index shows body size, never the body — `listAll()` does not select it.
     *
     * @param array<string, mixed> $row
     */
    private function formatBodyLength(array $row): string
    {
        $length = $row['recap_length'] ?? null;

        return is_numeric($length) ? ((string) (int) $length) . ' bytes' : '—';
    }

    /**
     * `themes_used` is a JSON column and is never rendered raw. The return value
     * is plain text that the caller escapes — nothing here is pre-rendered HTML,
     * so `trusted()` never touches a recap value. A payload that does not decode
     * to a non-empty array renders as a literal em dash; unit 1's
     * `recentThemesToleratesMalformedJson()` proves such rows exist.
     *
     * @param array<string, mixed> $recap
     */
    private function themeSummary(array $recap): string
    {
        $raw = $recap['themes_used'] ?? null;
        if (!is_string($raw) || $raw === '') {
            return '—';
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return '—';
        }

        if (!is_array($decoded)) {
            return '—';
        }

        $parts = [];
        foreach ($decoded as $theme) {
            if (is_scalar($theme)) {
                $parts[] = (string) $theme;
            }
        }

        return $parts === [] ? '—' : implode(', ', $parts);
    }
}
