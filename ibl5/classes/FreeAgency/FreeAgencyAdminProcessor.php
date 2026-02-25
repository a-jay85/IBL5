<?php

declare(strict_types=1);

namespace FreeAgency;

use FreeAgency\Contracts\FreeAgencyAdminProcessorInterface;
use FreeAgency\Contracts\FreeAgencyAdminRepositoryInterface;
use Player\Player;

/**
 * Processes admin free agency operations
 *
 * Handles the administrative workflow for processing free agency day results.
 * Database operations are delegated to FreeAgencyAdminRepository.
 *
 * @see FreeAgencyAdminProcessorInterface
 *
 * @phpstan-import-type OfferRow from FreeAgencyAdminRepositoryInterface
 */
class FreeAgencyAdminProcessor implements FreeAgencyAdminProcessorInterface
{
    private FreeAgencyAdminRepositoryInterface $repository;
    private \mysqli $db;

    public function __construct(FreeAgencyAdminRepositoryInterface $repository, \mysqli $db)
    {
        $this->repository = $repository;
        $this->db = $db;
    }

    /**
     * @see FreeAgencyAdminProcessorInterface::processDay()
     */
    public function processDay(int $day): array
    {
        // Get all offers with bird years, ordered by player then perceived value
        $offers = $this->repository->getAllOffersWithBirdYears();

        $signings = [];
        $rejections = [];
        $autoRejections = [];
        $allOffers = [];
        $newsHomeText = '';
        $newsBodyText = '';
        $discordText = '';

        $processedPlayers = [];

        foreach ($offers as $row) {
            /** @var OfferRow $row */
            $playerName = $row['name'];
            $playerId = $row['pid'];
            $offeringTeamName = $row['team'];
            $perceivedValue = $row['perceivedvalue'];

            $offer1 = $row['offer1'];
            $offer2 = $row['offer2'];
            $offer3 = $row['offer3'];
            $offer4 = $row['offer4'];
            $offer5 = $row['offer5'];
            $offer6 = $row['offer6'];

            $birdYears = $row['bird'];
            $mle = $row['MLE'];
            $lle = $row['LLE'];
            $random = $row['random'];

            // Calculate offer years and total
            $offerYears = $this->calculateOfferYears($offer1, $offer2, $offer3, $offer4, $offer5, $offer6);
            $offerTotal = ($offer1 + $offer2 + $offer3 + $offer4 + $offer5 + $offer6) / 100;

            // Store all offers for display
            $allOffers[] = [
                'playerName' => $playerName,
                'teamName' => $offeringTeamName,
                'offers' => [
                    'offer1' => $offer1,
                    'offer2' => $offer2,
                    'offer3' => $offer3,
                    'offer4' => $offer4,
                    'offer5' => $offer5,
                    'offer6' => $offer6,
                ],
                'birdYears' => $birdYears,
                'mle' => $mle,
                'lle' => $lle,
                'random' => $random,
                'perceivedValue' => $perceivedValue,
            ];

            // Build extended news text for all offers
            $newsBodyText .= "The {$offeringTeamName} offered {$playerName} a {$offerYears}-year deal worth a total of {$offerTotal} million dollars.<br>\n";

            // Get demands for this player
            $demands = $this->getPlayerDemands($playerId, $day);

            // Check if offer is auto-rejected (under half of demands)
            if ($perceivedValue <= $demands / 2) {
                $autoRejections[] = [
                    'playerName' => $playerName,
                    'teamName' => $offeringTeamName,
                    'offers' => [
                        'offer1' => $offer1,
                        'offer2' => $offer2,
                        'offer3' => $offer3,
                        'offer4' => $offer4,
                        'offer5' => $offer5,
                        'offer6' => $offer6,
                    ],
                    'reason' => 'Offer under half of player demands',
                ];
                continue;
            }

            // Only process first (highest value) offer per player
            if (!isset($processedPlayers[$playerName])) {
                $processedPlayers[$playerName] = true;

                // Get team info for IDs
                $offeringTeam = \Team::initialize($this->db, $offeringTeamName);
                $player = Player::withPlayerID($this->db, $playerId);
                $playerTeam = \Team::initialize($this->db, $player->teamName ?? '');

                // Build Discord text
                $discordText .= "**" . strtoupper("{$playerName}, {$playerTeam->city} {$player->teamName}") . "** <@!{$playerTeam->discordID}>\n";

                if ($perceivedValue > $demands) {
                    // Offer accepted
                    $signings[] = [
                        'playerName' => $playerName,
                        'playerId' => $playerId,
                        'teamName' => $offeringTeamName,
                        'teamId' => $offeringTeam->teamID,
                        'offers' => [
                            'offer1' => $offer1,
                            'offer2' => $offer2,
                            'offer3' => $offer3,
                            'offer4' => $offer4,
                            'offer5' => $offer5,
                            'offer6' => $offer6,
                        ],
                        'offerYears' => $offerYears,
                        'offerTotal' => $offerTotal,
                        'usedMle' => $mle === 1,
                        'usedLle' => $lle === 1,
                    ];

                    $outcomeText = "{$playerName} accepts the {$offeringTeamName} offer of a {$offerYears}-year deal worth a total of {$offerTotal} million dollars.";
                    $newsHomeText .= $outcomeText . "<br>\n";
                    $offeringTeamDiscordId = (string) ($offeringTeam->discordID ?? '');
                    $discordText .= $this->buildOfferLine($offeringTeamName, $offer1, $offer2, $offer3, $offer4, $offer5, $offer6, $offeringTeamDiscordId);
                    $discordText .= $outcomeText . " <@!{$offeringTeamDiscordId}>\n\n";
                } else {
                    // Offer rejected
                    $rejections[] = [
                        'playerName' => $playerName,
                        'reason' => 'Best offer did not meet player demands',
                    ];
                    $offeringTeamDiscordId = (string) ($offeringTeam->discordID ?? '');
                    $discordText .= $this->buildOfferLine($offeringTeamName, $offer1, $offer2, $offer3, $offer4, $offer5, $offer6, $offeringTeamDiscordId);
                    $discordText .= "**REJECTED**\n\n";
                }
            } else {
                // Additional offer for already-processed player - add to Discord text
                $offeringTeam = \Team::initialize($this->db, $offeringTeamName);
                // Only add if offer wasn't auto-rejected
                $offeringTeamDiscordId = (string) ($offeringTeam->discordID ?? '');
                $discordText .= $this->buildOfferLine($offeringTeamName, $offer1, $offer2, $offer3, $offer4, $offer5, $offer6, $offeringTeamDiscordId);
            }
        }

        return [
            'signings' => $signings,
            'rejections' => $rejections,
            'autoRejections' => $autoRejections,
            'allOffers' => $allOffers,
            'newsHomeText' => $newsHomeText,
            'newsBodyText' => $newsBodyText,
            'discordText' => $discordText,
        ];
    }

