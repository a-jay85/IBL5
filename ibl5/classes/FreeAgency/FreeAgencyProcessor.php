<?php

namespace FreeAgency;

/**
 * Orchestrates free agency operations
 * 
 * Handles the complete workflow for:
 * - Displaying free agents and offers
 * - Processing contract negotiations
 * - Calculating cap space and roster spots
 * - Coordinating with specialized classes
 */
class FreeAgencyProcessor
{
    private $db;
    private FreeAgencyOfferValidator $validator;
    private FreeAgencyDemandCalculator $calculator;
    private \Services\DatabaseService $databaseService;

    public function __construct($db)
    {
        $this->db = $db;
        $this->validator = new FreeAgencyOfferValidator($db);
        $this->calculator = new FreeAgencyDemandCalculator($db);
        $this->databaseService = new \Services\DatabaseService();
    }

    /**
     * Process a contract offer submission
     * 
     * @param array<string, mixed> $postData POST data from offer form
     * @return string HTML response
     */
    public function processOfferSubmission(array $postData): string
    {
        // Extract and sanitize input
        $teamName = $postData['teamname'] ?? '';
        $playerID = (int) ($postData['playerID'] ?? 0);
        
        // Load player object
        $player = \Player\Player::withPlayerID($this->db, $playerID);
        
        // Check if player already signed
        if ($this->validator->isPlayerAlreadySigned($player->name)) {
            return $this->renderOfferResponse(
                false,
                "Sorry, this player was previously signed to a team this Free Agency period.<p>
                Please <a href=\"/ibl5/modules.php?name=Free_Agency\">click here to return to the Free Agency main page</a>."
            );
        }
        
        // Parse offer data
        $offerData = $this->parseOfferData($postData, $player);
        
        // Validate the offer
        $validationResult = $this->validator->validateOffer($offerData);
        
        if (!$validationResult['valid']) {
            return $this->renderOfferResponse(false, $validationResult['error']);
        }
        
        // Save the offer
        $saveResult = $this->saveOffer($teamName, $player, $offerData);
        
        return $this->renderOfferResponse($saveResult, 
            $saveResult 
                ? "Your offer is legal. It should be immediately reflected in your Free Agency module.<br>Please <a href=\"/ibl5/modules.php?name=Free_Agency\">click here to return to the Free Agency module</a>."
                : "Failed to save your offer. Please try again."
        );
    }

