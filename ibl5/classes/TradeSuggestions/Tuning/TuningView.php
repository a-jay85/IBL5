<?php

declare(strict_types=1);

namespace TradeSuggestions\Tuning;

use BasketballStats\StatsFormatter;
use Security\HtmlSanitizer;
use TradeSuggestions\Engine\SideGain;
use TradeSuggestions\TeamMode;
use TradeSuggestions\Tuning\Contracts\TuningViewInterface;

/**
 * @see TuningViewInterface
 *
 * Every output goes through a template block, so the `ibl.unescapedOutput`
 * PHPStan rule checks each one. Stored free text is other GMs' input and is
 * escaped like everything else.
 */
final class TuningView implements TuningViewInterface
{
    /**
     * @see TuningViewInterface::render()
     */
    public function render(TuningReport $report): string
    {
        $filter = $report->filter;
        ob_start();
        ?>
<h1 class="ibl-title">Trade suggestions tuning</h1>
<p id="tuning-sim" class="tuning-sim">Sim <?= HtmlSanitizer::e($report->simKey) ?></p>

<form method="get" action="modules.php" class="d-card d-card-border bg-base-200 p-4 mb-6 tuning-filter">
    <input type="hidden" name="name" value="TradeSuggestionsTuning">
    <div class="flex flex-wrap items-center justify-center gap-4">
        <div class="flex items-center gap-2">
            <label class="d-label" for="tuning-team">Team</label>
            <select name="team" id="tuning-team" class="d-select w-auto">
                <option value="all"<?= HtmlSanitizer::e($filter->tid === null ? ' selected' : '') ?>>All teams</option>
<?php foreach ($report->teamNames as $tid => $name): ?>
                <option value="<?= HtmlSanitizer::e($tid) ?>"<?= HtmlSanitizer::e($filter->tid === $tid ? ' selected' : '') ?>><?= HtmlSanitizer::e($name) ?></option>
<?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-center gap-2">
            <label class="d-label" for="tuning-status">Show</label>
            <select name="status" id="tuning-status" class="d-select w-auto">
<?php foreach (TuningStatus::cases() as $status): ?>
                <option value="<?= HtmlSanitizer::e($status->value) ?>"<?= HtmlSanitizer::e($filter->status === $status ? ' selected' : '') ?>><?= HtmlSanitizer::e($status->label()) ?></option>
<?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="d-btn d-btn-primary">Run</button>
    </div>
</form>
<?php if ($filter->ignored !== []): ?>
<div class="ibl-alert ibl-alert--info" id="tuning-ignored">Ignored unknown values for: <?= HtmlSanitizer::e(implode(', ', $filter->ignored)) ?></div>
<?php endif; ?>

<details id="tuning-config" class="tuning-config">
    <summary>Engine settings</summary>
    <div class="table-scroll-wrapper">
        <div class="table-scroll-container">
            <table class="ibl-data-table tuning-config-table">
                <tbody>
<?php foreach ($report->config as $key => $value): ?>
                    <tr><th scope="row"><?= HtmlSanitizer::e($key) ?></th><td><pre class="tuning-json"><?= HtmlSanitizer::e(self::prettyJson($value)) ?></pre></td></tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</details>
<?php if ($report->truncated): ?>
<div class="ibl-alert ibl-alert--info" id="tuning-truncated">Stopped after <?= HtmlSanitizer::e(StatsFormatter::formatWithDecimals($report->elapsedSeconds, 1)) ?> s (budget <?= HtmlSanitizer::e(TuningBudget::TIME_BUDGET_SECONDS) ?> s). Teams not run: <?= HtmlSanitizer::e(implode(', ', $report->teamsNotRun)) ?></div>
<?php endif; ?>
<?php foreach ($report->sections as $section): ?>

<section class="tuning-team" id="tuning-team-<?= HtmlSanitizer::e($section->tid) ?>">
    <h2 class="ibl-title"><?= HtmlSanitizer::e($section->teamName) ?></h2>
    <table class="ibl-data-table tuning-counts">
        <tbody>
            <tr><th scope="row">Mode</th><td><?= HtmlSanitizer::e(self::modeLabel($section->mode)) ?></td></tr>
            <tr><th scope="row">Eligible partners</th><td><?= HtmlSanitizer::e(count($section->eligiblePartners)) ?></td></tr>
            <tr><th scope="row">Raw candidates</th><td><?= HtmlSanitizer::e($section->generation->rawCount) ?></td></tr>
            <tr><th scope="row">Prefilter rejected</th><td><?= HtmlSanitizer::e($section->generation->prefilterRejected) ?></td></tr>
            <tr><th scope="row">Capped</th><td><?= HtmlSanitizer::e($section->generation->capped ? 'yes' : 'no') ?></td></tr>
<?php foreach ($section->diagnostics->toArray() as $label => $count): ?>
<?php if (!is_array($count)): ?>
            <tr><th scope="row"><?= HtmlSanitizer::e($label) ?></th><td><?= HtmlSanitizer::e($count) ?></td></tr>
<?php elseif ($count === []): ?>
            <tr><th scope="row"><?= HtmlSanitizer::e($label) ?></th><td>-</td></tr>
<?php else: ?>
            <tr><th scope="row"><?= HtmlSanitizer::e($label) ?></th><td>
<?php foreach ($count as $id => $n): ?>
                <div class="tuning-count-line"><?= HtmlSanitizer::e($id) ?>: <?= HtmlSanitizer::e($n) ?></div>
<?php endforeach; ?>
            </td></tr>
<?php endif; ?>
<?php endforeach; ?>
        </tbody>
    </table>
<?php if ($section->rows === []): ?>
    <div class="ibl-empty-state"><p class="ibl-empty-state__text">No suggestions for this team in this sim.</p></div>
<?php else: ?>
    <div class="table-scroll-wrapper">
    <div class="table-scroll-container">
    <table class="ibl-data-table tuning-rows">
        <thead>
            <tr>
                <th>Rank</th><th>Outcome</th><th>Partner</th><th>Shape</th><th>Weaker side</th>
                <th>Your gain by season</th><th>Partner gain by season</th>
                <th>Vetoes</th><th>Detail</th><th>Exposure</th><th>Feedback</th>
            </tr>
        </thead>
        <tbody>
<?php foreach ($section->rows as $row): ?>
            <tr data-outcome="<?= HtmlSanitizer::e($row->outcome->value) ?>">
                <td><?= HtmlSanitizer::e($row->rank ?? '-') ?></td>
                <td class="tuning-outcome"><?= HtmlSanitizer::e($row->outcome->label()) ?></td>
                <td><?= HtmlSanitizer::e($report->teamNames[$row->partnerTid] ?? $row->partnerTid) ?></td>
                <td><?= HtmlSanitizer::e($row->shapeId) ?></td>
                <td><?= HtmlSanitizer::e(StatsFormatter::formatWithDecimals($row->weakerSideTotal, 2)) ?></td>
                <td><?= HtmlSanitizer::e(self::bySeason($row->user)) ?></td>
                <td><?= HtmlSanitizer::e(self::bySeason($row->partner)) ?></td>
                <td><?= HtmlSanitizer::e($row->firedVetoIds === [] ? '-' : implode(', ', $row->firedVetoIds)) ?></td>
                <td><?= HtmlSanitizer::e($row->detail ?? '-') ?></td>
                <td><?= HtmlSanitizer::e($row->exposureEligible ? 'yes' : 'no') ?></td>
<?php if ($row->feedback === null): ?>
                <td>-</td>
<?php else: ?>
                <td><?= HtmlSanitizer::e(implode(', ', $row->feedback->reasonLabels())) ?><?php if ($row->feedback->freeText !== null): ?> <span class="tuning-note"><?= HtmlSanitizer::e($row->feedback->freeText) ?></span><?php endif; ?></td>
<?php endif; ?>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
    </div>
    </div>
<?php endif; ?>
<?php if ($section->unmatchedFeedback !== []): ?>
    <h3 class="tuning-subtitle">Hidden ideas not in this run</h3>
    <div class="table-scroll-wrapper">
    <div class="table-scroll-container">
    <table class="ibl-data-table tuning-unmatched" id="tuning-unmatched-<?= HtmlSanitizer::e($section->tid) ?>">
        <thead>
            <tr><th>Partner</th><th>Reasons</th><th>Note</th><th>Hidden at</th></tr>
        </thead>
        <tbody>
<?php foreach ($section->unmatchedFeedback as $fb): ?>
            <tr>
                <td><?= HtmlSanitizer::e($report->teamNames[$fb->partnerTid] ?? $fb->partnerTid) ?></td>
                <td><?= HtmlSanitizer::e(implode(', ', $fb->reasonLabels())) ?></td>
                <td><span class="tuning-note"><?= HtmlSanitizer::e($fb->freeText ?? '-') ?></span></td>
                <td class="tuning-created-at"><?= HtmlSanitizer::e($fb->createdAt) ?></td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
    </div>
    </div>
<?php endif; ?>
<?php if ($section->omittedRows > 0): ?>
    <div class="ibl-alert ibl-alert--info"><?= HtmlSanitizer::e($section->omittedRows) ?> more rows not shown (cap <?= HtmlSanitizer::e(TuningBudget::MAX_ROWS_PER_TEAM) ?>).</div>
<?php endif; ?>
</section>
<?php endforeach; ?>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * One config value as indented JSON, so lists and maps show one entry per line.
     */
    private static function prettyJson(mixed $value): string
    {
        return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private static function modeLabel(TeamMode $mode): string
    {
        return match ($mode) {
            TeamMode::WinNow => 'Win now',
            TeamMode::Rebuild => 'Rebuild',
            TeamMode::Middle => 'Middle',
        };
    }

    /**
     * Season totals, nearest season first, e.g. "1.20 / 0.50 / 0.00 / -0.30".
     */
    private static function bySeason(SideGain $gain): string
    {
        $parts = [];
        foreach (array_keys($gain->components()) as $offset) {
            $parts[] = StatsFormatter::formatWithDecimals($gain->seasonTotal($offset), 2);
        }

        return $parts === [] ? '-' : implode(' / ', $parts);
    }
}