    /**
     * @see FreeAgencyAdminProcessorInterface::executeSignings()
     */
    public function executeSignings(
        int $day,
        array $signings,
        string $newsTitle,
        string $newsHomeText,
        string $newsBodyText
    ): array {
        $successCount = 0;
        $errorCount = 0;

        foreach ($signings as $signing) {
            // Update player contract
            $affected = $this->repository->updatePlayerContract(
                $signing['playerId'],
                $signing['teamName'],
                $signing['teamId'],
                $signing['offerYears'],
                $signing['offers']['offer1'],
                $signing['offers']['offer2'],
                $signing['offers']['offer3'],
                $signing['offers']['offer4'],
                $signing['offers']['offer5'],
                $signing['offers']['offer6']
            );

            if ($affected > 0) {
                $successCount++;
            } else {
                $errorCount++;
            }

            // Mark MLE as used if applicable
            if ($signing['usedMle']) {
                $this->repository->markMleUsed($signing['teamName']);
            }

            // Mark LLE as used if applicable
            if ($signing['usedLle']) {
                $this->repository->markLleUsed($signing['teamName']);
            }
        }

        // Insert news story if there were signings
        if ($successCount > 0 && $newsHomeText !== '' && $newsBodyText !== '') {
            $affected = $this->repository->insertNewsStory($newsTitle, $newsHomeText, $newsBodyText);

            if ($affected > 0) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }

        if ($errorCount === 0 && $successCount > 0) {
            return [
                'success' => true,
                'successCount' => $successCount,
                'errorCount' => $errorCount,
                'message' => "Successfully executed {$successCount} operations. Free agents have been assigned to teams.",
            ];
        } elseif ($errorCount > 0) {
            return [
                'success' => false,
                'successCount' => $successCount,
                'errorCount' => $errorCount,
                'message' => "Completed with errors: {$successCount} operations succeeded, {$errorCount} operations failed.",
            ];
        } else {
            return [
                'success' => false,
                'successCount' => 0,
                'errorCount' => 0,
                'message' => 'No operations were executed.',
            ];
        }
    }

