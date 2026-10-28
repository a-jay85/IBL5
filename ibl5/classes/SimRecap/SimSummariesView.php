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
     * @param list<array<string, mixed>> $rows          From SimSummaryRepository::listAll()
     * @param array<string, mixed>|null  $recap         From SimSummaryRepository::find(), or null
     * @param list<array<string, mixed>> $gameRecaps    From SimSummaryRepository::findDisplayableGameRecaps(), sorted by sort_order
     * @param string|null                $error         'malformed' | 'notfound' | null
     * @param int|null                   $requestedSim  The validated sim behind a 'notfound' notice
     * @param list<array<string, mixed>> $orphanedGames From SimSummaryRepository::findOrphanedGameRecaps()
     */
    public function render(array $rows, ?array $recap, array $gameRecaps, ?string $error, ?int $requestedSim = null, array $orphanedGames = []): string
    {
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<?= HtmlSanitizer::trusted($this->renderHead()) ?>
<body>
<div class="updater">
    <h1 class="updater__title">Sim Recaps</h1>

<?php $this->renderErrorNotice($error, $requestedSim); ?>

<?php $this->renderSimIndex($rows); ?>
<?php if ($recap !== null): ?>
<?php $this->renderRecapCard($recap, $gameRecaps, $orphanedGames); ?>
<?php endif; ?>

    <a href="/ibl5/index.php" class="updater__return underline">Return to IBL</a>
</div>
</body>
</html>
        <?php
        return (string) ob_get_clean();
    }

    private function renderErrorNotice(?string $error, ?int $requestedSim): void
    {
        ?>
<?php if ($error === 'malformed'): ?>
    <p class="ibl-card" id="recap-error">Invalid sim number.</p>
<?php elseif ($error === 'notfound'): ?>
    <p class="ibl-card" id="recap-error">No recap stored for sim <?= HtmlSanitizer::e($requestedSim ?? '') ?>.</p>
<?php endif; ?>
<?php
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function renderSimIndex(array $rows): void
    {
        ?>
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

<?php
    }

    /**
     * @param array<string, mixed>       $recap
     * @param list<array<string, mixed>> $gameRecaps
     * @param list<array<string, mixed>> $orphanedGames
     */
    private function renderRecapCard(array $recap, array $gameRecaps, array $orphanedGames): void
    {
        ?>
    <section class="ibl-card" id="recap-panel">
        <h2 class="ibl-title">Sim <?= HtmlSanitizer::e($recap['sim'] ?? '') ?></h2>
        <p>Status: <?= HtmlSanitizer::e($recap['status'] ?? '') ?> · Attempts: <?= HtmlSanitizer::e($recap['attempts'] ?? '') ?> · Generated: <?= HtmlSanitizer::e($recap['generated_at'] ?? '—') ?></p>
<?php if ($orphanedGames !== []): ?>
<?php $this->renderOrphanWarning($orphanedGames); ?>
<?php endif; ?>
<?php $body = $recap['recap_text'] ?? null; ?>
<?php if (!is_string($body)): ?>
        <p id="recap-missing">No recap text stored yet — status: <?= HtmlSanitizer::e($recap['status'] ?? '') ?>.</p>
<?php else: ?>
<?php
        $intro = $recap['intro_text'] ?? null;
        $outro = $recap['outro_text'] ?? null;
        // recap_text is usually a ~220-char teaser; the postable document only exists as
        // intro + per-game rows + outro. postableText() falls back to the stored text
        // when there is nothing to assemble or the parts lost their GM mention lines,
        // so Copy is never empty and never silently drops a tag.
        $document = RecapDocument::postableText(
            is_string($intro) ? $intro : null,
            $gameRecaps,
            is_string($outro) ? $outro : null,
            $body
        );
?>
        <textarea id="recap-body" readonly rows="24" cols="100"><?= HtmlSanitizer::e($document) ?></textarea>
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
<?php
    }

    /**
     * @param list<array<string, mixed>> $orphanedGames
     */
    private function renderOrphanWarning(array $orphanedGames): void
    {
        ?>
        <div id="recap-orphan-warning" class="ibl-card bg-yellow-50 border border-yellow-400 text-yellow-900">
            <p><strong>Warning:</strong> <?= HtmlSanitizer::e(count($orphanedGames)) ?> <?= HtmlSanitizer::e(count($orphanedGames) === 1 ? 'game' : 'games') ?> in this sim recap could not be matched to a box score and <?= HtmlSanitizer::e(count($orphanedGames) === 1 ? 'is' : 'are') ?> omitted from the postable document. Unresolvable keys:</p>
            <ul>
<?php foreach ($orphanedGames as $orphan): ?>
<?php
            $oDate = $orphan['game_date'] ?? null;
            $oVid  = $orphan['visitor_teamid'] ?? null;
            $oHid  = $orphan['home_teamid'] ?? null;
            $oGotd = $orphan['game_of_that_day'] ?? null;
?>
            <li class="recap-orphan"><?= HtmlSanitizer::e(is_string($oDate) ? $oDate : '') ?> · team <?= HtmlSanitizer::e(is_scalar($oVid) ? (string) $oVid : '') ?> at team <?= HtmlSanitizer::e(is_scalar($oHid) ? (string) $oHid : '') ?> · game <?= HtmlSanitizer::e(is_scalar($oGotd) ? (string) $oGotd : '') ?> of that day</li>
<?php endforeach; ?>
            </ul>
        </div>
<?php
    }

    private function renderHead(): string
    {
        return "<head>\n"
            . "    <meta charset=\"UTF-8\">\n"
            . "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n"
            . "    <title>Sim Recaps</title>\n"
            . '    ' . PageLayout::renderFontPreconnectLinks() . "\n"
            . "    <link rel=\"stylesheet\" href=\"/ibl5/themes/IBL/style/style.css\">\n"
            . "</head>\n";
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
}
