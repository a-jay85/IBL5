<?php

declare(strict_types=1);

namespace FreeAgency;

use FreeAgency\Contracts\FreeAgencyProcessorInterface;

/**
 * @see FreeAgencyProcessorInterface
 */
class FreeAgencyProcessor implements FreeAgencyProcessorInterface
{
    private \mysqli $mysqli_db;
    private FreeAgencyDemandCalculator $calculator;
    private FreeAgencyRepository $repository;
    private \Season $season;

    public function __construct(\mysqli $mysqli_db)
    {
        $this->mysqli_db = $mysqli_db;
        $this->season = new \Season($mysqli_db);

        $demandRepository = new FreeAgencyDemandRepository($this->mysqli_db);
        $this->calculator = new FreeAgencyDemandCalculator($demandRepository);
        $this->repository = new FreeAgencyRepository($this->mysqli_db);
    }

    /**
     * @see FreeAgencyProcessorInterface::processOfferSubmission()
     */
    public function processOfferSubmission(array $postData): array
    {
        // Extract and sanitize input
        /** @var string $teamName */
        $teamName = $postData['teamname'] ?? '';
        $rawPlayerID = $postData['playerID'] ?? 0;
        $playerID = is_numeric($rawPlayerID) ? (int) $rawPlayerID : 0;

        // Load player object
        $player = \Player\Player::withPlayerID($this->mysqli_db, $playerID);

        // Load team object for validation
        $team = \Team::initialize($this->mysqli_db, $teamName);

        // Check if player already signed
        if ($this->repository->isPlayerAlreadySigned($playerID)) {
            return [
                'success' => false,
                'type' => 'already_signed',
                'message' => 'This player was previously signed to a team this Free Agency period.',
                'playerID' => $playerID,
            ];
        }

        // Parse offer data (reuse team object to avoid duplicate DB query)
        $offerData = $this->parseOfferData($player, $postData, $team);

        // Create validator with team data for MLE/LLE checks
        $validator = new FreeAgencyOfferValidator($team);

        // Validate the offer
        $validationResult = $validator->validateOffer($offerData);

        if (!$validationResult['valid']) {
            return [
                'success' => false,
                'type' => 'validation_error',
                'message' => $validationResult['error'] ?? 'Validation failed.',
                'playerID' => $playerID,
            ];
        }

        // Save the offer
        $saveResult = $this->saveOffer($team->name, $player, $offerData);

        if (!$saveResult) {
            return [
                'success' => false,
                'type' => 'save_error',
                'message' => 'Failed to save your offer. Please try again.',
                'playerID' => $playerID,
            ];
        }

        return [
            'success' => true,
            'type' => 'offer_success',
            'message' => 'Your offer is legal and has been saved.',
            'playerID' => $playerID,
        ];
    }