    /**
     * @see FreeAgencyAdminProcessorInterface::clearOffers()
     */
    public function clearOffers(): array
    {
        $this->repository->clearAllOffers();

        return [
            'success' => true,
            'message' => 'Successfully cleared all free agency offers from the database.',
        ];
    }

    /**
     * Calculate number of years in an offer
     */
    private function calculateOfferYears(
        int $offer1,
        int $offer2,
        int $offer3,
        int $offer4,
        int $offer5,
        int $offer6
    ): int {
        $years = 6;
        if ($offer6 === 0) {
            $years = 5;
        }
        if ($offer5 === 0) {
            $years = 4;
        }
        if ($offer4 === 0) {
            $years = 3;
        }
        if ($offer3 === 0) {
            $years = 2;
        }
        if ($offer2 === 0) {
            $years = 1;
        }
        return $years;
    }

    /**
     * Get player demands adjusted for the current day
     *
     * Fetches raw demand data from repository and applies the day-adjusted
     * demand calculation formula.
     */
    private function getPlayerDemands(int $playerID, int $day): float
    {
        $demRow = $this->repository->getPlayerDemands($playerID);

        if ($demRow === null) {
            return 0.0;
        }

        $dem1 = $demRow['dem1'];
        $dem2 = $demRow['dem2'];
        $dem3 = $demRow['dem3'];
        $dem4 = $demRow['dem4'];
        $dem5 = $demRow['dem5'];
        $dem6 = $demRow['dem6'];

        // Calculate demand years
        $demYears = 6;
        if ($dem6 === 0) {
            $demYears = 5;
        }
        if ($dem5 === 0) {
            $demYears = 4;
        }
        if ($dem4 === 0) {
            $demYears = 3;
        }
        if ($dem3 === 0) {
            $demYears = 2;
        }
        if ($dem2 === 0) {
            $demYears = 1;
        }

        // Calculate demands with day adjustment (demands decrease as days progress)
        $totalDemand = $dem1 + $dem2 + $dem3 + $dem4 + $dem5 + $dem6;
        return ($totalDemand / $demYears) * ((11 - $day) / 10);
    }

    /**
     * Build offer line for Discord text
     */
    private function buildOfferLine(
        string $teamName,
        int $offer1,
        int $offer2,
        int $offer3,
        int $offer4,
        int $offer5,
        int $offer6,
        string $discordId
    ): string {
        $line = "{$teamName} - {$offer1}";
        if ($offer2 !== 0) {
            $line .= "/{$offer2}";
        }
        if ($offer3 !== 0) {
            $line .= "/{$offer3}";
        }
        if ($offer4 !== 0) {
            $line .= "/{$offer4}";
        }
        if ($offer5 !== 0) {
            $line .= "/{$offer5}";
        }
        if ($offer6 !== 0) {
            $line .= "/{$offer6}";
        }
        $line .= " <@!{$discordId}>\n";
        return $line;
    }
}
