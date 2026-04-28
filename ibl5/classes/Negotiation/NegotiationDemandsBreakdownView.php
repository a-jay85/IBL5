<?php

declare(strict_types=1);

namespace Negotiation;

use BasketballStats\StatsFormatter;
use Negotiation\Contracts\NegotiationDemandCalculatorInterface;
use Utilities\HtmlSanitizer;

/**
 * Renders a detailed breakdown of the contract demands formula.
 *
 * @phpstan-import-type DemandsBreakdown from NegotiationDemandCalculatorInterface
 */
class NegotiationDemandsBreakdownView
{
    /**
     * @param DemandsBreakdown $breakdown
     */
    public static function render(array $breakdown): string
    {
        ob_start();
        ?>
<details class="debug-breakdown">
    <summary class="debug-breakdown__summary">Demands Formula Breakdown</summary>

    <div class="debug-breakdown__section">
        <h3 class="debug-breakdown__heading">Ratings vs Market</h3>
        <table class="ibl-data-table">
            <thead>
                <tr>
                    <th>Rating</th>
                    <th>Player</th>
                    <th>Market Max</th>
                    <th>Raw Score</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($breakdown['ratings'] as $rating): ?>
                <tr>
                    <td><?= HtmlSanitizer::e($rating['name']) ?></td>
                    <td><?= (int) $rating['playerValue'] ?></td>
                    <td><?= (int) $rating['marketMax'] ?></td>
                    <td><?= (int) $rating['rawScore'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="debug-breakdown__section">
        <h3 class="debug-breakdown__heading">Score Pipeline</h3>
        <dl class="debug-breakdown__kv">
            <div><dt>Total Raw Score</dt><dd><?= (int) $breakdown['totalRawScore'] ?></dd></div>
            <div><dt>Baseline</dt><dd>-<?= (int) $breakdown['baseline'] ?></dd></div>
            <div><dt>Adjusted Score</dt><dd><?= (int) $breakdown['adjustedScore'] ?></dd></div>
            <div><dt>&times; 3 (demands factor) = Avg Demands</dt><dd><?= HtmlSanitizer::e(StatsFormatter::formatWithDecimals((float) $breakdown['avgDemands'], 1)) ?></dd></div>
            <div><dt>&times; 5 = Total Demands</dt><dd><?= HtmlSanitizer::e(StatsFormatter::formatWithDecimals((float) $breakdown['totalDemands'], 1)) ?></dd></div>
            <div><dt>&divide; 6 = Base Demands (Yr 1)</dt><dd><?= HtmlSanitizer::e(StatsFormatter::formatWithDecimals((float) $breakdown['baseDemands'], 1)) ?></dd></div>
            <div><dt>10% Raise per Year</dt><dd><?= HtmlSanitizer::e(StatsFormatter::formatWithDecimals((float) $breakdown['maxRaise'], 1)) ?></dd></div>
        </dl>
    </div>

    <div class="debug-breakdown__section">
        <h3 class="debug-breakdown__heading">Player FA Preferences</h3>
        <table class="ibl-data-table">
            <thead>
                <tr><th>Attribute</th><th>Value (1-10)</th></tr>
            </thead>
            <tbody>
                <tr><td>Play for Winner</td><td><?= (int) $breakdown['faPreferences']['playForWinner'] ?></td></tr>
                <tr><td>Tradition</td><td><?= (int) $breakdown['faPreferences']['tradition'] ?></td></tr>
                <tr><td>Loyalty</td><td><?= (int) $breakdown['faPreferences']['loyalty'] ?></td></tr>
                <tr><td>Playing Time</td><td><?= (int) $breakdown['faPreferences']['playingTime'] ?></td></tr>
            </tbody>
        </table>
    </div>

    <div class="debug-breakdown__section">
        <h3 class="debug-breakdown__heading">Team Factors</h3>
        <table class="ibl-data-table">
            <thead>
                <tr><th>Factor</th><th>Value</th></tr>
            </thead>
            <tbody>
                <tr><td>Wins</td><td><?= (int) $breakdown['teamFactors']['wins'] ?></td></tr>
                <tr><td>Losses</td><td><?= (int) $breakdown['teamFactors']['losses'] ?></td></tr>
                <tr><td>Tradition Wins</td><td><?= (int) $breakdown['teamFactors']['tradition_wins'] ?></td></tr>
                <tr><td>Tradition Losses</td><td><?= (int) $breakdown['teamFactors']['tradition_losses'] ?></td></tr>
                <tr><td>$ at Position</td><td><?= (int) $breakdown['teamFactors']['money_committed_at_position'] ?></td></tr>
            </tbody>
        </table>
    </div>

    <div class="debug-breakdown__section">
        <h3 class="debug-breakdown__heading">Modifier Components</h3>
        <table class="ibl-data-table">
            <thead>
                <tr>
                    <th>Modifier</th>
                    <th>Formula</th>
                    <th>Inputs</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($breakdown['modifiers'] as $mod): ?>
                <tr>
                    <td><?= HtmlSanitizer::e($mod['name']) ?></td>
                    <td class="debug-breakdown__mono"><?= HtmlSanitizer::e($mod['formula']) ?></td>
                    <td class="debug-breakdown__mono"><?= HtmlSanitizer::e($mod['inputs']) ?></td>
                    <td><?= HtmlSanitizer::e(StatsFormatter::formatWithDecimals($mod['result'], 4)) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="debug-breakdown__section">
        <h3 class="debug-breakdown__heading">Combined Modifier</h3>
        <p class="debug-breakdown__formula">1 + PFW + tradition + loyalty + PT = <strong><?= HtmlSanitizer::e(StatsFormatter::formatWithDecimals($breakdown['totalModifier'], 4)) ?></strong></p>
    </div>

    <div class="debug-breakdown__section">
        <h3 class="debug-breakdown__heading">Final Demands</h3>
        <table class="ibl-data-table">
            <thead>
                <tr><th>Year</th><th>Amount</th></tr>
            </thead>
            <tbody>
                <?php for ($yr = 1; $yr <= 5; $yr++): ?>
                    <?php $key = 'year' . $yr; ?>
                    <?php if ($breakdown['demands'][$key] !== 0): ?>
                    <tr>
                        <td>Year <?= HtmlSanitizer::e($yr) ?></td>
                        <td><?= (int) $breakdown['demands'][$key] ?></td>
                    </tr>
                    <?php endif; ?>
                <?php endfor; ?>
                <tr class="debug-breakdown__total-row">
                    <td><strong>Total</strong></td>
                    <td><strong><?= (int) $breakdown['demands']['total'] ?></strong></td>
                </tr>
                <tr>
                    <td>Years</td>
                    <td><?= (int) $breakdown['demands']['years'] ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</details>
        <?php
        return (string) ob_get_clean();
    }
}
