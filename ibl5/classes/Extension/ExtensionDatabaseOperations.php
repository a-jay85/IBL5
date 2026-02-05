<?php

declare(strict_types=1);

namespace Extension;

use Extension\Contracts\ExtensionDatabaseOperationsInterface;

/**
 * ExtensionDatabaseOperations - Database operations for contract extensions
 *
 * Handles all database operations related to updating player contracts,
 * managing extension usage flags, and creating news stories.
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 * @phpstan-import-type ExtensionOffer from Contracts\ExtensionDatabaseOperationsInterface
 * @phpstan-import-type ContractRow from Contracts\ExtensionDatabaseOperationsInterface
 * @phpstan-import-type ProcessResult from Contracts\ExtensionDatabaseOperationsInterface
 *
 * @phpstan-type ContractDbRow array{cy: int, cy1: int, cy2: int, cy3: int, cy4: int, cy5: int, cy6: int}
 *
 * @see ExtensionDatabaseOperationsInterface
 */
class ExtensionDatabaseOperations implements ExtensionDatabaseOperationsInterface
{
    /** @var \mysqli */
    private \mysqli $db;
    private \Services\NewsService $newsService;

    /**
     * Constructor
     *
     * @param \mysqli $db mysqli connection
     */
    public function __construct(object $db)
    {
        /** @var \mysqli $db */
        $this->db = $db;
        $this->newsService = new \Services\NewsService($db);
    }

