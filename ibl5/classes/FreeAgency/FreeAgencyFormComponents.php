<?php

declare(strict_types=1);

namespace FreeAgency;

use FreeAgency\Contracts\FreeAgencyFormComponentsInterface;

/**
 * @see FreeAgencyFormComponentsInterface
 */
class FreeAgencyFormComponents implements FreeAgencyFormComponentsInterface
{
    private string $teamName;
    private \Player\Player $player;

    /**
     * Constructor
     *
     * @param string $teamName Team name for hidden form fields
     * @param \Player\Player $player Player object with all necessary data
     */
    public function __construct(string $teamName, \Player\Player $player)
    {
        $this->teamName = $teamName;
        $this->player = $player;
    }

    /**
     * @see FreeAgencyFormComponentsInterface::renderPlayerRatings()
     */
    public function renderPlayerRatings(): string
    {
        ob_start();
        ?>
<table class="ibl-data-table" style="font-size: 0.875rem;">
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
            <td><?= (int) $this->player->ratingFieldGoalAttempts ?></td>
            <td><?= (int) $this->player->ratingFieldGoalPercentage ?></td>
            <td><?= (int) $this->player->ratingFreeThrowAttempts ?></td>
            <td><?= (int) $this->player->ratingFreeThrowPercentage ?></td>
            <td><?= (int) $this->player->ratingThreePointAttempts ?></td>
            <td><?= (int) $this->player->ratingThreePointPercentage ?></td>
            <td><?= (int) $this->player->ratingOffensiveRebounds ?></td>
            <td><?= (int) $this->player->ratingDefensiveRebounds ?></td>
            <td><?= (int) $this->player->ratingAssists ?></td>
            <td><?= (int) $this->player->ratingSteals ?></td>
            <td><?= (int) $this->player->ratingTurnovers ?></td>
            <td><?= (int) $this->player->ratingBlocks ?></td>
            <td><?= (int) $this->player->ratingFouls ?></td>
            <td><?= (int) $this->player->ratingOutsideOffense ?></td>
            <td><?= (int) $this->player->ratingDriveOffense ?></td>
            <td><?= (int) $this->player->ratingPostOffense ?></td>
            <td><?= (int) $this->player->ratingTransitionOffense ?></td>
            <td><?= (int) $this->player->ratingOutsideDefense ?></td>
            <td><?= (int) $this->player->ratingDriveDefense ?></td>
            <td><?= (int) $this->player->ratingPostDefense ?></td>
            <td><?= (int) $this->player->ratingTransitionDefense ?></td>
        </tr>
    </tbody>
</table>
        <?php
        return ob_get_clean();
    }

    /**
     * @see FreeAgencyFormComponentsInterface::renderDemandDisplay()
     */
    public function renderDemandDisplay(array $demands): string
    {
        ob_start();
        ?>
<div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
    <?php for ($i = 1; $i <= 6; $i++): ?>
        <?php if ($demands["dem{$i}"] !== 0): ?>
        <div style="text-align: center;">
            <div class="ibl-label" style="font-size: 0.75rem;">Yr <?= $i ?></div>
            <div style="font-weight: 600;"><?= (int) $demands["dem{$i}"] ?></div>
        </div>
        <?php endif; ?>
    <?php endfor; ?>
</div>
        <?php
        return ob_get_clean();
    }

