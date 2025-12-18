<?php

namespace FreeAgency;

use FreeAgency\Contracts\FreeAgencyNegotiationHelperInterface;
use Player\Player;
use Player\PlayerImageHelper;

/**
 * @see FreeAgencyNegotiationHelperInterface
 */
class FreeAgencyNegotiationHelper implements FreeAgencyNegotiationHelperInterface
{
    private object $mysqli_db;
    private FreeAgencyViewHelper $viewHelper;
    private FreeAgencyDemandCalculator $calculator;
    private FreeAgencyRepository $repository;
    private \Season $season;

    public function __construct(object $mysqli_db, \Season $season)
    {
        $this->mysqli_db = $mysqli_db;
        $this->season = $season;
        
        // Placeholder - will be replaced with actual player in renderNegotiationPage
        // Using a dummy player object for initialization
        $dummyPlayer = new Player();
        $this->viewHelper = new FreeAgencyViewHelper('', $dummyPlayer);
        $demandRepository = new FreeAgencyDemandRepository($this->mysqli_db);
        $this->calculator = new FreeAgencyDemandCalculator($demandRepository);
        $this->repository = new FreeAgencyRepository($this->mysqli_db);
    }

    /**
     * @see FreeAgencyNegotiationHelperInterface::renderNegotiationPage()
     */
    public function renderNegotiationPage(int $playerID, \Team $team): string
    {
        $player = Player::withPlayerID($this->mysqli_db, $playerID);
        
        // Initialize ViewHelper with actual team and player
        $this->viewHelper = new FreeAgencyViewHelper($team->name, $player);
        
        $capCalculator = new FreeAgencyCapCalculator($this->mysqli_db, $team, $this->season);
        $capMetrics = $capCalculator->calculateTeamCapMetrics($player->name);
        
        $demands = $this->calculator->getPlayerDemands($player->name);
        $veteranMinimum = \ContractRules::getVeteranMinimumSalary($player->yearsOfExperience);
        $maxContract = \ContractRules::getMaxContractSalary($player->yearsOfExperience);
        
        // Get existing offer if any
        $existingOffer = $this->getExistingOffer($team->name, $player->name);
        
        // Adjust cap space to account for existing offer
        $amendedCapSpace = $capMetrics['softCapSpace'][0] + $existingOffer['offer1'];
        
        // Check if there's an existing offer
        $hasExistingOffer = $existingOffer['offer1'] > 0;
        
        ob_start();
        ?>
<b><?= htmlspecialchars($player->position) ?> <?= htmlspecialchars($player->name) ?></b> - Contract Demands:
<br>

<?= $this->viewHelper->renderPlayerRatings() ?>

<img align="left" src="<?= htmlspecialchars(PlayerImageHelper::getImageUrl($playerID)) ?>">

Here are my demands (note these are not adjusted for your team's attributes; I will adjust the offer you make to me accordingly):

<?php if ($capMetrics['rosterSpots'][0] < 1 && !$hasExistingOffer): ?>
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
        
        <?= $this->renderNotesReminders($maxContract, $veteranMinimum, $amendedCapSpace, $capMetrics, $player->birdYears) ?>
        
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
     * @see FreeAgencyNegotiationHelperInterface::getExistingOffer()
     */
    public function getExistingOffer(string $teamName, string $playerName): array
    {
        $offer = $this->repository->getExistingOffer($teamName, $playerName);
        
        if ($offer === null) {
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
            'offer1' => (int) ($offer['offer1'] ?? 0),
            'offer2' => (int) ($offer['offer2'] ?? 0),
            'offer3' => (int) ($offer['offer3'] ?? 0),
            'offer4' => (int) ($offer['offer4'] ?? 0),
            'offer5' => (int) ($offer['offer5'] ?? 0),
            'offer6' => (int) ($offer['offer6'] ?? 0),
        ];
    }

    /**
     * Render Notes/Reminders section
     * 
     * @param int $maxContract Maximum contract value
     * @param int $veteranMinimum Veteran minimum salary
     * @param int $amendedCapSpace Amended cap space for year 1
     * @param array<string, mixed> $capMetrics Cap space data
     * @param int $birdYears Bird rights years
     * @return string HTML table row
     */
    private function renderNotesReminders(
        int $maxContract,
        int $veteranMinimum,
        int $amendedCapSpace,
        array $capMetrics,
        int $birdYears
    ): string {
        $softCapSpace = $capMetrics['softCapSpace'];
        $hardCapSpace = $capMetrics['hardCapSpace'];
        
        // Calculate raise percentage and example based on bird years (matching validator logic)
        $raisePercentage = \ContractRules::getMaxRaisePercentage($birdYears);
        $raisePercentageDisplay = (int)($raisePercentage * 100);
        $exampleSalary = 500;
        $exampleRaise = (int) round($exampleSalary * $raisePercentage);
        
        $hasBirdRights = \ContractRules::hasBirdRights($birdYears);
        if ($hasBirdRights) {
            $birdRightsText = "<b>Bird Rights Player on Your Team:</b> You may add no more than {$raisePercentageDisplay}% of the amount you offer in the first year as a raise between years (for instance, if you offer {$exampleSalary} in Year 1, you cannot offer a raise of more than {$exampleRaise} between any two subsequent years.)";
        } else {
            $birdRightsText = "<b>For Players who do not have Bird Rights with your team:</b> You may add no more than {$raisePercentageDisplay}% of the amount you offer in the first year as a raise between years (for instance, if you offer {$exampleSalary} in Year 1, you cannot offer a raise of more than {$exampleRaise} between any two subsequent years.)";
        }
        
        ob_start();
        ?>
<tr>
    <td colspan="8">
        <b>Notes/Reminders:</b>
        <ul>
            <li>The maximum contract permitted for me (based on my years of service) starts at <?= htmlspecialchars($maxContract) ?> in Year 1.</li>
            <li>You have <b><?= htmlspecialchars($amendedCapSpace) ?></b> in <b>soft cap</b> space available; the amount you offer in year 1 cannot exceed this unless you are using one of the exceptions.</li>
            <?php for ($year = 1; $year < 6; $year++): ?>
            <li>You have <b><?= htmlspecialchars($softCapSpace[$year]) ?></b> in <b>soft cap</b> space available; the amount you offer in year <?= $year + 1 ?> cannot exceed this unless you are using one of the exceptions.</li>
            <?php endfor; ?>
            <?php for ($year = 0; $year < 6; $year++): ?>
            <li>You have <b><?= htmlspecialchars($hardCapSpace[$year]) ?></b> in <b>hard cap</b> space available; the amount you offer in year <?= $year + 1 ?> cannot exceed this.</li>
            <?php endfor; ?>
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
