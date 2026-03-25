<?php

declare(strict_types=1);

namespace Extension;

use Extension\Contracts\ExtensionRepositoryInterface;

/**
 * ExtensionRepository - Database operations for contract extensions
 *
 * Handles all database operations related to updating player contracts,
 * managing extension usage flags, reading team data, and creating news stories.
 *
 * @phpstan-import-type ExtensionOffer from Contracts\ExtensionRepositoryInterface
 * @phpstan-import-type TraditionData from Contracts\ExtensionRepositoryInterface
 *
 * @phpstan-type TeamTraditionDbRow array{Contract_Wins: int, Contract_Losses: int, Contract_AvgW: int, Contract_AvgL: int}
 * @phpstan-type MoneyCommittedDbRow array{money_committed_at_position: int}
 *
 * @see ExtensionRepositoryInterface
 */
class ExtensionRepository extends \BaseMysqliRepository implements ExtensionRepositoryInterface
{
    private \Services\NewsService $newsService;

    /**
     * @param \mysqli $db Active mysqli connection
     * @param \Services\NewsService|null $newsService Optional NewsService injection
     */
    public function __construct(\mysqli $db, ?\Services\NewsService $newsService = null)
    {
        parent::__construct($db);
        $this->newsService = $newsService ?? new \Services\NewsService($db);
    }

    /**
     * @see ExtensionRepositoryInterface::updatePlayerContract()
     */
    public function updatePlayerContract(string $playerName, array $offer, int $currentSalary): bool
    {
        $offerYears = $this->calculateOfferYears($offer);
        $totalYears = 1 + $offerYears;

        try {
            $this->execute(
                "UPDATE ibl_plr SET cy = 1, cyt = ?, cy1 = ?, cy2 = ?, cy3 = ?, cy4 = ?, cy5 = ?, cy6 = ? WHERE name = ?",
                'iiiiiiis',
                $totalYears,
                $currentSalary,
                $offer['year1'],
                $offer['year2'],
                $offer['year3'],
                $offer['year4'],
                $offer['year5'],
                $playerName
            );
            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    /**
     * @see ExtensionRepositoryInterface::markExtensionUsedThisSim()
     */
    public function markExtensionUsedThisSim(string $teamName): bool
    {
        try {
            $this->execute(
                "UPDATE ibl_team_info SET Used_Extension_This_Chunk = 1 WHERE team_name = ?",
                's',
                $teamName
            );
            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    /**
     * @see ExtensionRepositoryInterface::markExtensionUsedThisSeason()
     */
    public function markExtensionUsedThisSeason(string $teamName): bool
    {
        try {
            $this->execute(
                "UPDATE ibl_team_info SET Used_Extension_This_Season = 1 WHERE team_name = ?",
                's',
                $teamName
            );
            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    /**
     * @see ExtensionRepositoryInterface::createAcceptedExtensionStory()
     */
    public function createAcceptedExtensionStory(string $playerName, string $teamName, float $offerInMillions, int $offerYears, string $offerDetails): bool
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

        $title = "{$playerName} extends their contract with the {$teamName}";
        $hometext = "{$playerName} today accepted a contract extension offer from the {$teamName} worth {$offerInMillions} million dollars over {$offerYears} years";
        if ($offerDetails !== '') {
            $hometext .= ":<br>" . $offerDetails;
        }
        $hometext .= ".";

        return $this->newsService->createNewsStory($categoryID, $topicID, $title, $hometext);
    }

    /**
     * @see ExtensionRepositoryInterface::createRejectedExtensionStory()
     */
    public function createRejectedExtensionStory(string $playerName, string $teamName, float $offerInMillions, int $offerYears): bool
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

        $title = "{$playerName} turns down an extension offer from the {$teamName}";
        $hometext = "{$playerName} today rejected a contract extension offer from the {$teamName} worth {$offerInMillions} million dollars over {$offerYears} years.";

        return $this->newsService->createNewsStory($categoryID, $topicID, $title, $hometext);
    }

    /**
     * @see ExtensionRepositoryInterface::getTeamTraditionData()
     */
    public function getTeamTraditionData(string $teamName): array
    {
        $defaults = [
            'currentSeasonWins' => 41,
            'currentSeasonLosses' => 41,
            'tradition_wins' => 41,
            'tradition_losses' => 41,
        ];

        try {
            /** @var TeamTraditionDbRow|null $row */
            $row = $this->fetchOne(
                "SELECT Contract_Wins, Contract_Losses, Contract_AvgW, Contract_AvgL FROM ibl_team_info WHERE team_name = ? LIMIT 1",
                's',
                $teamName
            );

            if ($row !== null) {
                return [
                    'currentSeasonWins' => $row['Contract_Wins'],
                    'currentSeasonLosses' => $row['Contract_Losses'],
                    'tradition_wins' => $row['Contract_AvgW'],
                    'tradition_losses' => $row['Contract_AvgL'],
                ];
            }
        } catch (\RuntimeException $e) {
            \Logging\LoggerFactory::getChannel('app')->warning('ExtensionRepository::getTeamTraditionData failed', ['error' => $e->getMessage()]);
        }

        return $defaults;
    }

    /**
     * @see ExtensionRepositoryInterface::getMoneyCommittedAtPosition()
     */
    public function getMoneyCommittedAtPosition(string $teamName): int
    {
        try {
            /** @var MoneyCommittedDbRow|null $row */
            $row = $this->fetchOne(
                "SELECT money_committed_at_position FROM ibl_team_info WHERE team_name = ? LIMIT 1",
                's',
                $teamName
            );

            if ($row !== null && $row['money_committed_at_position'] > 0) {
                return $row['money_committed_at_position'];
            }
        } catch (\RuntimeException $e) {
            \Logging\LoggerFactory::getChannel('app')->warning('ExtensionRepository::getMoneyCommittedAtPosition failed', ['error' => $e->getMessage()]);
        }

        return 0;
    }

    /**
     * @see ExtensionRepositoryInterface::saveAcceptedExtension()
     */
    public function saveAcceptedExtension(
        string $playerName,
        string $teamName,
        array $offer,
        int $currentSalary,
        float $offerInMillions,
        int $offerYears,
        string $offerDetails
    ): void {
        $this->transactional(function () use ($playerName, $teamName, $offer, $currentSalary, $offerInMillions, $offerYears, $offerDetails): void {
            if (!$this->updatePlayerContract($playerName, $offer, $currentSalary)) {
                throw new \RuntimeException('Failed to update player contract');
            }
            if (!$this->markExtensionUsedThisSeason($teamName)) {
                throw new \RuntimeException('Failed to mark extension used this season');
            }
            $this->createAcceptedExtensionStory($playerName, $teamName, $offerInMillions, $offerYears, $offerDetails);
        });
    }

    /**
     * @param ExtensionOffer $offer
     * @return int<3, 5>
     */
    private function calculateOfferYears(array $offer): int
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
}