    /**
     * Parse offer data from POST array
     * 
     * Reconstructs all derived values (vetmin, max, cap space) from playerID and teamName.
     * Only user input (offer amounts, offerType) comes from POST data.
     * 
     * @param array<string, mixed> $postData POST data
     * @param \Player\Player $player Player object
     * @return array<string, mixed> Parsed offer data
     */
    private function parseOfferData(array $postData, \Player\Player $player): array
    {
        $teamName = $postData['teamname'] ?? '';
        
        // Reconstruct derived values from player object
        $birdYears = $player->teamName == $teamName ? $player->birdYears : 0;
        $veteranMinimum = \ContractRules::getVeteranMinimumSalary($player->yearsOfExperience);
        $maxContractYear1 = \ContractRules::getMaxContractSalary($player->yearsOfExperience);
        
        // Reconstruct cap space data
        $team = \Team::initialize($this->db, $teamName);
        $capCalculator = new FreeAgencyCapCalculator($this->db);
        $capData = $capCalculator->calculateNegotiationCapSpace($team, $player->name);
        
        // Get existing offer to calculate amended cap space
        $helper = new FreeAgencyNegotiationHelper($this->db);
        $existingOffer = $helper->getExistingOffer($teamName, $player->name);
        $amendedCapSpaceYear1 = $capData['softCap']['year1'] + $existingOffer['offer1'];
        
        $offerType = (int) ($postData['offerType'] ?? 0);
        
        // Parse offer amounts based on exception type
        if (OfferType::isVeteranMinimum($offerType)) {
            // Veteran's minimum
            $offer1 = $veteranMinimum;
            $offer2 = 0;
            $offer3 = 0;
            $offer4 = 0;
            $offer5 = 0;
            $offer6 = 0;
        } elseif (OfferType::isLLE($offerType)) {
            // Lower-Level Exception
            $offer1 = \ContractRules::LLE_OFFER;
            $offer2 = 0;
            $offer3 = 0;
            $offer4 = 0;
            $offer5 = 0;
            $offer6 = 0;
        } elseif (OfferType::isMLE($offerType)) {
            // Mid-Level Exception
            $mleOffers = \ContractRules::MLE_OFFERS;
            $offer1 = $mleOffers[0];
            $offer2 = $offerType >= 2 ? $mleOffers[1] : 0;
            $offer3 = $offerType >= 3 ? $mleOffers[2] : 0;
            $offer4 = $offerType >= 4 ? $mleOffers[3] : 0;
            $offer5 = $offerType >= 5 ? $mleOffers[4] : 0;
            $offer6 = $offerType >= 6 ? $mleOffers[5] : 0;
        } else {
            // Custom offer
            $offer1 = (int) ($postData['offeryear1'] ?? 0);
            $offer2 = (int) ($postData['offeryear2'] ?? 0);
            $offer3 = (int) ($postData['offeryear3'] ?? 0);
            $offer4 = (int) ($postData['offeryear4'] ?? 0);
            $offer5 = (int) ($postData['offeryear5'] ?? 0);
            $offer6 = (int) ($postData['offeryear6'] ?? 0);
        }
        
        return [
            'offer1' => $offer1,
            'offer2' => $offer2,
            'offer3' => $offer3,
            'offer4' => $offer4,
            'offer5' => $offer5,
            'offer6' => $offer6,
            'birdYears' => $birdYears,
            'offerType' => $offerType,
            'vetmin' => $veteranMinimum,
            'year1Max' => $maxContractYear1,
            'amendedCapSpaceYear1' => $amendedCapSpaceYear1,
        ];
    }

    /**
     * Save a validated offer to the database
     * 
     * @param string $teamName Offering team
     * @param \Player\Player $player Player object
     * @param array<string, mixed> $offerData Offer details
     * @return bool True if saved successfully
     */
    private function saveOffer(string $teamName, \Player\Player $player, array $offerData): bool
    {
        $escapedTeamName = $this->databaseService->escapeString($this->db, $teamName);
        $escapedPlayerName = $this->databaseService->escapeString($this->db, $player->name);
        
        // Delete any existing offer from this team to this player
        $deleteQuery = "DELETE FROM ibl_fa_offers 
                        WHERE name = '$escapedPlayerName' 
                          AND team = '$escapedTeamName' 
                        LIMIT 1";
        $this->db->sql_query($deleteQuery);
        
        // Calculate perceived value
        $yearsInOffer = $this->calculateYearsInOffer($offerData);
        $offerAverage = $this->calculateOfferAverage($offerData, $yearsInOffer);
        
        $perceivedValue = $this->calculator->calculatePerceivedValue(
            $offerAverage,
            $teamName,
            $player,
            $yearsInOffer
        );
        
        // Determine MLE/LLE flags
        $mle = OfferType::isMLE($offerData['offerType']) ? 1 : 0;
        $lle = OfferType::isLLE($offerData['offerType']) ? 1 : 0;
        
        // Calculate modifier and random for storage (extract from perceived value calculation)
        $modifier = $perceivedValue / $offerAverage; // Approximate
        $random = 0; // Will be recalculated on acceptance
        
        // Insert the new offer
        $insertQuery = "INSERT INTO ibl_fa_offers 
                        (name, team, offer1, offer2, offer3, offer4, offer5, offer6, 
                         modifier, random, perceivedvalue, mle, lle, offer_type) 
                        VALUES 
                        ('$escapedPlayerName', '$escapedTeamName', 
                         {$offerData['offer1']}, {$offerData['offer2']}, {$offerData['offer3']}, 
                         {$offerData['offer4']}, {$offerData['offer5']}, {$offerData['offer6']}, 
                         $modifier, $random, $perceivedValue, $mle, $lle, {$offerData['offerType']})";
        
        $result = $this->db->sql_query($insertQuery);
        
        // Post to Discord if significant offer
        if ($result && $offerData['offer1'] > \ContractRules::LLE_OFFER) {
            $this->postOfferToDiscord($teamName, $player);
        }
        
        return (bool) $result;
    }

