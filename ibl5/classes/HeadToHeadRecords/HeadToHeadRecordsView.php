<?php

declare(strict_types=1);

namespace HeadToHeadRecords;

use HeadToHeadRecords\Contracts\HeadToHeadRecordsRepositoryInterface;
use Utilities\HtmlSanitizer;

/**
 * @phpstan-import-type Dimension from HeadToHeadRecordsRepositoryInterface
 * @phpstan-import-type Phase from HeadToHeadRecordsRepositoryInterface
 * @phpstan-import-type Scope from HeadToHeadRecordsRepositoryInterface
 * @phpstan-import-type AxisEntry from HeadToHeadRecordsRepositoryInterface
 * @phpstan-import-type MatchupRecord from HeadToHeadRecordsRepositoryInterface
 * @phpstan-import-type MatrixPayload from HeadToHeadRecordsRepositoryInterface
 */
class HeadToHeadRecordsView
{
    /**
     * @param Scope $scope
     * @param Dimension $dimension
     * @param Phase $phase
     */
    public function renderFilterForm(string $scope, string $dimension, string $phase): string
    {
        ob_start();
        ?>
<form method="POST" class="ibl-filter-form">
    <div class="ibl-filter-form__row">
        <div class="ibl-filter-form__group">
            <label class="ibl-filter-form__label" for="h2h-scope">Scope</label>
            <select name="scope" id="h2h-scope">
                <option value="current"<?= $scope === 'current' ? ' selected' : ''; ?>>Current Season</option>
                <option value="all_time"<?= $scope === 'all_time' ? ' selected' : ''; ?>>All-Time</option>
            </select>
        </div>
        <div class="ibl-filter-form__group">
            <label class="ibl-filter-form__label" for="h2h-dimension">Dimension</label>
            <select name="dimension" id="h2h-dimension">
                <option value="active_teams"<?= $dimension === 'active_teams' ? ' selected' : ''; ?>>Active Teams</option>
                <option value="all_time_teams"<?= $dimension === 'all_time_teams' ? ' selected' : ''; ?>>All-Time Teams</option>
                <option value="gms"<?= $dimension === 'gms' ? ' selected' : ''; ?>>GMs</option>
            </select>
        </div>
        <div class="ibl-filter-form__group">
            <label class="ibl-filter-form__label" for="h2h-phase">Phase</label>
            <select name="phase" id="h2h-phase">
                <option value="heat"<?= $phase === 'heat' ? ' selected' : ''; ?>>HEAT</option>
                <option value="regular"<?= $phase === 'regular' ? ' selected' : ''; ?>>Regular Season</option>
                <option value="playoffs"<?= $phase === 'playoffs' ? ' selected' : ''; ?>>Playoffs</option>
                <option value="all"<?= $phase === 'all' ? ' selected' : ''; ?>>All-Time</option>
            </select>
        </div>
        <div class="ibl-filter-form__group">
            <button type="submit" class="ibl-filter-form__submit">Filter</button>
        </div>
    </div>
</form>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param MatrixPayload $payload
     * @param list<string|int> $userMatchKeys axis keys that match the logged-in user
     */
    public function renderMatrix(array $payload, array $userMatchKeys): string
    {
        $axis = $payload['axis'];
        $matrix = $payload['matrix'];

        if ($axis === []) {
            return '<p class="h2h-empty-state">No records found for the selected filters.</p>';
        }

        $userKeySet = array_flip($userMatchKeys);

        ob_start();
        ?>
<div class="sticky-scroll-wrapper page-sticky">
<div class="sticky-scroll-container">
<table class="ibl-data-table sticky-table h2h-table">
<thead><tr>
    <th class="sticky-col sticky-corner">&nbsp;&nbsp;&nbsp;&rarr;&rarr;<br>vs.<br>&uarr;</th>
    <?php foreach ($axis as $entry): ?>
    <?php
        $isUserCol = isset($userKeySet[$entry['key']]);
        $safeLabel = HtmlSanitizer::e($entry['label']);
        $headerContent = $entry['logo'] !== ''
            ? '<img src="' . HtmlSanitizer::e($entry['logo']) . '" width="40" height="40" alt="' . $safeLabel . '">'
            : '<span class="h2h-gm-header">' . $safeLabel . '</span>';
        if ($isUserCol) {
            $headerContent = '<strong>' . $headerContent . '</strong>';
        }
    ?>
    <th class="text-center"><?= $headerContent; ?></th>
    <?php endforeach; ?>
</tr></thead>
<tbody>
<?php foreach ($axis as $rowEntry):
    $rowKey = $rowEntry['key'];
    $isUserRow = isset($userKeySet[$rowKey]);
    $safeRowLabel = HtmlSanitizer::e($rowEntry['label']);
    $rowLabel = $isUserRow ? '<strong>' . $safeRowLabel . '</strong>' : $safeRowLabel;
?>
<tr>
    <td class="sticky-col h2h-row-label"><?= $rowLabel; ?></td>
    <?php foreach ($axis as $colEntry):
        $colKey = $colEntry['key'];
        $isUserCell = $isUserRow || isset($userKeySet[$colKey]);
    ?>
    <?php if ($rowKey === $colKey): ?>
    <td class="h2h-diag"></td>
    <?php else:
        $record = $matrix[$rowKey][$colKey] ?? null;
        if ($record === null || ($record['wins'] === 0 && $record['losses'] === 0)):
    ?>
    <td class="h2h-empty text-center"><?= $isUserCell ? '<strong>&mdash;</strong>' : '&mdash;'; ?></td>
    <?php else:
        $wins = $record['wins'];
        $losses = $record['losses'];
        $total = $wins + $losses;
        $winPct = $total > 0 ? $wins / $total : 0.0;
        $pctFormatted = \BasketballStats\StatsFormatter::formatWithDecimals($winPct, 3);
        $statusClass = $wins > $losses ? 'h2h-winning' : ($wins < $losses ? 'h2h-losing' : 'h2h-tied');
        $cellContent = HtmlSanitizer::e((string) $wins) . '-' . HtmlSanitizer::e((string) $losses);
        if ($isUserCell) {
            $cellContent = '<strong>' . $cellContent . '</strong>';
        }
    ?>
    <td class="text-center <?= $statusClass; ?>" title="<?= $pctFormatted; ?>"><?= $cellContent; ?></td>
    <?php endif; endif; ?>
    <?php endforeach; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    public function renderTapTooltipScript(): string
    {
        ob_start();
        ?>
<script>
(function(){
    var tip=null;
    document.querySelector('.h2h-table').addEventListener('touchend',function(e){
        var td=e.target.closest('td[title]');
        if(tip){tip.remove();tip=null;}
        if(!td)return;
        e.preventDefault();
        tip=document.createElement('span');
        tip.className='h2h-tooltip';
        tip.textContent=td.getAttribute('title');
        td.appendChild(tip);
    });
    document.addEventListener('touchend',function(e){
        if(tip&&!e.target.closest('.h2h-table')){tip.remove();tip=null;}
    });
})();
</script>
        <?php
        return (string) ob_get_clean();
    }
}