    /**
     * Parse offer data from POST array
     *
     * Reconstructs all derived values (vetmin, max, cap space) from playerID and team.
     * Only user input (offer amounts, offerType) comes from POST data.
     *
     * @param \Player\Player $player Player object
     * @param array<string, mixed> $postData POST data from offer form
     * @param \Team $team Team object (provides team name and cap data)
     * @return array{offer1: int, offer2: int, offer3: int, offer4: int, offer5: int, offer6: int, birdYears: int, offerType: int, vetmin: int, year1Max: int, amendedCapSpaceYear1: int} Parsed offer data
     */
    private function parseOfferData(\Player\Player $player, array $postData, \Team $team): array
    {
        // Reconstruct derived values from player object
        $birdYears = $player->teamName === $team->name ? ($player->birdYears ?? 0) : 0;
        $veteranMinimum = \ContractRules::getVeteranMinimumSalary($player->yearsOfExperience ?? 0);
        $maxContractYear1 = \ContractRules::getMaxContractSalary($player->yearsOfExperience ?? 0);

        // Reconstruct cap space data using provided team object
        $capCalculator = new FreeAgencyCapCalculator($this->mysqli_db, $team, $this->season);
        $playerName = $player->name ?? '';
        $capMetrics = $capCalculator->calculateTeamCapMetrics($playerName);

        // Get existing offer to calculate amended cap space
        $existingOfferRow = $this->repository->getExistingOffer($team->name, $playerName);
        $existingOfferYear1 = $existingOfferRow !== null ? ($existingOfferRow['offer1'] ?? 0) : 0;
        /** @var array{softCapSpace: array<int, int>, hardCapSpace: array<int, int>, totalSalaries: array<int, int>, rosterSpots: array<int, int>} $capMetrics */
        $amendedCapSpaceYear1 = $capMetrics['softCapSpace'][0] + $existingOfferYear1;

        $rawOfferType = $postData['offerType'] ?? 0;
        $offerType = is_numeric($rawOfferType) ? (int) $rawOfferType : 0;

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
            $raw1 = $postData['offeryear1'] ?? 0;
            $raw2 = $postData['offeryear2'] ?? 0;
            $raw3 = $postData['offeryear3'] ?? 0;
            $raw4 = $postData['offeryear4'] ?? 0;
            $raw5 = $postData['offeryear5'] ?? 0;
            $raw6 = $postData['offeryear6'] ?? 0;
            $offer1 = is_numeric($raw1) ? (int) $raw1 : 0;
            $offer2 = is_numeric($raw2) ? (int) $raw2 : 0;
            $offer3 = is_numeric($raw3) ? (int) $raw3 : 0;
            $offer4 = is_numeric($raw4) ? (int) $raw4 : 0;
            $offer5 = is_numeric($raw5) ? (int) $raw5 : 0;
            $offer6 = is_numeric($raw6) ? (int) $raw6 : 0;
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
     * @param array{offer1: int, offer2: int, offer3: int, offer4: int, offer5: int, offer6: int, birdYears: int, offerType: int, vetmin: int, year1Max: int, amendedCapSpaceYear1: int} $offerData Offer details
     * @return bool True if saved successfully
     */
    private function saveOffer(string $teamName, \Player\Player $player, array $offerData): bool
    {
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
        $modifier = (int) ($perceivedValue / $offerAverage); // Approximate
        $random = 0; // Will be recalculated on acceptance

        $playerName = $player->name ?? '';

        // Save the offer using repository
        $saved = $this->repository->saveOffer([
            'teamName' => $teamName,
            'playerName' => $playerName,
            'offer1' => $offerData['offer1'],
            'offer2' => $offerData['offer2'],
            'offer3' => $offerData['offer3'],
            'offer4' => $offerData['offer4'],
            'offer5' => $offerData['offer5'],
            'offer6' => $offerData['offer6'],
            'modifier' => $modifier,
            'random' => $random,
            'perceivedValue' => $perceivedValue,
            'mle' => $mle,
            'lle' => $lle,
            'offerType' => $offerData['offerType'],
        ]);

        // Post to Discord if significant offer
        if ($saved && $offerData['offer1'] > \ContractRules::LLE_OFFER) {
            $this->postOfferToDiscord($teamName, $player);
        }

        return $saved;
    }

    /**
     * Calculate number of years in an offer
     *
     * @param array{offer1: int, offer2: int, offer3: int, offer4: int, offer5: int, offer6: int, birdYears: int, offerType: int, vetmin: int, year1Max: int, amendedCapSpaceYear1: int} $offerData Offer data
     * @return int Number of years
     */
    private function calculateYearsInOffer(array $offerData): int
    {
        $offers = [
            1 => $offerData['offer1'],
            2 => $offerData['offer2'],
            3 => $offerData['offer3'],
            4 => $offerData['offer4'],
            5 => $offerData['offer5'],
            6 => $offerData['offer6'],
        ];

        $years = 6;
        for ($i = 6; $i >= 1; $i--) {
            if ($offers[$i] === 0) {
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
     * @param array{offer1: int, offer2: int, offer3: int, offer4: int, offer5: int, offer6: int, birdYears: int, offerType: int, vetmin: int, year1Max: int, amendedCapSpaceYear1: int} $offerData Offer data
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
        /** @var \mysqli $mysqliDb */
        $mysqliDb = $this->mysqli_db;
        $season = new \Season($mysqliDb);

        if ($season->freeAgencyNotificationsState !== "On") {
            return;
        }

        $discord = new \Discord($mysqliDb);
        $playerTeamName = $player->teamName ?? '';
        $playerTeamDiscordID = $discord->getDiscordIDFromTeamname($playerTeamName);

        if ($teamName === $player->teamName) {
            $message = "Free agent **{$player->name}** has been offered a contract to _stay_ with the **{$player->teamName}**.
_**{$player->teamName}** GM <@!$playerTeamDiscordID> could not be reached for comment._";
        } else {
            $message = "Free agent **{$player->name}** has been offered a contract to _leave_ the **{$player->teamName}**.
_**{$player->teamName}** GM <@!$playerTeamDiscordID> could not be reached for comment._";
        }

        \Discord::postToChannel('#free-agency', $message);
    }

    /**
     * @see FreeAgencyProcessorInterface::deleteOffers()
     */
    public function deleteOffers(string $teamName, int $playerID): array
    {
        $player = \Player\Player::withPlayerID($this->mysqli_db, $playerID);

        $this->repository->deleteOffer($teamName, $player->name ?? '');

        return ['success' => true];
    }
}