    /**
     * @param string $playerName
     * @param ExtensionOffer $offer
     * @param int $currentSalary
     * @return bool
     *
     * @see ExtensionDatabaseOperationsInterface::updatePlayerContract()
     */
    public function updatePlayerContract($playerName, $offer, $currentSalary)
    {
        $offerYears = $this->calculateOfferYears($offer);
        $totalYears = 1 + $offerYears;
        $year4 = $offer['year4'];
        $year5 = $offer['year5'];

        $stmt = $this->db->prepare(
            "UPDATE ibl_plr SET cy = 1, cyt = ?, cy1 = ?, cy2 = ?, cy3 = ?, cy4 = ?, cy5 = ?, cy6 = ? WHERE name = ?"
        );
        if ($stmt === false) {
            return false;
        }
        $stmt->bind_param('iiiiiiis', $totalYears, $currentSalary, $offer['year1'], $offer['year2'], $offer['year3'], $year4, $year5, $playerName);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    /**
     * @see ExtensionDatabaseOperationsInterface::markExtensionUsedThisSim()
     */
    public function markExtensionUsedThisSim($teamName)
    {
        $stmt = $this->db->prepare("UPDATE ibl_team_info SET Used_Extension_This_Chunk = 1 WHERE team_name = ?");
        if ($stmt === false) {
            return false;
        }
        $stmt->bind_param('s', $teamName);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    /**
     * @see ExtensionDatabaseOperationsInterface::markExtensionUsedThisSeason()
     */
    public function markExtensionUsedThisSeason($teamName)
    {
        $stmt = $this->db->prepare("UPDATE ibl_team_info SET Used_Extension_This_Season = 1 WHERE team_name = ?");
        if ($stmt === false) {
            return false;
        }
        $stmt->bind_param('s', $teamName);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    /**
     * @see ExtensionDatabaseOperationsInterface::createAcceptedExtensionStory()
     */
    public function createAcceptedExtensionStory($playerName, $teamName, $offerInMillions, $offerYears, $offerDetails)
    {
        $topicID = $this->newsService->getTopicIDByTeamName($teamName);
        if ($topicID === null) {
            return false;
        }

        $categoryID = $this->newsService->getCategoryIDByTitle('Contract Extensions');
        if ($categoryID === null) {
            return false;
        }

        $this->newsService->incrementCategoryCounter('Contract Extensions');

        // NewsService uses prepared statements - no escaping needed here
        $title = "{$playerName} extends their contract with the {$teamName}";
        $hometext = "{$playerName} today accepted a contract extension offer from the {$teamName} worth {$offerInMillions} million dollars over {$offerYears} years";
        if ($offerDetails !== '') {
            $hometext .= ":<br>" . $offerDetails;
        }
        $hometext .= ".";

        return $this->newsService->createNewsStory($categoryID, $topicID, $title, $hometext);
    }

    /**
     * @see ExtensionDatabaseOperationsInterface::createRejectedExtensionStory()
     */
    public function createRejectedExtensionStory($playerName, $teamName, $offerInMillions, $offerYears)
    {
        $topicID = $this->newsService->getTopicIDByTeamName($teamName);
        if ($topicID === null) {
            return false;
        }

        $categoryID = $this->newsService->getCategoryIDByTitle('Contract Extensions');
        if ($categoryID === null) {
            return false;
        }

        $this->newsService->incrementCategoryCounter('Contract Extensions');

        // NewsService uses prepared statements - no escaping needed here
        $title = "{$playerName} turns down an extension offer from the {$teamName}";
        $hometext = "{$playerName} today rejected a contract extension offer from the {$teamName} worth {$offerInMillions} million dollars over {$offerYears} years.";

        return $this->newsService->createNewsStory($categoryID, $topicID, $title, $hometext);
    }

    /**
     * @param string $playerName
     * @return PlayerRow|null
     *
     * @see ExtensionDatabaseOperationsInterface::getPlayerPreferences()
     */
    public function getPlayerPreferences($playerName)
    {
        $stmt = $this->db->prepare("SELECT * FROM ibl_plr WHERE name = ?");
        if ($stmt === false) {
            return null;
        }
        $stmt->bind_param('s', $playerName);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result === false || $result->num_rows === 0) {
            $stmt->close();
            return null;
        }

        /** @var PlayerRow|null $row */
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row;
    }

    /**
     * @param string $playerName
     * @return ContractRow|null
     *
     * @see ExtensionDatabaseOperationsInterface::getPlayerCurrentContract()
     */
    public function getPlayerCurrentContract($playerName)
    {
        $stmt = $this->db->prepare("SELECT cy, cy1, cy2, cy3, cy4, cy5, cy6 FROM ibl_plr WHERE name = ?");
        if ($stmt === false) {
            return null;
        }
        $stmt->bind_param('s', $playerName);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result === false || $result->num_rows === 0) {
            $stmt->close();
            return null;
        }

        /** @var ContractDbRow|null $dbRow */
        $dbRow = $result->fetch_assoc();
        $stmt->close();

        if ($dbRow === null) {
            return null;
        }

        $cy = $dbRow['cy'];
        $cyField = 'cy' . $cy;
        $currentSalary = 0;
        if ($cy >= 1 && $cy <= 6) {
            /** @var array{cy1: int, cy2: int, cy3: int, cy4: int, cy5: int, cy6: int} $salaryFields */
            $salaryFields = [
                'cy1' => $dbRow['cy1'],
                'cy2' => $dbRow['cy2'],
                'cy3' => $dbRow['cy3'],
                'cy4' => $dbRow['cy4'],
                'cy5' => $dbRow['cy5'],
                'cy6' => $dbRow['cy6'],
            ];
            if (isset($salaryFields[$cyField])) {
                $currentSalary = $salaryFields[$cyField];
            }
        }

        return [
            'cy' => $dbRow['cy'],
            'cy1' => $dbRow['cy1'],
            'cy2' => $dbRow['cy2'],
            'cy3' => $dbRow['cy3'],
            'cy4' => $dbRow['cy4'],
            'cy5' => $dbRow['cy5'],
            'cy6' => $dbRow['cy6'],
            'currentSalary' => $currentSalary,
        ];
    }

    /**
     * @param ExtensionOffer $offer
     * @return int<3, 5>
     */
    private function calculateOfferYears($offer): int
    {
        $years = 5;
        if ($offer['year5'] === 0) {
            $years = 4;
        }
        if ($offer['year4'] === 0) {
            $years = 3;
        }
        return $years;
    }

    /**
     * @param string $playerName
     * @param string $teamName
     * @param ExtensionOffer $offer
     * @param int $currentSalary
     * @return ProcessResult
     *
     * @see ExtensionDatabaseOperationsInterface::processAcceptedExtension()
     */
    public function processAcceptedExtension($playerName, $teamName, $offer, $currentSalary)
    {
        $this->updatePlayerContract($playerName, $offer, $currentSalary);
        $this->markExtensionUsedThisSeason($teamName);
        $offerYears = $this->calculateOfferYears($offer);
        $offerTotal = $offer['year1'] + $offer['year2'] + $offer['year3'] + $offer['year4'] + $offer['year5'];
        $offerInMillions = $offerTotal / 100;
        $offerDetails = $offer['year1'] . " " . $offer['year2'] . " " . $offer['year3'] . " " . $offer['year4'] . " " . $offer['year5'];
        $this->createAcceptedExtensionStory($playerName, $teamName, $offerInMillions, $offerYears, $offerDetails);
        return ['success' => true];
    }

    /**
     * @param string $playerName
     * @param string $teamName
     * @param ExtensionOffer $offer
     * @return ProcessResult
     *
     * @see ExtensionDatabaseOperationsInterface::processRejectedExtension()
     */
    public function processRejectedExtension($playerName, $teamName, $offer)
    {
        $offerYears = $this->calculateOfferYears($offer);
        $offerTotal = $offer['year1'] + $offer['year2'] + $offer['year3'] + $offer['year4'] + $offer['year5'];
        $offerInMillions = $offerTotal / 100;
        $this->createRejectedExtensionStory($playerName, $teamName, $offerInMillions, $offerYears);
        return ['success' => true];
    }
}