    /**
     * @see FreeAgencyFormComponentsInterface::renderOfferInputs()
     */
    public function renderOfferInputs(array $prefills): string
    {
        ob_start();
        ?>
<div style="display: flex; gap: 0.5rem; align-items: flex-end; flex-wrap: wrap;">
    <?php for ($i = 1; $i <= 6; $i++): ?>
    <div style="text-align: center;">
        <label class="ibl-label" style="font-size: 0.75rem; display: block;">Yr <?= $i ?></label>
        <input type="number" class="ibl-input ibl-input--sm" style="width: 4.5rem;" name="offeryear<?= $i ?>" value="<?= $prefills["offer{$i}"] ?: '' ?>" min="0" max="9999">
    </div>
    <?php endfor; ?>
</div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a single offer button form
     *
     * Used for all contract offer types (max contract, MLE, LLE, vet min)
     *
     * @param array<int> $offers Salary amounts for each year
     * @param int $finalYear Final year of contract
     * @param int $offerType Offer type (0=normal, 1-6=MLE, 7=LLE, 8=VET)
     * @return string HTML form
     */
    private function renderOfferButtonForm(array $offers, int $finalYear, int $offerType = 0): string
    {
        ob_start();
        ?>
<form name="FAOffer" method="post" action="modules.php?name=FreeAgency&pa=processoffer" style="display: inline;">
    <?= $this->renderHiddenFields($offers, $offerType) ?>
    <button type="submit" class="ibl-btn ibl-btn--sm ibl-btn--primary"><?= (int) $offers[$finalYear - 1] ?></button>
</form>
        <?php
        return ob_get_clean();
    }

    /**
     * Render hidden form fields
     *
     * @param array<int> $offers Offer amounts
     * @param int $offerType Offer type (0=normal, 1-6=MLE, 7=LLE, 8=VET)
     * @return string HTML hidden inputs
     */
    private function renderHiddenFields(array $offers, int $offerType = 0): string
    {
        ob_start();

        // Offer years
        for ($i = 0; $i < count($offers); $i++) {
            $yearNum = $i + 1;
            echo "<input type=\"hidden\" name=\"offeryear{$yearNum}\" value=\"" . (int) $offers[$i] . "\">\n";
        }

        // Essential form data - uses player properties
        echo "<input type=\"hidden\" name=\"teamname\" value=\"" . htmlspecialchars($this->teamName) . "\">\n";
        echo "<input type=\"hidden\" name=\"playerID\" value=\"" . (int) $this->player->playerID . "\">\n";
        echo "<input type=\"hidden\" name=\"offerType\" value=\"" . (int) $offerType . "\">\n";

        return ob_get_clean();
    }

    /**
     * @see FreeAgencyFormComponentsInterface::renderMaxContractButtons()
     */
    public function renderMaxContractButtons(array $maxSalaries, int $birdYears = 0): string
    {
        $contractOfferConfigs = [];
        for ($years = 1; $years <= 6; $years++) {
            $contractOfferConfigs[] = [
                'offers' => array_slice($maxSalaries, 0, $years),
            ];
        }

        $raisePercentage = \ContractRules::getMaxRaisePercentage($birdYears);
        $raisePercentageDisplay = (int)($raisePercentage * 100);
        $hasBirdRights = \ContractRules::hasBirdRights($birdYears);
        $birdRightsText = $hasBirdRights ? ' with Bird Rights' : '';

        $label = "Max Level Contract {$raisePercentageDisplay}%{$birdRightsText} (click the button that corresponds to the final year you wish to offer):";

        return $this->renderButtonRow(
            $label,
            $contractOfferConfigs,
            0
        );
    }

    /**
     * @see FreeAgencyFormComponentsInterface::renderExceptionButtons()
     */
    public function renderExceptionButtons(string $exceptionType): string
    {
        ob_start();

        if ($exceptionType === 'MLE') {
            $this->renderMLEButtons();
        } elseif ($exceptionType === 'LLE') {
            $this->renderLLEButton();
        } elseif ($exceptionType === 'VET') {
            $this->renderVetMinButton();
        }

        return ob_get_clean();
    }

    /**
     * Render Mid-Level Exception buttons
     *
     * @return void
     */
    private function renderMLEButtons(): void
    {
        $contractOfferConfigs = [];
        for ($years = 1; $years <= 6; $years++) {
            $contractOfferConfigs[] = [
                'offers' => \ContractRules::getMLEOffers($years),
                'offerType' => (string) $years,
            ];
        }

        echo $this->renderButtonRow(
            'Mid-Level Exception (click the button that corresponds to the final year you wish to offer):',
            $contractOfferConfigs,
            0
        );
    }

    /**
     * Render Lower-Level Exception button
     *
     * @return void
     */
    private function renderLLEButton(): void
    {
        $contractOfferConfigs = [
            [
                'offers' => [\ContractRules::LLE_OFFER],
                'offerType' => (string) OfferType::LOWER_LEVEL_EXCEPTION,
            ],
        ];

        echo $this->renderButtonRow(
            'Lower-Level Exception:',
            $contractOfferConfigs,
            0
        );
    }

    /**
     * Render Veteran's Minimum button
     *
     * @return void
     */
    private function renderVetMinButton(): void
    {
        $contractOfferConfigs = [
            [
                'offers' => [\ContractRules::getVeteranMinimumSalary($this->player->yearsOfExperience)],
                'offerType' => (string) OfferType::VETERAN_MINIMUM,
            ],
        ];

        echo $this->renderButtonRow(
            'Veterans Exception:',
            $contractOfferConfigs,
            0
        );
    }

    /**
     * Render a row of contract offer buttons with label
     *
     * @param string $label Label text for the row
     * @param array<array{offers: array<int>, offerType?: string}> $contractOfferConfigs Contract offer configurations by years
     * @param int $fillCells Unused, kept for interface compatibility
     * @return string HTML flex row content
     */
    private function renderButtonRow(string $label, array $contractOfferConfigs, int $fillCells = 0): string
    {
        ob_start();
        ?>
<div style="margin-bottom: 0.75rem;">
    <span class="ibl-label"><?= htmlspecialchars($label) ?></span>
    <div style="display: flex; gap: 0.375rem; flex-wrap: wrap; margin-top: 0.25rem;">
        <?php foreach ($contractOfferConfigs as $config): ?>
            <?php
            $offerType = isset($config['offerType']) ? (int) $config['offerType'] : 0;
            $finalYear = count($config['offers']);
            echo $this->renderOfferButtonForm($config['offers'], $finalYear, $offerType);
            ?>
        <?php endforeach; ?>
    </div>
</div>
        <?php
        return ob_get_clean();
    }
}
