<?php

namespace FreeAgency;

/**
 * Handles HTML rendering for Free Agency module
 * 
 * Uses output buffering pattern for clean, maintainable HTML generation.
 * All output is properly escaped to prevent XSS vulnerabilities.
 */
class FreeAgencyViewHelper
{
    private string $teamName;
    private int $playerID;

    /**
     * Constructor
     * 
     * @param string $teamName Team name for hidden form fields
     * @param int $playerID Player ID for hidden form fields
     */
    public function __construct(string $teamName, int $playerID)
    {
        $this->teamName = $teamName;
        $this->playerID = $playerID;
    }

    /**
     * Render player ratings table
     * 
     * @param \Player\Player $player Player object with ratings
     * @return string HTML table
     */
    public function renderPlayerRatings(\Player\Player $player): string
    {
        ob_start();
        ?>
<center>
    <table>
        <tr>
            <td align="center"><b>2ga</b></td>
            <td align="center"><b>2gp</b></td>
            <td align="center"><b>fta</b></td>
            <td align="center"><b>ftp</b></td>
            <td align="center"><b>3ga</b></td>
            <td align="center"><b>3gp</b></td>
            <td align="center"><b>orb</b></td>
            <td align="center"><b>drb</b></td>
            <td align="center"><b>ast</b></td>
            <td align="center"><b>stl</b></td>
            <td align="center"><b>tvr</b></td>
            <td align="center"><b>blk</b></td>
            <td align="center"><b>foul</b></td>
            <td align="center"><b>oo</b></td>
            <td align="center"><b>do</b></td>
            <td align="center"><b>po</b></td>
            <td align="center"><b>to</b></td>
            <td align="center"><b>od</b></td>
            <td align="center"><b>dd</b></td>
            <td align="center"><b>pd</b></td>
            <td align="center"><b>td</b></td>
        </tr>
        <tr>
            <td align="center"><?= htmlspecialchars($player->ratingFieldGoalAttempts) ?></td>
            <td align="center"><?= htmlspecialchars($player->ratingFieldGoalPercentage) ?></td>
            <td align="center"><?= htmlspecialchars($player->ratingFreeThrowAttempts) ?></td>
            <td align="center"><?= htmlspecialchars($player->ratingFreeThrowPercentage) ?></td>
            <td align="center"><?= htmlspecialchars($player->ratingThreePointAttempts) ?></td>
            <td align="center"><?= htmlspecialchars($player->ratingThreePointPercentage) ?></td>
            <td align="center"><?= htmlspecialchars($player->ratingOffensiveRebounds) ?></td>
            <td align="center"><?= htmlspecialchars($player->ratingDefensiveRebounds) ?></td>
            <td align="center"><?= htmlspecialchars($player->ratingAssists) ?></td>
            <td align="center"><?= htmlspecialchars($player->ratingSteals) ?></td>
            <td align="center"><?= htmlspecialchars($player->ratingTurnovers) ?></td>
            <td align="center"><?= htmlspecialchars($player->ratingBlocks) ?></td>
            <td align="center"><?= htmlspecialchars($player->ratingFouls) ?></td>
            <td align="center"><?= htmlspecialchars($player->ratingOutsideOffense) ?></td>
            <td align="center"><?= htmlspecialchars($player->ratingDriveOffense) ?></td>
            <td align="center"><?= htmlspecialchars($player->ratingPostOffense) ?></td>
            <td align="center"><?= htmlspecialchars($player->ratingTransitionOffense) ?></td>
            <td align="center"><?= htmlspecialchars($player->ratingOutsideDefense) ?></td>
            <td align="center"><?= htmlspecialchars($player->ratingDriveDefense) ?></td>
            <td align="center"><?= htmlspecialchars($player->ratingPostDefense) ?></td>
            <td align="center"><?= htmlspecialchars($player->ratingTransitionDefense) ?></td>
        </tr>
    </table>
</center>
        <?php
        return ob_get_clean();
    }

    /**
     * Render demand display for negotiation form
     * 
     * @param array{dem1: int, dem2: int, dem3: int, dem4: int, dem5: int, dem6: int} $demands Player demands
     * @return string HTML table cells
     */
    public function renderDemandDisplay(array $demands): string
    {
        ob_start();
        
        echo htmlspecialchars($demands['dem1']);
        if ($demands['dem2'] != 0) echo "</td><td>" . htmlspecialchars($demands['dem2']);
        if ($demands['dem3'] != 0) echo "</td><td>" . htmlspecialchars($demands['dem3']);
        if ($demands['dem4'] != 0) echo "</td><td>" . htmlspecialchars($demands['dem4']);
        if ($demands['dem5'] != 0) echo "</td><td>" . htmlspecialchars($demands['dem5']);
        if ($demands['dem6'] != 0) echo "</td><td>" . htmlspecialchars($demands['dem6']);
        echo "</td><td></td>";
        
        return ob_get_clean();
    }

