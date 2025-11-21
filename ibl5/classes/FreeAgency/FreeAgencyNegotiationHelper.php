<?php

namespace FreeAgency;

use Player\Player;
use Player\PlayerImageHelper;

/**
 * Handles Free Agency negotiation page rendering
 * 
 * Renders the negotiation form with:
 * - Player ratings
 * - Player demands
 * - Offer input fields
 * - Max contract buttons
 * - Exception buttons (MLE, LLE, Vet Min)
 */
class FreeAgencyNegotiationHelper
{
    private $db;
    private \Services\DatabaseService $databaseService;
    private FreeAgencyViewHelper $viewHelper;
    private FreeAgencyDemandCalculator $calculator;

    public function __construct($db)
    {
        $this->db = $db;
        $this->databaseService = new \Services\DatabaseService();
        // Placeholder - will be replaced with actual team/player in renderNegotiationPage
        $this->viewHelper = new FreeAgencyViewHelper('', 0);
        $this->calculator = new FreeAgencyDemandCalculator($db);
    }

    /**
     * Render complete negotiation page
     * 
     * @param int $playerID Player ID
     * @param \Team $team User's team
     * @return string HTML output
     */
    public function renderNegotiationPage(int $playerID, \Team $team): string
    {
        $player = Player::withPlayerID($this->db, $playerID);
        
        // Initialize ViewHelper with actual team and player context
        $this->viewHelper = new FreeAgencyViewHelper($team->name, $playerID);
        
        $capCalculator = new FreeAgencyCapCalculator($this->db);
        $capData = $capCalculator->calculateNegotiationCapSpace($team, $player->name);
        
        $demands = $this->calculator->getPlayerDemands($player->name);
        $veteranMinimum = \ContractRules::getVeteranMinimumSalary($player->yearsOfExperience);
        $maxContract = \ContractRules::getMaxContractSalary($player->yearsOfExperience);
        
        // Get existing offer if any
        $existingOffer = $this->getExistingOffer($team->name, $player->name);
        
        // Adjust cap space to account for existing offer
        $amendedCapSpace = $capData['softCap']['year1'] + $existingOffer['offer1'];
        
        // Check if there's an existing offer
        $hasExistingOffer = $existingOffer['offer1'] > 0;
        
        ob_start();
        ?>
<b><?= htmlspecialchars($player->position) ?> <?= htmlspecialchars($player->name) ?></b> - Contract Demands:
<br>

<?= $this->viewHelper->renderPlayerRatings($player) ?>

<img align="left" src="<?= htmlspecialchars(PlayerImageHelper::getImageUrl($playerID)) ?>">

Here are my demands (note these are not adjusted for your team's attributes; I will adjust the offer you make to me accordingly):

<?php if ($capData['rosterSpots'] < 1 && !$hasExistingOffer): ?>
    <table cellspacing="0" border="1">
        <tr>
            <td colspan="8">Sorry, you have no roster spots remaining and cannot offer me a contract!</td>
        </tr>
    </table>
<?php else: ?>
    <table cellspacing="0" border="1">
        <tr>
            <td>My demands are:</td>
            <td><?= $this->viewHelper->renderDemandDisplay($demands) ?></td>
        </tr>
        
        <form name="FAOffer" method="post" action="modules.php?name=Free_Agency&pa=processoffer">
        <tr>
            <td>Please enter your offer in this row:</td>
            <td><?= $this->viewHelper->renderOfferInputs($existingOffer) ?></td>
            
            <input type="hidden" name="teamname" value="<?= htmlspecialchars($team->name) ?>">
            <input type="hidden" name="playerID" value="<?= htmlspecialchars($player->playerID) ?>">
            <input type="hidden" name="offerType" value="0">
            
            <td><input type="submit" value="Offer/Amend Free Agent Contract!"></td>
        </tr>
        </form>
        
        <tr>
            <td colspan="8"><center><b>MAX SALARY OFFERS:</b></center></td>
        </tr>
        
        <?= $this->renderOfferButtons($player) ?>
        
        <?= $this->renderNotesReminders($maxContract, $veteranMinimum, $amendedCapSpace, $capData, $player->birdYears) ?>
        
        <?php if ($hasExistingOffer): ?>
        <tr>
            <td colspan="8">
                <form method="post" action="modules.php?name=Free_Agency&pa=deleteoffer">
                    <input type="hidden" name="teamname" value="<?= htmlspecialchars($team->name) ?>">
                    <input type="hidden" name="playerID" value="<?= htmlspecialchars($player->playerID) ?>">
                    <center><input type="submit" value="DELETE this offer"></center>
                </form>
            </td>
        </tr>
        <?php endif; ?>
    </table>
<?php endif; ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Render all offer button rows (Max Contract, MLE, LLE, Vet Min)
     * 
     * @param Player $player
     * @return string HTML table rows
     */
    private function renderOfferButtons(Player $player): string
    {
        // Calculate max contract salary and raises based on bird years
        $maxContract = \ContractRules::getMaxContractSalary($player->yearsOfExperience);
        $raisePercentage = \ContractRules::getMaxRaisePercentage($player->birdYears);
        $maxRaise = (int) round($maxContract * $raisePercentage);
        
        // Build max salaries for all 6 years using same raise logic as validator
        // Use 0-based indexing for array_slice compatibility in FreeAgencyViewHelper
        $maxSalaries = [
            0 => $maxContract,
            1 => $maxContract + $maxRaise,
            2 => $maxContract + ($maxRaise * 2),
            3 => $maxContract + ($maxRaise * 3),
            4 => $maxContract + ($maxRaise * 4),
            5 => $maxContract + ($maxRaise * 5),
        ];
        
        ob_start();
        echo $this->viewHelper->renderMaxContractButtons($maxSalaries, $player->birdYears);
        
        // MLE row
        echo "<tr>";
        echo $this->viewHelper->renderExceptionButtons('MLE');
        echo "</tr>";
        
        // LLE row
        echo "<tr>";
        echo $this->viewHelper->renderExceptionButtons('LLE');
        echo "</tr>";
        
        // Vet Min row
        echo "<tr>";
        echo $this->viewHelper->renderExceptionButtons('VET');
        echo "</tr>";
        
        return ob_get_clean();
    }

    /**
     * Get existing offer for a player
     * 
     * @param string $teamName
     * @param string $playerName
     * @return array<string, int> Existing offer or empty array
     */
    public function getExistingOffer(string $teamName, string $playerName): array
    {
        $escapedTeamName = $this->databaseService->escapeString($this->db, $teamName);
        $escapedPlayerName = $this->databaseService->escapeString($this->db, $playerName);
        
        $query = "SELECT * FROM ibl_fa_offers WHERE team='$escapedTeamName' AND name='$escapedPlayerName'";
        $result = $this->db->sql_query($query);
        $offer = $this->db->sql_fetchrow($result);
        
        if (!$offer) {
            return [
                'offer1' => 0,
                'offer2' => 0,
                'offer3' => 0,
                'offer4' => 0,
                'offer5' => 0,
                'offer6' => 0,
            ];
        }
        
        return [
            'offer1' => (int) ($offer['offer1'] ?: 0),
            'offer2' => (int) ($offer['offer2'] ?: 0),
            'offer3' => (int) ($offer['offer3'] ?: 0),
            'offer4' => (int) ($offer['offer4'] ?: 0),
            'offer5' => (int) ($offer['offer5'] ?: 0),
            'offer6' => (int) ($offer['offer6'] ?: 0),
        ];
    }

    /**
     * Render Notes/Reminders section
     * 
     * @param int $maxContract Maximum contract value
     * @param int $veteranMinimum Veteran minimum salary
     * @param int $amendedCapSpace Amended cap space for year 1
     * @param array<string, mixed> $capData Cap space data
     * @param int $birdYears Bird rights years
     * @return string HTML table row
     */
    private function renderNotesReminders(
        int $maxContract,
        int $veteranMinimum,
        int $amendedCapSpace,
        array $capData,
        int $birdYears
    ): string {
        $hardCapYear1 = $capData['hardCap']['year1'];
        $hardCapYear2 = $capData['hardCap']['year2'];
        $hardCapYear3 = $capData['hardCap']['year3'];
        $hardCapYear4 = $capData['hardCap']['year4'];
        $hardCapYear5 = $capData['hardCap']['year5'];
        $hardCapYear6 = $capData['hardCap']['year6'];
        
        $softCapYear2 = $capData['softCap']['year2'];
        $softCapYear3 = $capData['softCap']['year3'];
        $softCapYear4 = $capData['softCap']['year4'];
        $softCapYear5 = $capData['softCap']['year5'];
        $softCapYear6 = $capData['softCap']['year6'];
        
        // Calculate raise percentage and example based on bird years (matching validator logic)
        $raisePercentage = \ContractRules::getMaxRaisePercentage($birdYears);
        $exampleRaise = (int) round(500 * $raisePercentage);
        
        if ($raisePercentage >= 0.125) {
            $birdRightsText = "<b>Bird Rights Player on Your Team:</b> You may add no more than 12.5% of the amount you offer in the first year as a raise between years (for instance, if you offer 500 in Year 1, you cannot offer a raise of more than {$exampleRaise} between any two subsequent years.)";
        } else {
            $birdRightsText = "<b>For Players who do not have Bird Rights with your team:</b> You may add no more than 10% of the amount you offer in the first year as a raise between years (for instance, if you offer 500 in Year 1, you cannot offer a raise of more than {$exampleRaise} between any two subsequent years.)";
        }
        
        ob_start();
        ?>
<tr>
    <td colspan="8">
        <b>Notes/Reminders:</b>
        <ul>
            <li>The maximum contract permitted for me (based on my years of service) starts at <?= htmlspecialchars($maxContract) ?> in Year 1.</li>
            <li>You have <b><?= htmlspecialchars($amendedCapSpace) ?></b> in <b>soft cap</b> space available; the amount you offer in year 1 cannot exceed this unless you are using one of the exceptions.</li>
            <li>You have <b><?= htmlspecialchars($softCapYear2) ?></b> in <b>soft cap</b> space available; the amount you offer in year 2 cannot exceed this unless you are using one of the exceptions.</li>
            <li>You have <b><?= htmlspecialchars($softCapYear3) ?></b> in <b>soft cap</b> space available; the amount you offer in year 3 cannot exceed this unless you are using one of the exceptions.</li>
            <li>You have <b><?= htmlspecialchars($softCapYear4) ?></b> in <b>soft cap</b> space available; the amount you offer in year 4 cannot exceed this unless you are using one of the exceptions.</li>
            <li>You have <b><?= htmlspecialchars($softCapYear5) ?></b> in <b>soft cap</b> space available; the amount you offer in year 5 cannot exceed this unless you are using one of the exceptions.</li>
            <li>You have <b><?= htmlspecialchars($softCapYear6) ?></b> in <b>soft cap</b> space available; the amount you offer in year 6 cannot exceed this unless you are using one of the exceptions.</li>
            <li>You have <b><?= htmlspecialchars($hardCapYear1) ?></b> in <b>hard cap</b> space available; the amount you offer in year 1 cannot exceed this.</li>
            <li>You have <b><?= htmlspecialchars($hardCapYear2) ?></b> in <b>hard cap</b> space available; the amount you offer in year 2 cannot exceed this.</li>
            <li>You have <b><?= htmlspecialchars($hardCapYear3) ?></b> in <b>hard cap</b> space available; the amount you offer in year 3 cannot exceed this.</li>
            <li>You have <b><?= htmlspecialchars($hardCapYear4) ?></b> in <b>hard cap</b> space available; the amount you offer in year 4 cannot exceed this.</li>
            <li>You have <b><?= htmlspecialchars($hardCapYear5) ?></b> in <b>hard cap</b> space available; the amount you offer in year 5 cannot exceed this.</li>
            <li>You have <b><?= htmlspecialchars($hardCapYear6) ?></b> in <b>hard cap</b> space available; the amount you offer in year 6 cannot exceed this.</li>
            <li>Enter "0" for years you do not want to offer a contract.</li>
            <li>The amounts offered each year must equal or exceed the previous year.</li>
            <li>The first year of the contract must be at least the veteran's minimum (<?= htmlspecialchars($veteranMinimum) ?> for this player).</li>
            <li><?= $birdRightsText ?></li>
        </ul>
    </td>
</tr>
        <?php
        return ob_get_clean();
    }
}