    /**
     * Calculate number of years in an offer
     * 
     * @param array<string, mixed> $offerData Offer data
     * @return int Number of years
     */
    private function calculateYearsInOffer(array $offerData): int
    {
        $years = 6;
        for ($i = 6; $i >= 1; $i--) {
            if ($offerData["offer{$i}"] == 0) {
                $years = $i - 1;
            } else {
                break;
            }
        }
        return max(1, $years);
    }

    /**
     * Calculate average salary per year
     * 
     * @param array<string, mixed> $offerData Offer data
     * @param int $yearsInOffer Years in offer
     * @return int Average salary
     */
    private function calculateOfferAverage(array $offerData, int $yearsInOffer): int
    {
        $total = $offerData['offer1'] + $offerData['offer2'] + $offerData['offer3'] 
               + $offerData['offer4'] + $offerData['offer5'] + $offerData['offer6'];
        
        return (int) ($total / $yearsInOffer);
    }

    /**
     * Post offer notification to Discord
     * 
     * @param string $teamName Offering team
     * @param \Player\Player $player Player object
     * @return void
     */
    private function postOfferToDiscord(string $teamName, \Player\Player $player): void
    {
        $season = new \Season($this->db);
        
        if ($season->freeAgencyNotificationsState != "On") {
            return;
        }
        
        $playerTeamDiscordID = \Discord::getDiscordIDFromTeamname($this->db, $player->teamName);
        
        if ($teamName == $player->teamName) {
            $message = "Free agent **{$player->name}** has been offered a contract to _stay_ with the **{$player->teamName}**.
_**{$player->teamName}** GM <@!$playerTeamDiscordID> could not be reached for comment._";
        } else {
            $message = "Free agent **{$player->name}** has been offered a contract to _leave_ the **{$player->teamName}**.
_**{$player->teamName}** GM <@!$playerTeamDiscordID> could not be reached for comment._";
        }
        
        \Discord::postToChannel('#free-agency', $message);
    }

    /**
     * Render offer submission response
     * 
     * @param bool $success Whether offer was successful
     * @param string $message Message to display
     * @return string HTML response
     */
    private function renderOfferResponse(bool $success, string $message): string
    {
        ob_start();
        ?>
<html>
<head>
    <title>Free Agency Offer Entry</title>
</head>
<body>
    <?php if ($success): ?>
        <?= $message ?>
    <?php else: ?>
        <font color="#ff0000">
            <?= $message ?>
            <br>Please go "Back" in your browser to try again.
        </font>
    <?php endif; ?>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    /**
     * Delete all offers from a team to a player
     * 
     * @param string $teamName Team name
     * @param int $playerID Player ID
     * @return string HTML response
     */
    public function deleteOffers(string $teamName, int $playerID): string
    {
        $player = \Player\Player::withPlayerID($this->db, $playerID);
        
        $escapedTeamName = $this->databaseService->escapeString($this->db, $teamName);
        $escapedPlayerName = $this->databaseService->escapeString($this->db, $player->name);
        
        $query = "DELETE FROM ibl_fa_offers 
                  WHERE name = '$escapedPlayerName' 
                    AND team = '$escapedTeamName'";
        $this->db->sql_query($query);
        
        ob_start();
        ?>
<html>
<head>
    <title>Free Agency Offer Deletion</title>
</head>
<body>
    Your offers have been deleted. This should show up immediately. 
    Please <a href="/ibl5/modules.php?name=Free_Agency">click here to return to the Free Agency main page</a> 
    (your offer should now be gone).
</body>
</html>
        <?php
        return ob_get_clean();
    }
}