    /**
     * Render offer input fields
     * 
     * @param array<string, int> $prefills Pre-filled offer values
     * @return string HTML input fields
     */
    public function renderOfferInputs(array $prefills): string
    {
        ob_start();
        ?>
<input type="number" style="width: 4em" name="offeryear1" size="4" value="<?= htmlspecialchars($prefills['offer1'] ?: '') ?>" min="0" max="9999"></td><td>
<input type="number" style="width: 4em" name="offeryear2" size="4" value="<?= htmlspecialchars($prefills['offer2'] ?: '') ?>" min="0" max="9999"></td><td>
<input type="number" style="width: 4em" name="offeryear3" size="4" value="<?= htmlspecialchars($prefills['offer3'] ?: '') ?>" min="0" max="9999"></td><td>
<input type="number" style="width: 4em" name="offeryear4" size="4" value="<?= htmlspecialchars($prefills['offer4'] ?: '') ?>" min="0" max="9999"></td><td>
<input type="number" style="width: 4em" name="offeryear5" size="4" value="<?= htmlspecialchars($prefills['offer5'] ?: '') ?>" min="0" max="9999"></td><td>
<input type="number" style="width: 4em" name="offeryear6" size="4" value="<?= htmlspecialchars($prefills['offer6'] ?: '') ?>" min="0" max="9999"></td>
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
<form name="FAOffer" method="post" action="modules.php?name=Free_Agency&pa=processoffer">
    <?= $this->renderHiddenFields($offers, $offerType) ?>
    <input type="submit" value="<?= htmlspecialchars($offers[$finalYear - 1]) ?>">
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
            echo "<input type=\"hidden\" name=\"offeryear{$yearNum}\" value=\"" . htmlspecialchars($offers[$i]) . "\">\n";
        }
        
        // Essential form data - uses instance properties
        echo "<input type=\"hidden\" name=\"teamname\" value=\"" . htmlspecialchars($this->teamName) . "\">\n";
        echo "<input type=\"hidden\" name=\"playerID\" value=\"" . htmlspecialchars($this->playerID) . "\">\n";
        echo "<input type=\"hidden\" name=\"offerType\" value=\"" . htmlspecialchars($offerType) . "\">\n";
        
        return ob_get_clean();
    }

    /**
     * Render max contract offer buttons
     * 
     * @param array<int> $maxSalaries Maximum salaries per year
     * @param int $birdYears Number of consecutive years with current team
     * @return string HTML form buttons
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
     * Render exception offer buttons (MLE, LLE, Vet Min)
     * 
     * @param string $exceptionType Type of exception (MLE, LLE, VET)
     * @return string HTML form buttons
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
            6
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
                'offers' => [\ContractRules::getVeteranMinimumSalary(1)],
                'offerType' => (string) OfferType::VETERAN_MINIMUM,
            ],
        ];
        
        echo $this->renderButtonRow(
            'Veterans Exception:',
            $contractOfferConfigs,
            6
        );
    }

    /**
     * Render a row of contract offer buttons with label and fill cells
     * 
     * @param string $label Label text for the first cell
     * @param array<array{offers: array<int>, offerType?: string}> $contractOfferConfigs Contract offer configurations by years
     * @param int $fillCells Number of empty cells to add at end
     * @return string HTML table row content
     */
    private function renderButtonRow(string $label, array $contractOfferConfigs, int $fillCells = 0): string
    {
        ob_start();
        
        echo "<td>" . htmlspecialchars($label) . "</td>";
        
        foreach ($contractOfferConfigs as $config) {
            $offerType = isset($config['offerType']) ? (int) $config['offerType'] : 0;
            $finalYear = count($config['offers']);
            echo "<td>{$this->renderOfferButtonForm($config['offers'], $finalYear, $offerType)}</td>";
        }
        
        if ($fillCells > 0) {
            echo "<td colspan=\"{$fillCells}\"></td>";
        } else {
            echo "<td></td>";
        }
        
        return ob_get_clean();
    }
}
