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
    private $db;
    private \Services\DatabaseService $databaseService;

    public function __construct($db)
    {
        $this->db = $db;
        $this->databaseService = new \Services\DatabaseService();
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
     * @param int $playerExperience Years of experience
     * @return string HTML table cells
     */
    public function renderDemandDisplay(array $demands, int $playerExperience): string
    {
        ob_start();
        
        if ($playerExperience > 0) {
            // Veteran - show all applicable years
            echo htmlspecialchars($demands['dem1']);
            if ($demands['dem2'] != 0) echo "</td><td>" . htmlspecialchars($demands['dem2']);
            if ($demands['dem3'] != 0) echo "</td><td>" . htmlspecialchars($demands['dem3']);
            if ($demands['dem4'] != 0) echo "</td><td>" . htmlspecialchars($demands['dem4']);
            if ($demands['dem5'] != 0) echo "</td><td>" . htmlspecialchars($demands['dem5']);
            if ($demands['dem6'] != 0) echo "</td><td>" . htmlspecialchars($demands['dem6']);
            echo "</td><td></td>";
        } else {
            // Undrafted rookie - limit to 2 years (show dem3/dem4)
            echo htmlspecialchars($demands['dem3']);
            if ($demands['dem4'] != 0) echo "</td><td>" . htmlspecialchars($demands['dem4']);
            echo "</td><td></td>";
        }
        
        return ob_get_clean();
    }

    /**
     * Render offer input fields
     * 
     * @param int $playerExperience Years of experience
     * @param array<string, int> $prefills Pre-filled offer values
     * @return string HTML input fields
     */
    public function renderOfferInputs(int $playerExperience, array $prefills): string
    {
        ob_start();
        
        if ($playerExperience > 0) {
            // Veteran - 6 year offers allowed
            ?>
<input type="number" style="width: 4em" name="offeryear1" size="4" value="<?= htmlspecialchars($prefills['offer1'] ?: '') ?>"></td><td>
<input type="number" style="width: 4em" name="offeryear2" size="4" value="<?= htmlspecialchars($prefills['offer2'] ?: '') ?>"></td><td>
<input type="number" style="width: 4em" name="offeryear3" size="4" value="<?= htmlspecialchars($prefills['offer3'] ?: '') ?>"></td><td>
<input type="number" style="width: 4em" name="offeryear4" size="4" value="<?= htmlspecialchars($prefills['offer4'] ?: '') ?>"></td><td>
<input type="number" style="width: 4em" name="offeryear5" size="4" value="<?= htmlspecialchars($prefills['offer5'] ?: '') ?>"></td><td>
<input type="number" style="width: 4em" name="offeryear6" size="4" value="<?= htmlspecialchars($prefills['offer6'] ?: '') ?>"></td>
            <?php
        } else {
            // Undrafted rookie - limit to 2 years
            ?>
<input type="number" style="width: 4em" name="offeryear1" size="4" value="<?= htmlspecialchars($prefills['offer3'] ?: '') ?>"></td><td>
<input type="number" style="width: 4em" name="offeryear2" size="4" value="<?= htmlspecialchars($prefills['offer4'] ?: '') ?>"></td>
            <?php
        }
        
        return ob_get_clean();
    }

    /**
     * Render max contract offer buttons
     * 
     * @param array<string, mixed> $formData Data for hidden form fields
     * @param array<int> $maxSalaries Maximum salaries per year
     * @param int $playerExperience Years of experience
     * @return string HTML form buttons
     */
    public function renderMaxContractButtons(array $formData, array $maxSalaries, int $playerExperience): string
    {
        ob_start();
        ?>
<td>Max Level Contract 10% (click the button that corresponds to the final year you wish to offer):</td>
<td><?= $this->renderMaxContractForm($formData, [$maxSalaries[1]], 1) ?></td>
<td><?= $this->renderMaxContractForm($formData, [$maxSalaries[1], $maxSalaries[2]], 2) ?></td>
        <?php
        
        if ($playerExperience > 0) {
            echo "<td>{$this->renderMaxContractForm($formData, [$maxSalaries[1], $maxSalaries[2], $maxSalaries[3]], 3)}</td>";
            echo "<td>{$this->renderMaxContractForm($formData, [$maxSalaries[1], $maxSalaries[2], $maxSalaries[3], $maxSalaries[4]], 4)}</td>";
            echo "<td>{$this->renderMaxContractForm($formData, [$maxSalaries[1], $maxSalaries[2], $maxSalaries[3], $maxSalaries[4], $maxSalaries[5]], 5)}</td>";
            echo "<td>{$this->renderMaxContractForm($formData, [$maxSalaries[1], $maxSalaries[2], $maxSalaries[3], $maxSalaries[4], $maxSalaries[5], $maxSalaries[6]], 6)}</td>";
        }
        
        echo "<td></td>";
        
        return ob_get_clean();
    }

    /**
     * Render a single max contract form
     * 
     * @param array<string, mixed> $formData Hidden field data
     * @param array<int> $offers Salary amounts for each year
     * @param int $finalYear Final year of contract
     * @return string HTML form
     */
    private function renderMaxContractForm(array $formData, array $offers, int $finalYear): string
    {
        ob_start();
        ?>
<form name="FAOffer" method="post" action="modules.php?name=Free_Agency&pa=processoffer">
    <?= $this->renderHiddenFields($formData, $offers) ?>
    <input type="submit" value="<?= htmlspecialchars($offers[$finalYear - 1]) ?>">
</form>
        <?php
        return ob_get_clean();
    }

    /**
     * Render hidden form fields
     * 
     * @param array<string, mixed> $formData Form data
     * @param array<int> $offers Offer amounts
     * @return string HTML hidden inputs
     */
    private function renderHiddenFields(array $formData, array $offers): string
    {
        ob_start();
        
        // Offer years
        for ($i = 0; $i < count($offers); $i++) {
            $yearNum = $i + 1;
            echo "<input type=\"hidden\" name=\"offeryear{$yearNum}\" value=\"" . htmlspecialchars($offers[$i]) . "\">\n";
        }
        
        // Other form data
        foreach ($formData as $key => $value) {
            $safeKey = htmlspecialchars($key);
            $safeValue = htmlspecialchars($value);
            echo "<input type=\"hidden\" name=\"{$safeKey}\" value=\"{$safeValue}\">\n";
        }
        
        return ob_get_clean();
    }

    /**
     * Render exception offer buttons (MLE, LLE, Vet Min)
     * 
     * @param array<string, mixed> $formData Form data
     * @param string $exceptionType Type of exception (MLE, LLE, VET)
     * @param int $playerExperience Years of experience
     * @return string HTML form buttons
     */
    public function renderExceptionButtons(array $formData, string $exceptionType, int $playerExperience): string
    {
        ob_start();
        
        if ($exceptionType === 'MLE') {
            $this->renderMLEButtons($formData, $playerExperience);
        } elseif ($exceptionType === 'LLE') {
            $this->renderLLEButton($formData);
        } elseif ($exceptionType === 'VET') {
            $this->renderVetMinButton($formData);
        }
        
        return ob_get_clean();
    }

    /**
     * Render Mid-Level Exception buttons
     * 
     * @param array<string, mixed> $formData Form data
     * @param int $playerExperience Years of experience
     * @return void
     */
    private function renderMLEButtons(array $formData, int $playerExperience): void
    {
        echo "<td>Mid-Level Exception (click the button that corresponds to the final year you wish to offer):</td>";
        
        foreach (FreeAgencyNegotiationHelper::getMLEOffersArray() as $years => $offers) {
            $formDataWithMLE = array_merge($formData, ['MLEyrs' => (string) $years]);
            echo "<td>{$this->renderMaxContractForm($formDataWithMLE, $offers, $years)}</td>";
        }
        
        echo "<td></td>";
    }

    /**
     * Render Lower-Level Exception button
     * 
     * @param array<string, mixed> $formData Form data
     * @return void
     */
    private function renderLLEButton(array $formData): void
    {
        $formDataWithLLE = array_merge($formData, ['MLEyrs' => '7']);
        echo "<td>Lower-Level Exception:</td>";
        echo "<td>{$this->renderMaxContractForm($formDataWithLLE, [FreeAgencyNegotiationHelper::LLE_OFFER], 1)}</td>";
        echo "<td colspan=\"6\"></td>";
    }

    /**
     * Render Veteran's Minimum button
     * 
     * @param array<string, mixed> $formData Form data
     * @return void
     */
    private function renderVetMinButton(array $formData): void
    {
        $formDataWithVet = array_merge($formData, ['MLEyrs' => '8']);
        // Use formData value if available, otherwise use rookie minimum from constants
        $vetMin = $formData['vetmin'] ?? FreeAgencyNegotiationHelper::getVeteranMinimumSalary(1);
        echo "<td>Veterans Exception:</td>";
        echo "<td>{$this->renderMaxContractForm($formDataWithVet, [(int) $vetMin], 1)}</td>";
        echo "<td colspan=\"6\"></td>";
    }
}
