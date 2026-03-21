<?php

declare(strict_types=1);

namespace Negotiation;

use League\League;
use Negotiation\Contracts\NegotiationDemandCalculatorInterface;
use Negotiation\Contracts\NegotiationViewHelperInterface;
use Player\Player;
use Player\PlayerImageHelper;
use Utilities\HtmlSanitizer;

/**
 * @see NegotiationViewHelperInterface
 *
 * @phpstan-import-type DemandResult from NegotiationDemandCalculatorInterface
 */
class NegotiationViewHelper implements NegotiationViewHelperInterface
{
    /**
     * @see NegotiationViewHelperInterface::renderNegotiationForm()
     *
     * @param Player $player The player object
     * @param DemandResult $demands Calculated demands
     * @param int $capSpace Available cap space for year 1
     * @param int $maxYearOneSalary Maximum first year salary based on experience
     */
    public static function renderNegotiationForm(
        Player $player,
        array $demands,
        int $capSpace,
        int $maxYearOneSalary
    ): string {
        $playerName = HtmlSanitizer::e($player->name ?? '');
        $playerID = $player->playerID ?? 0;
        $teamName = HtmlSanitizer::e($player->teamName ?? '');
        $playerPos = HtmlSanitizer::e($player->position ?? '');

        // Check if player demands exceed max
        $demandsExceedMax = $demands['year1'] >= $maxYearOneSalary;

        // Calculate max raises
        $birdYears = $player->birdYears ?? 0;
        $raisePercentage = \ContractRules::getMaxRaisePercentage($birdYears);
        $maxRaise = (int) round($maxYearOneSalary * $raisePercentage);
        $rawPercentage = $raisePercentage * 100;
        $raisePercentageDisplay = ($rawPercentage === floor($rawPercentage))
            ? (string) (int) $rawPercentage
            : rtrim(rtrim(sprintf('%.1f', $rawPercentage), '0'), '.');
        $hasBirdRights = \ContractRules::hasBirdRights($birdYears);
        $exampleSalary = 500;
        $exampleRaise = (int) round($exampleSalary * $raisePercentage);

        ob_start();

        // Card 1: Player Info
        ?>
<div class="ibl-card">
    <div class="ibl-card__header">
        <h2 class="ibl-card__title"><?= $playerPos ?> <?= $playerName ?> - Contract Extension</h2>
    </div>
    <div class="ibl-card__body">
        <div class="offer-player-info">
            <img src="<?= PlayerImageHelper::getImageUrl($player->playerID) ?>" alt="<?= $playerName ?>" class="offer-player-img">
            <?= self::renderPlayerRatings($player) ?>
        </div>
    </div>
</div>

<?php // Card 2: Contract Offer ?>
<div class="ibl-card">
    <div class="ibl-card__header">
        <h2 class="ibl-card__title">Contract Offer</h2>
    </div>
    <div class="ibl-card__body">
        <div class="ibl-alert ibl-alert--warning ibl-field-group">If you offer the max and I refuse, it means I am opting for Free Agency at the end of the season.</div>

        <div class="ibl-field-group">
            <span class="ibl-label">Player Demands:</span>
            <div class="ibl-field-group__content">
                <?= self::buildDemandDisplay($demands) ?>
            </div>
        </div>

        <form name="ExtensionOffer" method="post" action="modules/Player/extension.php">
            <div class="ibl-field-group">
                <span class="ibl-label">Your Offer:</span>
                <div class="ibl-field-group__content">
                    <?php if (!$demandsExceedMax): ?>
                        <?= self::renderEditableOfferFields($demands) ?>
                    <?php else: ?>
                        <?= self::renderMaxSalaryFields($maxYearOneSalary, $maxRaise, $demands) ?>
                    <?php endif; ?>
                </div>
            </div>

            <input type="hidden" name="maxyr1" value="<?= $maxYearOneSalary ?>">
            <input type="hidden" name="demandsTotal" value="<?= $demands['total'] ?>">
            <input type="hidden" name="demandsYears" value="<?= $demands['years'] ?>">
            <input type="hidden" name="teamName" value="<?= $teamName ?>">
            <input type="hidden" name="playerName" value="<?= $playerName ?>">
            <input type="hidden" name="playerID" value="<?= $playerID ?>">

            <button type="submit" class="ibl-btn ibl-btn--primary">Offer Extension</button>
        </form>
    </div>
</div>

<?php // Card 3: Notes / Reminders ?>
<div class="ibl-card">
    <div class="ibl-card__header">
        <h2 class="ibl-card__title">Notes / Reminders</h2>
    </div>
    <div class="ibl-card__body">
        <ul class="ibl-notes">
            <li>You have <strong><?= $capSpace ?></strong> in cap space available; the amount you offer in year 1 cannot exceed this.</li>
            <li>Based on years of service, the maximum amount you can offer in year 1 is <strong><?= $maxYearOneSalary ?></strong>.</li>
            <li>Enter "0" for years you do not want to offer a contract.</li>
            <li>Contract extensions must be at least three years in length.</li>
            <li>The amounts offered each year must equal or exceed the previous year.</li>
            <?php if ($hasBirdRights): ?>
                <li><strong>Bird Rights Player:</strong> You may add no more than <?= $raisePercentageDisplay ?>% of the amount you offer in the first year as a raise between years (for instance, if you offer <?= $exampleSalary ?> in Year 1, you cannot offer a raise of more than <?= $exampleRaise ?> between any two subsequent years.)</li>
            <?php else: ?>
                <li><strong>No Bird Rights:</strong> You may add no more than <?= $raisePercentageDisplay ?>% of the amount you offer in the first year as a raise between years (for instance, if you offer <?= $exampleSalary ?> in Year 1, you cannot offer a raise of more than <?= $exampleRaise ?> between any two subsequent years.)</li>
            <?php endif; ?>
            <li>When re-signing your own players, you can go over the soft cap and up to the hard cap (<?= League::HARD_CAP_MAX ?>).</li>
        </ul>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Build demand display as flex row with year labels
     *
     * @param DemandResult $demands Demand amounts
     * @return string HTML formatted demand display
     */
    private static function buildDemandDisplay(array $demands): string
    {
        $yearKeys = ['year1', 'year2', 'year3', 'year4', 'year5', 'year6'];

        ob_start();
        ?>
<div class="offer-salary-row">
    <?php foreach ($yearKeys as $index => $key): ?>
        <?php if ($demands[$key] !== 0): ?>
        <div class="offer-salary-cell">
            <div class="ibl-label ibl-label--sm">Yr <?= $index + 1 ?></div>
            <div class="offer-salary-cell__value"><?= (int) $demands[$key] ?></div>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render editable offer fields as flex row with year labels
     *
     * @param DemandResult $demands Demand amounts
     * @return string HTML for input fields
     */
    private static function renderEditableOfferFields(array $demands): string
    {
        ob_start();
        ?>
<div class="offer-salary-row offer-salary-row--inputs">
    <?php for ($i = 1; $i <= 5; $i++): ?>
    <div class="offer-salary-cell">
        <label for="offerYear<?= $i ?>" class="ibl-label ibl-label--sm">Yr <?= $i ?></label>
        <input type="number" id="offerYear<?= $i ?>" class="ibl-input ibl-input--sm offer-salary-input" name="offerYear<?= $i ?>" value="<?= (int) $demands['year' . $i] ?>" min="0" max="9999">
    </div>
    <?php endfor; ?>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render max salary fields as flex row with year labels
     *
     * @param int $maxYear1 Maximum first year salary
     * @param int $maxRaise Maximum raise per year
     * @param DemandResult $demands Demand amounts (to determine which years to show)
     * @return string HTML for input fields
     */
    private static function renderMaxSalaryFields(int $maxYear1, int $maxRaise, array $demands): string
    {
        $maxValues = [$maxYear1];
        $maxValues[] = ($demands['year2'] !== 0) ? $maxYear1 + $maxRaise : 0;
        $maxValues[] = ($demands['year3'] !== 0) ? $maxValues[1] + $maxRaise : 0;
        $maxValues[] = ($demands['year4'] !== 0) ? $maxValues[2] + $maxRaise : 0;
        $maxValues[] = ($demands['year5'] !== 0) ? $maxValues[3] + $maxRaise : 0;

        ob_start();
        ?>
<div class="offer-salary-row offer-salary-row--inputs">
    <?php for ($i = 0; $i < 5; $i++): ?>
    <div class="offer-salary-cell">
        <label for="offerYear<?= $i + 1 ?>" class="ibl-label ibl-label--sm">Yr <?= $i + 1 ?></label>
        <input type="number" id="offerYear<?= $i + 1 ?>" class="ibl-input ibl-input--sm offer-salary-input" name="offerYear<?= $i + 1 ?>" value="<?= $maxValues[$i] ?>" min="0" max="9999">
    </div>
    <?php endfor; ?>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @see NegotiationViewHelperInterface::renderError()
     */
    public static function renderError(string $error): string
    {
        $escaped = HtmlSanitizer::e($error);
        return '<div class="ibl-alert ibl-alert--error">' . $escaped . '</div>';
    }

    /**
     * @see NegotiationViewHelperInterface::renderHeader()
     */
    public static function renderHeader(Player $player): string
    {
        return '<h2 class="ibl-title">Contract Extension</h2>';
    }

    /**
     * Render player ratings table (21-column layout matching FA pattern)
     *
     * @param Player $player The player object
     * @return string HTML ratings table
     */
    private static function renderPlayerRatings(Player $player): string
    {
        ob_start();
        ?>
<table class="ibl-data-table offer-ratings">
    <thead>
        <tr>
            <th>2ga</th>
            <th>2gp</th>
            <th>fta</th>
            <th>ftp</th>
            <th>3ga</th>
            <th>3gp</th>
            <th>orb</th>
            <th>drb</th>
            <th>ast</th>
            <th>stl</th>
            <th>tvr</th>
            <th>blk</th>
            <th>foul</th>
            <th>oo</th>
            <th>do</th>
            <th>po</th>
            <th>to</th>
            <th>od</th>
            <th>dd</th>
            <th>pd</th>
            <th>td</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><?= (int) $player->ratingFieldGoalAttempts ?></td>
            <td><?= (int) $player->ratingFieldGoalPercentage ?></td>
            <td><?= (int) $player->ratingFreeThrowAttempts ?></td>
            <td><?= (int) $player->ratingFreeThrowPercentage ?></td>
            <td><?= (int) $player->ratingThreePointAttempts ?></td>
            <td><?= (int) $player->ratingThreePointPercentage ?></td>
            <td><?= (int) $player->ratingOffensiveRebounds ?></td>
            <td><?= (int) $player->ratingDefensiveRebounds ?></td>
            <td><?= (int) $player->ratingAssists ?></td>
            <td><?= (int) $player->ratingSteals ?></td>
            <td><?= (int) $player->ratingTurnovers ?></td>
            <td><?= (int) $player->ratingBlocks ?></td>
            <td><?= (int) $player->ratingFouls ?></td>
            <td><?= (int) $player->ratingOutsideOffense ?></td>
            <td><?= (int) $player->ratingDriveOffense ?></td>
            <td><?= (int) $player->ratingPostOffense ?></td>
            <td><?= (int) $player->ratingTransitionOffense ?></td>
            <td><?= (int) $player->ratingOutsideDefense ?></td>
            <td><?= (int) $player->ratingDriveDefense ?></td>
            <td><?= (int) $player->ratingPostDefense ?></td>
            <td><?= (int) $player->ratingTransitionDefense ?></td>
        </tr>
    </tbody>
</table>
        <?php
        return (string) ob_get_clean();
    }
}
