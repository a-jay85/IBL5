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
    /**
     * Veteran minimum salary by years of experience for Free Agency signings
     * 
     * Note: These are the minimum salary tiers for Free Agency contract offers.
     * Year 1 value (35) represents the first-year rookie contract minimum.
     * Year 2+ values (51+) represent veteran and returning player minimums.
     * 
     * For waiver signings, see WaiversProcessor::calculateVeteranMinimumSalary()
     * which uses 51 as the minimum for all rookies.
     * 
     * @var array<int, int>
     */
    private const VETERAN_MINIMUM_SALARIES = [
        10 => 103,  // 10+ years
        9  => 100,  // 9 years
        8  => 89,   // 8 years
        7  => 82,   // 7 years
        6  => 76,   // 6 years
        5  => 70,   // 5 years
        4  => 64,   // 4 years
        3  => 61,   // 3 years
        2  => 51,   // 2 years (and above for waivers)
        1  => 35,   // 1 year (first-year rookie contract minimum)
    ];

    /**
     * Maximum contract salary by years of experience (first year only)
     * 
     * These represent the maximum first-year contract salary for players
     * based on their years of service. Additional years can have raises
     * up to 12.5% (with bird rights) or 10% (without bird rights).
     * 
     * @var array<int, int>
     */
    private const MAX_CONTRACT_SALARIES = [
        10 => 1451,  // 10+ years
        7  => 1275,  // 7-9 years
        0  => 1063,  // 0-6 years
    ];

    /**
     * Lower-Level Exception offer amount (LLE)
     * 
     * This represents the maximum salary for a LLE contract offer.
     * Available to teams with cap space and limited MLE eligibility.
     * 
     * @var int
     */
    public const LLE_OFFER = 145;

    /**
     * Mid-Level Exception offer amounts for a 6-year contract
     * 
     * These represent the MLE salary amounts for each year of a maximum 6-year contract.
     * Each year has a 10% raise from the previous year.
     * For contracts shorter than 6 years, use array_slice to get the appropriate years.
     * 
     * @var array<int>
     */
    public const MLE_OFFERS = [450, 495, 540, 585, 630, 675];

    private $db;
    private \Services\DatabaseService $databaseService;
    private FreeAgencyViewHelper $viewHelper;
    private FreeAgencyDemandCalculator $calculator;

    public function __construct($db)
    {
        $this->db = $db;
        $this->databaseService = new \Services\DatabaseService();
        $this->viewHelper = new FreeAgencyViewHelper($db);
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
        $capCalculator = new FreeAgencyCapCalculator($this->db);
        $capData = $capCalculator->calculateNegotiationCapSpace($team, $player->name);
        
        $demands = $this->calculator->getPlayerDemands($player->name);
        $veteranMinimum = self::getVeteranMinimumSalary($player->yearsOfExperience);
        $maxContract = self::getMaxContractSalary($player->yearsOfExperience);
        
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
            <td><?= $this->viewHelper->renderDemandDisplay($demands, $player->yearsOfExperience) ?></td>
        </tr>
        
        <form name="FAOffer" method="post" action="modules.php?name=Free_Agency&pa=processoffer">
        <tr>
            <td>Please enter your offer in this row:</td>
            <td><?= $this->viewHelper->renderOfferInputs($player->yearsOfExperience, $existingOffer) ?></td>
            
            <input type="hidden" name="amendedCapSpaceYear1" value="<?= htmlspecialchars($amendedCapSpace) ?>">
            <input type="hidden" name="capnumber" value="<?= htmlspecialchars($capData['softCap']['year1']) ?>">
            <input type="hidden" name="capnumber2" value="<?= htmlspecialchars($capData['softCap']['year2']) ?>">
            <input type="hidden" name="capnumber3" value="<?= htmlspecialchars($capData['softCap']['year3']) ?>">
            <input type="hidden" name="capnumber4" value="<?= htmlspecialchars($capData['softCap']['year4']) ?>">
            <input type="hidden" name="capnumber5" value="<?= htmlspecialchars($capData['softCap']['year5']) ?>">
            <input type="hidden" name="capnumber6" value="<?= htmlspecialchars($capData['softCap']['year6']) ?>">
            <input type="hidden" name="demtot" value="<?= htmlspecialchars($this->calculateTotalDemands($demands)) ?>">
            <input type="hidden" name="demyrs" value="<?= htmlspecialchars($this->calculateDemandYears($demands)) ?>">
            <input type="hidden" name="max" value="<?= htmlspecialchars($maxContract) ?>">
            <input type="hidden" name="teamname" value="<?= htmlspecialchars($team->name) ?>">
            <input type="hidden" name="player_teamname" value="<?= htmlspecialchars($player->teamName) ?>">
            <input type="hidden" name="playername" value="<?= htmlspecialchars($player->name) ?>">
            <input type="hidden" name="bird" value="<?= htmlspecialchars($player->birdYears) ?>">
            <input type="hidden" name="vetmin" value="<?= htmlspecialchars($veteranMinimum) ?>">
            <input type="hidden" name="MLEyrs" value="0">
            
            <td><input type="submit" value="Offer/Amend Free Agent Contract!"></td>
        </tr>
        </form>
        
        <tr>
            <td colspan="8"><center><b>MAX SALARY OFFERS:</b></center></td>
        </tr>
        
        <?= $this->renderMaxContractRow($team->name, $player, $maxContract, $veteranMinimum, $amendedCapSpace, $capData) ?>
        <?= $this->renderMLERow($team->name, $player, $veteranMinimum, $amendedCapSpace, $capData) ?>
        <?= $this->renderLLERow($team->name, $player, $veteranMinimum, $amendedCapSpace, $capData) ?>
        <?= $this->renderVetMinRow($team->name, $player, $veteranMinimum, $amendedCapSpace, $capData) ?>
        
        <?= $this->renderNotesReminders($maxContract, $veteranMinimum, $amendedCapSpace, $capData, $player->birdYears) ?>
        
        <?php if ($hasExistingOffer): ?>
        <tr>
            <td colspan="8">
                <form method="post" action="modules.php?name=Free_Agency&pa=deleteoffer">
                    <input type="hidden" name="teamname" value="<?= htmlspecialchars($team->name) ?>">
                    <input type="hidden" name="playername" value="<?= htmlspecialchars($player->name) ?>">
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
     * Render max contract offer row
     * 
     * @param string $teamName
     * @param Player $player
     * @param int $maxContract
     * @param int $veteranMinimum
     * @param int $amendedCapSpace
     * @param array<string, mixed> $capData
     * @return string HTML table row
     */
    private function renderMaxContractRow(
        string $teamName,
        Player $player,
        int $maxContract,
        int $veteranMinimum,
        int $amendedCapSpace,
        array $capData
    ): string {
        $maxRaise = (int) round($maxContract * 0.1);
        $maxSalaries = [
            1 => $maxContract,
            2 => $maxContract + $maxRaise,
            3 => $maxContract + ($maxRaise * 2),
            4 => $maxContract + ($maxRaise * 3),
            5 => $maxContract + ($maxRaise * 4),
            6 => $maxContract + ($maxRaise * 5),
        ];
        
        $formData = $this->buildFormData($teamName, $player, $veteranMinimum, $amendedCapSpace, $capData);
        
        return $this->viewHelper->renderMaxContractButtons($formData, $maxSalaries, $player->yearsOfExperience);
    }

    /**
     * Render MLE offer row
     * 
     * @param string $teamName
     * @param Player $player
     * @param int $veteranMinimum
     * @param int $amendedCapSpace
     * @param array<string, mixed> $capData
     * @return string HTML table row
     */
    private function renderMLERow(
        string $teamName,
        Player $player,
        int $veteranMinimum,
        int $amendedCapSpace,
        array $capData
    ): string {
        $formData = $this->buildFormData($teamName, $player, $veteranMinimum, $amendedCapSpace, $capData);
        
        ob_start();
        echo "<tr>";
        echo $this->viewHelper->renderExceptionButtons($formData, 'MLE', $player->yearsOfExperience);
        echo "</tr>";
        return ob_get_clean();
    }

    /**
     * Render LLE offer row
     * 
     * @param string $teamName
     * @param Player $player
     * @param int $veteranMinimum
     * @param int $amendedCapSpace
     * @param array<string, mixed> $capData
     * @return string HTML table row
     */
    private function renderLLERow(
        string $teamName,
        Player $player,
        int $veteranMinimum,
        int $amendedCapSpace,
        array $capData
    ): string {
        $formData = $this->buildFormData($teamName, $player, $veteranMinimum, $amendedCapSpace, $capData);
        
        ob_start();
        echo "<tr>";
        echo $this->viewHelper->renderExceptionButtons($formData, 'LLE', $player->yearsOfExperience);
        echo "</tr>";
        return ob_get_clean();
    }

    /**
     * Render Vet Min offer row
     * 
     * @param string $teamName
     * @param Player $player
     * @param int $veteranMinimum
     * @param int $amendedCapSpace
     * @param array<string, mixed> $capData
     * @return string HTML table row
     */
    private function renderVetMinRow(
        string $teamName,
        Player $player,
        int $veteranMinimum,
        int $amendedCapSpace,
        array $capData
    ): string {
        $formData = $this->buildFormData($teamName, $player, $veteranMinimum, $amendedCapSpace, $capData);
        
        ob_start();
        echo "<tr>";
        echo $this->viewHelper->renderExceptionButtons($formData, 'VET', $player->yearsOfExperience);
        echo "</tr>";
        return ob_get_clean();
    }

    /**
     * Build form data array
     * 
     * @param string $teamName
     * @param Player $player
     * @param int $veteranMinimum
     * @param int $amendedCapSpace
     * @param array<string, mixed> $capData
     * @return array<string, mixed>
     */
    private function buildFormData(
        string $teamName,
        Player $player,
        int $veteranMinimum,
        int $amendedCapSpace,
        array $capData
    ): array {
        $demands = $this->calculator->getPlayerDemands($player->name);
        $maxContract = self::getMaxContractSalary($player->yearsOfExperience);
        
        return [
            'teamname' => $teamName,
            'player_teamname' => $player->teamName,
            'playername' => $player->name,
            'bird' => (string) $player->birdYears,
            'vetmin' => (string) $veteranMinimum,
            'max' => (string) $maxContract,
            'amendedCapSpaceYear1' => (string) $amendedCapSpace,
            'capnumber' => (string) $capData['softCap']['year1'],
            'capnumber2' => (string) $capData['softCap']['year2'],
            'capnumber3' => (string) $capData['softCap']['year3'],
            'capnumber4' => (string) $capData['softCap']['year4'],
            'capnumber5' => (string) $capData['softCap']['year5'],
            'capnumber6' => (string) $capData['softCap']['year6'],
            'demtot' => (string) $this->calculateTotalDemands($demands),
            'demyrs' => (string) $this->calculateDemandYears($demands),
        ];
    }

    /**
     * Get veteran minimum salary for a specific experience level
     * Public static method for use by other classes (e.g., Waivers, view helpers)
     * 
     * @param int $experience Years of experience
     * @return int Veteran minimum salary
     */
    public static function getVeteranMinimumSalary(int $experience): int
    {
        foreach (self::VETERAN_MINIMUM_SALARIES as $years => $salary) {
            if ($experience >= $years) {
                return $salary;
            }
        }
        return self::VETERAN_MINIMUM_SALARIES[1];
    }

    /**
     * Get maximum contract salary for a specific experience level
     * Public static method for use by other classes (e.g., Negotiation processors, view helpers)
     * 
     * @param int $experience Years of experience
     * @return int Maximum first-year contract salary
     */
    public static function getMaxContractSalary(int $experience): int
    {
        foreach (self::MAX_CONTRACT_SALARIES as $years => $salary) {
            if ($experience >= $years) {
                return $salary;
            }
        }
        return self::MAX_CONTRACT_SALARIES[0];
    }

    /**
     * Get existing offer for a player
     * 
     * @param string $teamName
     * @param string $playerName
     * @return array<string, int> Existing offer or empty array
     */
    private function getExistingOffer(string $teamName, string $playerName): array
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
     * Calculate total demands in hundreds
     * 
     * @param array{dem1: int, dem2: int, dem3: int, dem4: int, dem5: int, dem6: int} $demands
     * @return float Total in hundreds (divided by 100, rounded to 2 decimals)
     */
    private function calculateTotalDemands(array $demands): float
    {
        $total = $demands['dem1'] + $demands['dem2'] + $demands['dem3'] 
               + $demands['dem4'] + $demands['dem5'] + $demands['dem6'];
        return round($total / 100, 2);
    }

    /**
     * Calculate number of years in demands
     * 
     * @param array{dem1: int, dem2: int, dem3: int, dem4: int, dem5: int, dem6: int} $demands
     * @return int Number of years
     */
    private function calculateDemandYears(array $demands): int
    {
        if ($demands['dem6'] != 0) return 6;
        if ($demands['dem5'] != 0) return 5;
        if ($demands['dem4'] != 0) return 4;
        if ($demands['dem3'] != 0) return 3;
        if ($demands['dem2'] != 0) return 2;
        return 1;
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
        
        $birdRightsText = $birdYears >= 3
            ? "<b>Bird Rights Player on Your Team:</b> You may add no more than 12.5% of the amount you offer in the first year as a raise between years (for instance, if you offer 500 in Year 1, you cannot offer a raise of more than 62 between any two subsequent years.)"
            : "<b>For Players who do not have Bird Rights with your team:</b> You may add no more than 10% of the amount you offer in the first year as a raise between years (for instance, if you offer 500 in Year 1, you cannot offer a raise of more than 50 between any two subsequent years.)";
        
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
